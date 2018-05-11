=== Fix Media Library ===
Contributors: wowpresshost
Donate link: https://wowpress.host/plugins/fix-media-library/
Tags: media library, attachments, thumbnail, thumbnails, post thumbnail, post thumbnails, clean, images
Requires PHP: 5.3
Requires at least: 4.6
Tested up to: 4.9.5
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Fix Media Library inconsistency between database and wp-content/uploads folder contents.
Fix missing attachments metadata (_wp_attachment_metadata).
Regenerate thumbnails according to actual settings and identify/remove unused image files.

== Description ==

Fix Media Library fixes inconsistency between wp-content/uploads folder and
database.
Fixes corrupted Media Library database records.
Designed to run smoothly against huge Media Libraries containing hundreds of thousands of images.

Useful when:

* Really old database is used and there are a lot of problems with Media Library found
* New thumbnail sizes are registered
* Some thumbnail sizes are not used anymore (theme change, upgrade), but image files are still exists
* There are Media Library entries present pointing to image files that don't exist anymore
* Some entries in Media Library don't show images, while those are present (_wp_attachment_metadata meta field corrupted)
* There are a lot of images in wp-content/uploads folder that are no longer used
* There are duplicate attachments pointing to the same image file
* You want to update attachments GUID fields containing old/staging urls

At [WowPress.host](https://wowpress.host/) company we regularly migrate very old databases and clean it up to make sure website using it are running smoothly. Of course those have all different kinds of inconsistencies collected during years or even decades, and Media Library is the most common problematic piece of data here.
That plugin helps to solve most common problems related to Media Library data.

We use a lot of open-source tools in our work, and therefore decided publish our own tools so that those can be used by the community too.

= Need Help? Found A Bug? Want To Contribute Code? =

Support for this plugin is provided via the [WordPress.org forums](https://wordpress.org/support/plugin/wow-media-library-fix).

The source code for this plugin is available on [GitHub](https://github.com/wowpress-host/wow-media-library-fix).

Paid support at [WowPress.host](https://wowpress.host/professional-services/).

== Installation ==

1. Go to your admin area and select Plugins → Add New from the menu.
2. Search for "Fix Media Library".
3. Click install.
4. Click activate.
5. Navigate to Tools → Fix Media Library.

== Screenshots ==

1. Configuration page
2. Progress running

== Upgrade Notice ==

Nothing to care about

== ChangeLog ==

= Version 1.0 =

Initial release.
