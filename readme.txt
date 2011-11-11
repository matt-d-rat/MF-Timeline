=== WP Facebook Timeline (MF Timeline) ===
Contributors: matt_d_rat, clearbooks
Donate link: http://www.aplaceformyhead.co.uk
Tags: timeline, facebook, twitter, time line, event, stories, story, milestone
Requires at least: 3.1.3
Tested up to: 3.2.1
Stable tag: 1.0

Creates a visual linear timeline representation from your Wordpress posts and other media sources in the style of Facebook Profile Timeline.

== Description ==

Creates a visual linear timeline representation from your Wordpress posts and other media sources in the style of Facebook Profile Timeline. Timeline events can be filtered by taxonomy terms and post types, including custom taxonomies and post types enabled by your active theme.

In addition to Wordpress content, you can also add content to the timeline from Twitter - filtering by multiple hashtags.

Please report all issues or give feedback on the plugin's [Github repository](http://wordpress.org/ "MF Timeline Repository").

== Installation ==

1. Upload the folder `mf-timeline` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Call the plugin through the short code [mf_timeline] or call `<?php echo $mf_timeline->get_timeline(); ?>` in your templates. You are also welcome to instantiate a new object by calling $object = new MF_Timeline();

== Frequently Asked Questions ==

= What is this plugin for? =

This plugin is for displaying data as a visual linear timeline. I developed the code originally for my work at Clear Books (http://www.clearbooks.co.uk) but decided to develop it some more and turn it into a Wordpress plugin for everyone to use.

= Can I change the styling of the Timeline? =

By default the plugin is designed to look like Facebook's Profile Timeline - since this is wear I drew my original inspiration from for this plugin, but you are free to style it as you please. The plugin's stylesheets should be enqueued before your theme's stylesheets so simply writing your own CSS should be enough to overwrite the default styles. Alernatively you can always dequeue the plugin's styles by calling `<?php wp_dequeue_style( 'mf_timeline_styles' ) ?>`

= Where can I report an issue or give feedback on the plugin? =

Please direct all issues and feedback to the plugin's [Github repository](http://wordpress.org/ "MF Timeline Repository") page which can be found here:

= Do you plan to add any other content sources to the plugin? =

Yes I plan to add a variety of different sources to the plugin in future releases including:

* Flickr
* Youtube
* Stories
* Facebook

If you have any other ideas for sources I can make available to the timeline then please share them with me.

== Screenshots ==

1. MF-Timeline output with default "Facebook style" stylesheets applied, pulling content from Twitter and Wordpress.

== Changelog ==

= 1.0 =
* Initial release of MF Timeline.
* Supports content from Wordpress and Twitter.
* Wordpress content can be filtered by multiple post types and taxonomies (including custom).
* Twitter content can be filtered by multiple hashtags.

== Upgrade Notice ==
A new version of MF-Timeline is available. Upgrade to the latest version.

== Arbitrary section ==
