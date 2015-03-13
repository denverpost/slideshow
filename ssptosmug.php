<?php
/*
ASSUMPTIONS:
1. There is XML of the slideshows you want to import, and that XML lives in a directory named SSPXML.
2. Ugh does this script really need to be run via HTTP ugh no it doesn't
*/
include("share_functions.php");
include("classes/DirectorPHP.php");

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
    $_SESSION['smugmugurl'] = 'http://heyreveb.smugmug.com';
endif;

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
            $this->ssp['albums'] = $this->director->album->all(array('list_only' => true));
            return $this->ssp['albums'];
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

    function create_smug_album($ssp_album)
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
        $title = $ssp_album->name;
        $cat_id = $this->smug_category;
        $result = $this->check_album_log($ssp_album->id);
        $total_photos = count($ssp_album->contents);

        if ( $result->num_rows === 0 ):
            // NEW ALBUM YEA YEA YEA.
            //$smug_album = $this->f->albums_create("Title=$title", "CategoryID=$cat_id", "Protected=true", "Printable=true", "Public=true", "Larges=true", "Originals=false", "X2Larges=false", "X3Larges=false", "XLarges=false", "SmugSearchable=true");
            $smug_id = strval($smug_album['id']);
        //$smugkey = $smug_album['Key'];				
            $this->log_smug_album($ssp_album->id, $smug_id, $total_photos, 0, 'NEW');
            $current_photo = 0;
        else:
            // We're in the middle of this album upload, so we need to see which photo we're on.
            //function log_album_creation($album_id, $total_photos, $current_photo=0, $status='NEW', $exists = False, $ssp_id = 0)
            echo 'hi';           
        endif; 

        //if ($albumStatus != "DONE" && $albumStatus != "ERROR"){mcsmugcheckalbum($path, $xml, $f);}
    }

    function create_smug_image($album_id, $image_path)
    {
        // With the two required parameters, upload an image to a gallery.
        $image_type = 'File';
        if ( strpos('http', $image_path) !== FALSE ) $image_type = 'URL';

        if ( $image_type == 'File' ):
            $this->f->images_upload("AlbumID=$album_id", "$image_type=$image_path");
        elseif ( $image_type == 'URL' ):
            $this->f->images_uploadFromURL("AlbumID=$album_id", "$image_type=$image_path");
        endif;

        return True;
    }

    function log_album_creation($ssp_id, $smug_id, $total_photos, $current_photo=0, $status='NEW', $exists = False)
    {
        // Create an entry in the sspexport database logging that we've created this album.
        // To do this we need to update / create a record with these values:
        //   `sspid` `smugid` `smugkey` `totalphotos` `currentphotos` `status` 

        //CREATE
        if ( $exists == False ):
            $sql = "INSERT INTO sspexport VALUES ('$ssp_id', '$smug_id', '' ,'$total_photos','$current_photo','$status')";
        elseif ( $exists == True ):
            $sql = "UPDATE sspexport SET 
                currentphotos='" . $current_photo . "',
                status='" . $status . "'
            WHERE smugid='" . $smug_id. "'
            LIMIT 1";
        endif;
        $this->mysqli->query($sql) or die('query failed:' . $this->mysqli->error() . "\nsql:" . $sql);
    }

    function log_image_upload()
    {
    }

    function check_album_log($album_id)
    {
        // Check the database, see if we've uploaded this album already.
        // Also make sure that the number of images in the album are the #
        // of images that we've uploaded.
        $sql = "SELECT * FROM sspexport WHERE smugid = $album_id LIMIT 1";
        $result = $this->mysqli->query($sql);
        return $result;
    }
}
$ssptosmug = new ssptosmug();
//$ssptosmug->get_ssp_galleries('one', 24943);
//$albums = $ssptosmug->get_ssp_albums();
$reverb_category_is_0 = 0;
$album = $ssptosmug->get_ssp_albums('one', 450352);
$created = $ssptosmug->create_smug_album($album);
//$ssptosmug->get_smug_categories();

//FUNCTION: check category
function mcsmugcategory ($smugObj) {
	$smugcats = $smugObj->categories_get();
	//var_dump($smugcats);
	//Check if there is a mycapture category
	foreach($smugcats as $item){
		//echo $item["Name"];
    		if (isset($item["Name"]) && $item["Name"] == "MyCapture"){
    			echo "Mycapture!";
			$catID = $item;
		}
	}

    if(!isset($catID)){
        $catID = $smugObj->categories_create("Name=MyCapture");
    }
    echo $catID["id"] . "\n";
    return $catID["id"];

}

