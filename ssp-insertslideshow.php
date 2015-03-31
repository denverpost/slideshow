<?php
/*
Plugin Name: Slideshowpro Insert Album SMUGMUG EDITION
Plugin URI:
Version: v1.00
Author: mateo leyba
Description: Take a SSP shortcode, look up its smugmug equivalent and load the smugmug gallery.
Copyright 2009 mateo leyba  (email : mleyba [a t ] denverpost DOT com)
*/

function sspx_init() {
        $hasSlideShow = true;
        function addShowjava($atts) {
                //example of user input, this is what you put in your post body. [insertslideshowjava xml="77583397001" api="reverb, captured, dp, seen"]
                extract(shortcode_atts(array(
                        'xml' => 'no id'
                ), $atts));
                $album_id = explode("=", $xml);
                // The album id is the second element in $album_id.
                // We use the lookup file to figure out the SMUGMUG url
                include('lookup.php');
                $smugdata = $ssplookup[$album_id[1]];
                $id = get_the_ID();
                add_post_meta($id, 'smugdata', $smugdata, TRUE);
	$dir = plugin_dir_path(__FILE__);
	$JSDeps = array(
		'js/carousel.js',
		'js/smart-resize.js',
		'js/bootstrap-transition.js',
		'js/handlebars.min.js',
		'js/imagesLoaded.js',
		'js/custom/JSON-helper.js',
		'js/custom/carousel-bindings.js'
	);
	$CSSDeps = array(
		'css/gallery-styles.css',
		'css/custom/gallery-styles.css'
	);
	$HTMLAsPHPInclude = array(
		'gallery-html-includes/gallery-js-and-html.php'
	);
	$JSONDir = $dir . 'js/json/'; // Needs to be able to be written to

	$gallery = new DFMGallery();
	$gallery->setJSDeps($JSDeps);
	$gallery->setCSSDeps($CSSDeps);
	$gallery->setHTML($HTMLAsPHPInclude);
	$gallery->setJSONDir($JSONDir);
	//$gallery->setMeta(true);
	//$gallery->setAPCCacheBusting(false);
	return $gallery->createDFMGallery();
        }
add_shortcode('insertSlideshowjava', 'addshowjava');
}
add_action('plugins_loaded', 'sspx_init');
?>
