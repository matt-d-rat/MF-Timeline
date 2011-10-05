<?php
/*
Plugin Name: WP Facebook Timeline (MF-Timeline)
Plugin URI: http://www.aplaceformyhead.co.uk/2011/10/05/wp-facebook-timeline-mf-timeline/
Description: Creates a visual linear timeline representation from your Wordpress posts and other media sources in the style of Facebook Profile Timeline.
Version: 1.0.2
Author: Matt Fairbrass
Author URI: http://www.aplaceformyhead.co.uk
License: GPLv2
.
By default the timeline is styled to resemble Facebook's Profile Timeline, but you are free to override the enqueued plugin styles with your own.
.
*/
require('class-mf-timeline.php');

$mf_timeline = new MF_Timeline();
?>