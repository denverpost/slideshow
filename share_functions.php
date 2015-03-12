<?php
/**
 * Plugin Name: Shared Functions
 * Description: This plugin contains FUNCTIONS that are shared between all themes. There is an includes directory that contains any other files that can be required by any function. Keep additions in that directory. Also note variable scope, best to return pass variables to functions as arguments and RETURN values at end of function.
 * Author: Chris Johnson
 * Version: 0.1.0
 */

/* Place custom code below this line. */
//smugmug api class
include_once( plugin_dir_path( __FILE__ ) . '/includes/phpSmug.php');
include_once( plugin_dir_path( __FILE__ ) . '/includes/phpSmugAPCcache.php');

//Need this plugin for the site to work. Needs to be activated for the relevant DB to be created. 
require_once( WP_PLUGIN_DIR . '/dfm-wp-data/dfm-wp-data.php');


function setSSPcodes( $api ) {
    require(dirname(__FILE__) . '/includes/sspcodes.php');
    return $director;
}
//add_shortcode('setSSPcodes', 'setSSPcodes');
//add_shortcode('customFunctionRocks', 'customFunctionRocks');
//add_action('setSSPcodes', 'setSSPcodes');
//$api = 'denverpost';
//getSmugAPI($api);

// get a galleries smug mug api key information
function getSmugApi($smugdata){
        $getTheHost = parse_url($smugdata);
        //var_dump($getTheHost["host"]);
        $dataForThisHost = DFMDataForWP::retrieveRowFromMasterData('smug_url', $getTheHost["host"]); // Row of all data for row that contains this smugmug host
        //var_dump($dataForThisHost);
        $smugvalues = array("smug_api_key" => $dataForThisHost["smug_api_key"], "smug_secret" => $dataForThisHost["smug_secret"], "smug_token" => $dataForThisHost["smug_token"]);
        return array ($smugvalues);
}


function getSmugThumb($smugthumb,$rotator=false){
    if (isset($smugthumb) ) {
        //parse smugthumb which is coming from the custom field in the post so we can get the AlbumID and AlbumKey
        $pq = parse_url($smugthumb, PHP_URL_QUERY);
        $qatts = array();
        parse_str($pq, $qatts);
    }
     $smugvalues = getSmugApi($smugthumb);//returns smug values like APIKey and OAuth secret specific to this smug instance.
    $tokenarray = unserialize($smugvalues[0]['smug_token']);//setup the token value from smugvalues so we can use it as an array
    $cachevart = plugin_dir_path( __FILE__ ) . 'includes/smugcache';
    //include_once( plugin_dir_path( __FILE__ ) . '/includes/phpSmug.php'); THIS LOADS AT THE TOP OF SHARED FUNCTIONS!
	$f = new phpSmug("APIKey={$smugvalues[0]['smug_api_key']}", "AppName=DFM Photo Gallery 1.0", "OAuthSecret={$smugvalues[0]['smug_secret']}", "APIVer=1.3.0");
    $f->setToken( "id={$tokenarray['Token']['id']}", "Secret={$tokenarray['Token']['Secret']}" );
    $f->enableCache("type=apc", "cache_dir={$cachevart}", "cache_expire=180" );
	if ($f->APIKey){//Some error handling
		if ($rotator){
			if ($rotator == 'large'){
				$images = $f->images_get("AlbumID={$qatts["AlbumID"]}", "AlbumKey={$qatts["AlbumKey"]}", "CustomSize=471x471", "Heavy=0" );
			}
			elseif ($rotator == 'small'){
				$images = $f->images_get("AlbumID={$qatts["AlbumID"]}", "AlbumKey={$qatts["AlbumKey"]}", "CustomSize=152x152", "Heavy=0" );
			}
			elseif ($rotator === 'responsive') {
				$images = $f->images_get("AlbumID={$qatts["AlbumID"]}", "AlbumKey={$qatts["AlbumKey"]}", "Heavy=1" );
			}
			else {
				$images = $f->images_get("AlbumID={$qatts["AlbumID"]}", "AlbumKey={$qatts["AlbumKey"]}", "CustomSize=400x400", "Heavy=0" );
			}
		}
		else {
			$images = $f->images_get("AlbumID={$qatts["AlbumID"]}", "AlbumKey={$qatts["AlbumKey"]}", "Heavy=1" );
		}
		if(!$f->error_code){
			$images = ( $f->APIVer == "1.3.0" ) ? $images['Images'] : $images;
		}		
		else $images = NULL;
	}
	else $images = NULL;
    return $images;
}

if (!function_exists('getMCSiteName')):
    function getMCSiteName(){
  // Get the site name as it was entered into the WordPress admin for the multinetwork setup
  $siteInfo = get_blog_details($GLOBALS['blog_id']);
  return str_replace('/', '', $siteInfo->path);
}
endif;

function DetermineParentCompany($existingconfig) {
    $siteName = getMCSiteName();
		if (!$siteName) {
			$siteName = 'mercurynews';
		}
    //var_dump($siteName);
    //check if site array config passed to the function is valid for the site we are currently on. If it's different swictch to the proper array confi 9-20-12 -mateo
    if ($existingconfig['wp_site_name'] === $siteName) {
        return false;
    }
    else {
        $config = DFMDataForWP::retrieveRowFromMasterData('wp_site_name', $siteName); // Row of all data for this site
        //var_dump($config);
        $company = strtolower($config['company']);
        $company = ($company === 'mng') ? 'mngi' : $company; // Currently the company is "MNG" in the site data file, but we need "mngi"
        $_SESSION['parent_company'] = $company; //legacy var to id mng or jrc property 9-19-12 - mateo
        $_SESSION['apt_leader_path'] = $company . '_apt_leader.html';//legacy var to use mng or jrc style ad tags 9-19-12 - mateo
        $_SESSION['siteconfig'] = $config;//the master var that is an array of all the config values a site may need, including the above which we should update someday 9-19-12 - mateo
        return $config;
    }
}

