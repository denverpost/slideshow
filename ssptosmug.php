<?php
/*
ASSUMPTIONS:
1. There is XML of the slideshows you want to import, and that XML lives in a directory named SSPXML.
2. Ugh does this script really need to be run via HTTP ugh no it doesn't
*/
include("share_functions.php");
include("classes/DirectorPHP.php");
date_default_timezone_set('America/Denver');

if ( isset($_GET["SiteName"]) ):
    include( $_SERVER['DOCUMENT_ROOT'] . '/wp-blog-header.php');
    $_SESSION['SiteName'] = $_GET["SiteName"];
    //$_SESSION['MCFolder'] = $_GET["MCFolder"];
    $_SESSION['smugmugurl'] = $_GET['smugmugurl'];
    $thispage = $_SERVER['REQUEST_URI'];
    $refreshtime = "30";
    header("Refresh: $refreshtime; url=$thispage");
    //$dir = "/SSPXML/";
else:
    $_SESSION['SiteName'] = 'heyreverb';
    $_SESSION['smugmugurl'] = 'http://heyreverb.smugmug.com';
endif;

$album_count = 0;
set_time_limit(0);


/* Create tables sql:
CREATE TABLE `sspexport` (
`sspid` varchar( 255 ) NOT NULL ,
`smugid` int( 11 ) NOT NULL ,
`smugkey` varchar( 255 ) NOT NULL ,
`totalphotos` int( 11 ) NOT NULL ,
`currentphotos` int( 11 ) NOT NULL ,
`status` varchar( 255 ) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = latin1;
*/

class ssptosmug
{
    // Manage interactions between SSP and SmugMug galleries
    var $f; // phpSmug object.
    var $director; // SSP API object.
    var $ssp; // SSP data
    var $smug_category;
    var $user="root";
    var $password="root";
    var $database="wp_mc";
    var $mysqli;

    function __construct()
    {
        $this->smug_category = 0;
        $this->mysqli = mysqli_connect(localhost, $this->user, $this->password, $this->database);

        // Authenticate with SSP
        $this->authenticate_ssp();
        $this->authenticate_smug();
    }

    function authenticate_ssp()
    {
        $this->director = new Director(getenv('SSP_API_KEY'), getenv('SSP_API_PATH'));
    }

    function authenticate_smug()
    {
        $smugURL = $_SESSION['smugmugurl'];

        // SMUGMUG caching            
        $smugvalues = getSmugApi($smugURL); //returns smug values for these images based on what instance they are in
        $tokenarray = unserialize($smugvalues[0]['smug_token']);
        $cachevar = dirname(__FILE__) . 'smugcache';	

        // APC Cache Version
        $this->f = new phpSmug("APIKey={$smugvalues[0]['smug_api_key']}", "AppName=DFM Photo Gallery 1.0", "OAuthSecret={$smugvalues[0]['smug_secret']}", "APIVer=1.3.0");
        $cache_result = $this->f->enableCache("type=apc", "cache_dir={$cachevar}", "cache_expire=180" );
        $this->f->setToken( "id={$tokenarray['Token']['id']}", "Secret={$tokenarray['Token']['Secret']}" );
    }
            
    function get_ssp_galleries($type='all', $id=0)
    {
        // Takes two parameters: $type, which defaults to 'all' but can be 'one',
        // and $id, which should have a non-zero value if you're passing $type 'one'.
        // Example use:
        // get_ssp_galleries(); // see a list of the galleries available and their id's. Figure out the id you want, then:
        // get_ssp_galleries('one', the-id-you-want);
        if ( $type == 'all' ):
            $galleries = $this->director->gallery->all();
            foreach ( $galleries as $gallery ):
                echo $gallery->name . ": " . $gallery->id . "\n";
            endforeach;
        elseif ( $type == 'one' ):
            echo $id;
            $this->ssp['gallery'] = $this->director->gallery->get($id);
        endif;
        
    }    

    function get_ssp_albums($type='all', $id=0)
    {
        // Wrapper method for SSP's album methods. Returns false if nothing happens.
        if ( $type == 'all' ):
            $albums = $this->director->album->all(array('list_only' => true));
            $this->ssp['albums'] = $albums;
            return $albums;
        elseif ( $type == 'one' && $id > 0 ):
            $album = $this->director->album->get($id);
            return $album;
        endif;

        return false;
    }    

    function get_ssp_image()
    {
        // Takes a SSP album's image array,
        // downloads the image locally.
    }

    function get_smug_categories()
    {
        // Gosh this method needs work.
        $cats = $this->f->categories_get();
        foreach ( $cats as $item ):
            echo $item['Name'] . ': ' . $item['id'] . "\n";
        endforeach;

        return $cats;
    }

    function set_smug_category($category)
    {
        // Setter method for smug category
        $this->smug_category = $category;
    }

