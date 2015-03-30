<?php
/*
Plugin Name: Slideshowpro Insert Album
Plugin URI:
Version: v1.00
Author: mateo leyba
Description: Dynamicaly passes Slideshow Pro Director CMS gallery to a pre made swf/slideshow
Copyright 2009 mateo leyba  (email : mleyba [a t ] denverpost DOT com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

#if you need to load stuff in the wordpress header
function ssp_headfies() {

//css in case you need to mess with the slideshow div
//echo '<link rel="stylesheet" type="text/css" href="' . get_settings('siteurl') . '/wp-content/plugins/ssp-slideshow/css/ssp-insertslideshow.css" media="screen" />';
}

add_action('wp_head', 'ssp_headfies');

function sspx_init() {
        $hasSlideShow = true;
        function addShowjava($atts) {
                //example of user input, this is what you put in your post body. [insertslideshowjava xml="77583397001" api="reverb, captured, dp, seen"]
                extract(shortcode_atts(array(
                        'xml' => 'no id'
                ), $atts));
                $album_id = explode("=", $xml);
                /*---------------Start Director Setup ------------------------*/
            # Include DirectorAPI class file
                # and create a new instance of the class
                # Be sure to have entered your API key and path in the DirectorPHP.php file.

                include('http://www.heyreverb.com/wp-content/plugins/ssp-slideshow/classes/DirectorPHP.php');
        $director = new Director('hosted-adsfasdfasdfasdfsd', 'reverb.slideshowpro.com');
                //echo('Connected!');

                # When your application is live, it is a good idea to enable caching.
                # You need to provide a string specific to this page and a time limit
                # for the cache. Note that in most cases, Director will be able to ping
                # back to clear the cache for you after a change is made, so don't be
                # afraid to set the time limit to a high number.

                $director->cache->set('thisisreverb01', '+30 minutes');

                # What sizes do we want?
                $director->format->add(array('name' => 'thumb', 'width' => '60', 'height' => '60', 'crop' => 1, 'quality' => 75, 'sharpening' => 1));
                $director->format->add(array('name' => 'large', 'width' => '640', 'height' => '403', 'crop' => 0, 'quality' => 95, 'sharpening' => 1));

                # Make API call using get_album method. Replace "1" with the numerical ID for your album
                $album = $director->album->get($album_id[1]);

                # Set images variable for easy access
                $contents = $album->contents[0];
                $total_images = count($contents);
                ob_start(); ?>

                <!-- Start Advanced Gallery Html Containers -->
            <div class="clearfloat"></div>
                <div id="gallery" class="content">
                        <div class="slideshow-container">
                                <div id="loading" class="loader"></div>
                                <div id="slideshow" class="slideshow"></div>
                        </div>
                        <div id="caption" class="caption-container"></div>
                <div id="controls" class="controls"></div>
                </div>
                <div class="clearfloat"></div>
            <div class="navcontainer">
                        <div id="thumbs" class="navigation">
                                <ul class="thumbs noscript">
                        <?php foreach ($contents as $image): ?>
                                        <li>
                                                <a class="thumb" name="name here" href="<?php echo $image->large->url ?>" title="">
                                <img src="<?php echo $image->thumb->url ?>" width="<?php echo $image->thumb->width ?>" height="<?php echo $image->thumb->height ?>" alt="" />
                                </a>
                                <div class="caption">
                                                        <div class="image-title"><?php echo $image->seq . " of " . $total_images ?>  </div><div class="clear"></div>
                                                        <div class="image-desc"><?php echo $image->caption ?></div><div class="clear"></div>
                                                </div>
                             </li>
                                <?php endforeach ?>
                                </ul>
                        </div>
                </div>
                <div class="clearfloat"></div>
                <!-- End Advanced Gallery Html Containers -->
        <?php
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
        }
add_shortcode('insertSlideshowjava', 'addshowjava');
}
add_action('plugins_loaded', 'sspx_init');
?>