//FUNCTION: New Album
function mcsmugnewalbum ($mcXMLPath, $mcXMLFile, $albumcatID, $smugObj, $albumLog, $retry){
    /*
        Values that we need for a new album in smugmug:
        * Title
        * CategoryID
        * # of images in the album
        * 
    */
	$title = $mcXMLFile->channel->EVENT_TITLE;
	echo "<h1>CATEGORY: " . $albumcatID . "</h1>";
	$newAlbum = $smugObj->albums_create("Title=$title", "CategoryID=$albumcatID", "Protected=true", "Printable=true", "Public=true", "Larges=true", "Originals=false", "X2Larges=false", "X3Larges=false", "XLarges=false", "SmugSearchable=true");
	//var_dump ($smugObj);
	$smugAlbumID = strval($newAlbum['id']);
	$smugAlbumKey = $newAlbum['Key'];
	$totalImages = count($mcXMLFile->channel->item);

	if ($smugAlbumID == "" || $smugAlbumKey == "") mcsmugnewalbum ($mcXMLPath, $mcXMLFile, $smugObj, $albumLog);

	if ($retry == false){
		$processedquery = "INSERT INTO sspexport VALUES ('$mcXMLPath', '$smugAlbumID', '$smugAlbumKey' ,'$totalImages','0','NEW')";
		echo $processedquery;
		mysql_query($processedquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
		return $newAlbum;
	}
	else {
		$processedquery = "UPDATE sspexport SET smugid='" . $smugAlbumID . "' , smugkey='" . $smugAlbumKey . "' WHERE xmlpath='" . $mcXMLPath  . "' totalphotos='" . $totalimages . "' currentphotos='0' status='NEW' WHERE xmlpath='" . $mcXMLPath  . "'";
		echo $processedquery;
		mysql_query($processedquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
		return $newAlbum;
	}
}

//FUNCTION: Check Album
function mcsmugcheckalbum ($xmlpath, $MCXML, $smugObj){
    // Check the number of images in the MC gallery,
    // make sure that matches the # of images we've sent to Smug.

	$resumequery = "SELECT * FROM sspexport WHERE xmlpath = '" . $xmlpath . "'";
	// echo $resumequery;
	$resumeresult = mysql_query($resumequery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
	$errors = array();
	while ($entry = mysql_fetch_array($resumeresult)){
		if($entry['xmlpath'] == $xmlpath){
			$checkID = $entry['smugid'];
			$checkKey = $entry['smugkey'];
			$currentphotos = $entry['currentphotos'];
			$status = $entry['status'];
		}
	}

	echo "Checking if we need to update<br>";
	$albums = $smugObj->albums_getInfo("AlbumID={$checkID}", "AlbumKey={$checkKey}", "Strict=1");
	if ($albums['ImageCount'] != count($MCXML->channel->item)){
		echo "There are " . $albums['ImageCount'] . " images  already in smug. The original MyCapture album has " . count($MCXML->channel->item) . "<br>";
		echo "<h1> Image count does not match or this is a new album</h1><br>";
		$images = $smugObj->images_get("AlbumID={$checkID}", "AlbumKey={$checkKey}");
		$images = ( $smugObj->APIVer == "1.3.0" ) ? $images['Images'] : $images;
		//var_dump($images);
		if ($status == "DONE"){
			foreach ($images as $imgbust){					
				$imgbustID = $imgbust['id'];
				echo "<br>Image " . $counter . " " . $imgbustID;
				//var_dump($imgbust);					
				$albumReset = $smugObj->images_delete("ImageID={$imgbustID}");	
				echo "<br>";
				//var_dump($albumReset);
				}
			}

		else{
			$i = $albums['ImageCount'];
			$statusquery = "UPDATE sspexport SET status = 'IN_PROG' WHERE xmlpath='" . $xmlpath  . "'";
			echo $statusquery;
			mysql_query($statusquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
			while ($i != count($MCXML->channel->item)){
				echo "Starting upload... <br>" . $i;				
				$imageURL = $MCXML->channel->item[$i]->enclosure['url'];
				$imageCaption = $MCXML->channel->item[$i]->Caption;
				echo "Uploading " . $MCXML->channel->item[$i]->enclosure['url'] . "<br>" . $MCXML->channel->item[$i]->Caption . "<br>To " . $checkID . "<br>";
				$imageupload = $smugObj->images_uploadFromURL("AlbumID=${checkID}" , "URL=${imageURL}", "Caption=${imageCaption}");					
				var_dump($imageupload);		
				echo "<br>";			
				$i ++;
				$counterquery = "UPDATE sspexport SET currentphotos='" . $i . "' WHERE xmlpath='" . $xmlpath  . "'";
				mysql_query($counterquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
				flush();
			ob_flush();
			}
} 

			$albums = $smugObj->albums_getInfo("AlbumID={$checkID}", "AlbumKey={$checkKey}", "Strict=1");
			if ($albums['ImageCount'] == $i){
				$statusquery = "UPDATE sspexport SET status = 'DONE' WHERE xmlpath='" . $xmlpath  . "'";
				mysql_query($statusquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
			}
			else{
				$statusquery = "UPDATE sspexport SET status = 'ERROR' WHERE xmlpath='" . $xmlpath  . "'";
				mysql_query($statusquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
				$newError = "The album " .  $albums['Title'] . "(" . $albums['URL'] . ") contains" . $albums['ImageCount'] . " images, but there are " . count($MCXML->channel->item) . " images in the MyCapture XML. Check to see if the images still exist in MyCapture./n";
				array_push($errors, $newError);


			}
		} 



	else {
			echo "<h1> Since the number of images matches no update needed.</h1><br>";
			$statusquery = "UPDATE sspexport SET status = 'DONE' WHERE xmlpath='" . $xmlpath  . "'";
			mysql_query($statusquery) or die('query failed:'.mysql_error().'<br/>sql:'.$sql.'<br/>');
			}

$reportrecipients = "bhenderson@pioneerpress.com";
$reportSubject = date('F j h:i:s');
echo $reportSubject . "<br>";
var_dump($errors);
//mail ($reportrecipients, $reportSubject, $errors[0]);

} 	



function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }
    return false;
}







// ********************************************** 
// THIS IS WHAT WE USED TO RUN WHEN WE WERE IMPORTING MYCAPTURE GALLERIES
// ********************************************** 
if (is_dir($dir) && $dh = opendir($dir)) 
{
    ob_start;
    // ********************************************** 
    // HERE WE:
    // 1. Load each XML file,
    // 2. and make sure we haven't already imported that file before,
    // 3. and then we import it using the mcsmugnewalbum function.
    // ********************************************** 
    while (($file = readdir($dh)) !== false):
        $path = $dir . "/" . $file;
        if ($file == ".DS_Store" || $file == "." || $file == ".." || $file == "processed") { continue; }
        //echo "foldername: " . $file . "\n<br>";
        if (filetype($path) == "file"):
            $xml = simplexml_load_file($path);
            //var_dump($xml);
            $albumsearchquery = "SELECT * FROM sspexport WHERE xmlpath = '" . $path . "'";
            $albumsearch = mysql_query($albumsearchquery)  or die('query failed:'.mysql_error().'<br/>sql:'.$albumsearchquery.'<br/>');
            $searchdebug = mysql_num_rows($albumsearch);
            //echo $searchdebug;
            
            if ($searchdebug != 0)
            {
                //echo "PROGRESS<br>";
                while ($entry = mysql_fetch_array($albumsearch)):
                    if($entry['xmlpath'] == $path){
                        $smugid = $entry['smugid'];
                        $smugkey = $entry['smugkey'];
                        $albumStatus = $entry['status'];
                    }
                endwhile;
            }
            elseif ($searchdebug == 0)
            {
                // WE HAVEN'T TRIED THIS ALBUM YET. LET'S IMPORT IT.
                //echo "maybe progress<br>";				
                $theNewAlbum = mcsmugnewalbum($path, $xml, $categoryID, $f, $siteFileHandle, false);
                $smugid = strval($theNewAlbum['id']);
                $smugkey = $theNewAlbum['Key'];				
            }

            //echo "Log entry exists!<br>ID AND KEY:" . $smugid . "  " . $smugkey . "<br>";
            if ($smugid == 0 || $smugkey == "")
            {
                echo "NEW ALBUM FAILED. RETRYING.";
                $theNewAlbum = mcsmugnewalbum($path, $xml, $categoryID, $f, $siteFileHandle, true);
                $smugid = strval($theNewAlbum['id']);
                $smugkey = $theNewAlbum['Key'];
            }
             
            //mcsmugcheckalbum($smugid, $smugkey, $xml, $f);
            if ($albumStatus != "DONE" && $albumStatus != "ERROR"){mcsmugcheckalbum($path, $xml, $f);}
        endif;
    endwhile;
    closedir($dh);
}

?>