//--------------------the following are older functions pulled from theme function.php files

//----test function
function customFunctionRocks( $xtext ) {
    die('you did it! '. $xtext );
}
//not sure we need to add these below, seems to work without them
//add_shortcode('customFunctionRocks', 'customFunctionRocks');
//add_action('plugins_loaded', 'sspx_init');

/* Place custom code above this line. */

function createCanonical($url){ 
    // Returns a canonical url based on data from the master spreadsheet file
    $matches = array();
    if (preg_match('/dfm-ssp\.medianewsgroup\.com\/([A-Za-z0-9\-\_]+)/', $url, $matches)){
        $siteIdentifier = $matches[1];
        $replace = $matches[0];
        if ($siteIdentifier) {
            // This is how to do this with new DFM site table data and query functions
            // Will move include once using elsewhere 
            $syndicatedFromMC = DFMDataForWP::retrieveValueFromMasterData('media_center_url', 'wp_site_name', $siteIdentifier);
            $syndicatedFromMC = str_replace(array('http://', '/'), '', $syndicatedFromMC);
            if ($syndicatedFromMC) {
                $url = str_replace($replace, $syndicatedFromMC, $url);
            } 
        }
    }
    return '<link rel="canonical" href="' . $url  . '" />';
}

//Add Spreed RSS feed template
function create_my_customfeed() {
    load_template( TEMPLATEPATH . '/feed-spreed.php'); 
}
add_action('do_feed_spreed', 'create_my_customfeed', 10, 0);


// Add custom button to html editor to insert [insertSmugmug] code
// Add buttons to html editor, remember this is JS you are loading so you must do it properly with wp_enqueue_script
function mc_quicktags($hook) {
    if( $hook != 'post.php' && $hook != 'post-new.php' )//only return when it's a new post or editing an existing post.
        return;
    wp_register_script( 'quicktag-script', plugins_url( '/includes/qtags.js', __FILE__ ), array(), null, true );  
    wp_enqueue_script( 'quicktag-script' );
}
add_action( 'admin_enqueue_scripts', 'mc_quicktags' );

/* Function to output JSON for embedded galleries */
function outputEmbedJson($post_object, $js_object_name){
	$feed_data = addsmugmug($att='',$feed=true); // Function that hits Smug Mug API and returns album and image data
	if ($feed_data['albums'] && $feed_data['images']){
		$image_json; // String with the majority of the data
		$gallery_title = json_encode($post_object->post_title); // Post title
		$gallery_id = json_encode($feed_data['albums']['id']); // Smug gallery id
		$gallery_desc = json_encode($feed_data['albums']['Description']); // Smug gallery description
		// Prepare image data for JSON output
		foreach($feed_data['images'] as $image){
			$image_json .= '{"thumbUrl" : ' . json_encode($image['CustomURL']) . ', "fullUrl" : ' . json_encode($image['LargeURL']) . ', "caption" : ' . json_encode($image['Caption'])  . ', "id" : ' . json_encode($image['id']) . '},';
		}
		$image_json = '[' . trim($image_json, ',') . ']'; // Wrap the js objects so they are an array (and remove trailing comma from loop above)
		$json = $js_object_name . '.createGallery({ "imageElementCollection" : ' . $image_json . ', "title" : ' . $gallery_title . ', "id" : ' . $gallery_id . ', "description" : ' . $gallery_desc . '});';// JSON wrapped in js callback function
	}
	else $json = $js_object_name . '.createGallery({"error" : "Missing data."});';
	echo $json;
}


// Some functions for Omniture vars
// Partially copied from mcenter header

function parent_of_cat($category) {
	// Moved from functions.php so it wasn't in both themes
	$cat_tree = get_category_parents($category[0]->term_id, FALSE, ':', TRUE);
	$top_cat = split(':',$cat_tree);
	$parent = $top_cat[0];
	if (is_string($cat_tree)) {
		$top_cat = preg_split('/:/',$cat_tree);
		$parent = $top_cat[0];
		return $parent;
	}
	else {
		return NULL;
	}
}

if (!function_exists('getmy_tag')): 
function getmy_tag($posttags){
	// Moved from functions.php so it wasn't in both themes
	$count=0;
	if ($posttags) {
		foreach($posttags as $tag) {
			$count++;
			if (1 == $count) {
				$this_tag = str_replace(' ', '_',$tag->name);
				return $this_tag;
			}
		}
	}
	return NULL;
}
endif;

function omniTitle() {
	if (is_home()) { 
		return 'home';
	}
	return preg_replace('/[^a-z0-9_]/i', '_', trim(wp_title('', false)));
}

function omniCat() {
	if (is_home()) {
		$thePageCat = 'home';
		return $thePageCat;
	}
	elseif ($category = get_the_category()) {
		return parent_of_cat($category);
	}
	return NULL;
}

function omniTag() {
	if (is_home()) {
		return 'home';
	}
	elseif (is_archive()) {
		return 'archive_galleries';
	}
	elseif (is_search()) {
		return 'search_galleries';
	}
	elseif (is_singular() && $postTags = get_the_tags()) { 
		return getmy_tag($postTags);
	}
	return NULL;
}