    function create_smug_album($ssp_album_id)
    {
        /*
            Takes an SSP album object, loops through it creating the objects in smugmug as necessary.
            1. Check if album already exists.
            2. Create album if not.
            3. If the album already exists, see if we have any more images to upload.
            4. Upload images as necessary
            5. Log all the actions.

            Values that we need for a new album in smugmug:
            * Title
            * CategoryID
            * # of images in the album
        */
        // Get the full details of the album here:
        $ssp_album = $this->get_ssp_albums('one', $ssp_album_id);
        $title = $ssp_album->name;
        $cat_id = $this->smug_category;
        $result = $this->check_album_log($ssp_album_id);
        $total_photos = count($ssp_album->contents);

        if ( $result->num_rows == 0 ):
            // NEW ALBUM YEA YEA YEA.
            echo date('m/d/Y h:i:s a', time()) . "\n";
            $smug_album = $this->f->albums_create("Title=$title", "CategoryID=$cat_id", "Protected=true", "Printable=true", "Public=true", "Larges=true", "Originals=false", "X2Larges=false", "X3Larges=false", "XLarges=false", "SmugSearchable=true");
            $smug_id = strval($smug_album['id']);
            $smug_key = $smug_album['Key'];				
            $this->log_album($ssp_album->id, $smug_id, $smug_key, $total_photos, 0, 'NEW');
            $current_photo = 0;
        else:
            // We're in the middle of this album upload, so we need to see which photo we're on.
            // That means we need to take the $result result and turn it into vars.
            // $result will result in these vars:
            //   `sspid` `smugid` `smugkey` `totalphotos` `currentphotos` `status` 
            $row = $result->fetch_assoc();
            $current_photo = $row['currentphotos'];
            $smug_id = $row['smugid'];
        endif; 

        // Here we upload all the images to the gallery we have yet to upload.
        while ( $current_photo < $total_photos ):
            //echo $ssp_album->contents[$current_photo]->original->url . "\n";
            $caption = $ssp_album->contents[$current_photo]->caption;
            $this->create_smug_image($smug_id, $ssp_album->contents[$current_photo]->original->url, $caption);
            $current_photo += 1;
            $exists = True;
            $this->log_album($ssp_album->id, $smug_id, '', $total_photos, $current_photo, 'INPROGRESS', $exists);
        endwhile;
        echo "\nTOTAL PHOTOS: " . $total_photos;
        $this->log_album($ssp_album->id, $smug_id, '', $total_photos, $current_photo, 'DONE', True);
        //if ($albumStatus != "DONE" && $albumStatus != "ERROR"){mcsmugcheckalbum($path, $xml, $f);}
    }

    function create_smug_image($album_id, $image_path, $caption='')
    {
        // With the two required parameters, upload an image to a gallery.
        $image_type = 'File';
        if ( strpos($image_path, 'http') !== FALSE ) $image_type = 'URL';

        if ( $image_type == 'File' ):
            $this->f->images_upload("AlbumID=$album_id", "$image_type=$image_path", "Caption=$caption");
        elseif ( $image_type == 'URL' ):
            $this->f->images_uploadFromURL("AlbumID=$album_id", "$image_type=$image_path", "Caption=$caption");
        endif;

        return True;
    }

    function log_album($ssp_id, $smug_id, $smug_key, $total_photos, $current_photo=0, $status='NEW', $exists = False)
    {
        // Create an entry in the sspexport database logging that we've created this album.
        // To do this we need to update / create a record with these values:
        //   `sspid` `smugid` `smugkey` `totalphotos` `currentphotos` `status` 

        //CREATE
        if ( $exists === False ):
            $sql = "INSERT INTO sspexport VALUES ('$ssp_id', '$smug_id', '$smug_key','$total_photos','$current_photo','$status')";
        elseif ( $exists == True ):
            $sql = "UPDATE sspexport SET 
                currentphotos='" . $current_photo . "',
                status='" . $status . "'
            WHERE sspid='" . $ssp_id. "'
            LIMIT 1";
        else:
            echo "EXISTS: " . $exists;
        endif;
        $this->mysqli->query($sql) or die('query failed:' . $this->mysqli->error . "\nsql:" . $sql);
    }

    function check_album_log($album_id)
    {
        // Check the database, see if we've uploaded this album already.
        // Also make sure that the number of images in the album are the #
        // of images that we've uploaded.
        $sql = "SELECT * FROM sspexport WHERE sspid = $album_id LIMIT 1";
        $result = $this->mysqli->query($sql);
        return $result;
    }
}
$ssptosmug = new ssptosmug();
//$ssptosmug->get_ssp_galleries('one', 24943);
$albums = $ssptosmug->get_ssp_albums();
$reverb_category_is_0 = 0;
$current_album = intval(trim(file_get_contents("album_count"))) - 1;
foreach ( $albums as $album ):
    $album_count += 1; 
    if ( $album_count < $current_album ) continue;
    echo " ALBUMS UPLOADED: " . $album_count . " ";
    $created = $ssptosmug->create_smug_album($album->id);
    file_put_contents("album_count", $album_count);
endforeach;
//$album = $ssptosmug->get_ssp_albums('one', 450352);
//$ssptosmug->get_smug_categories();
