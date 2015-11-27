=== UmanIT Update URLs ===
Contributors: vrobic
Tags: migration, domain, permalinks, url, links,
Requires at least: 3.0.1
Tested up to: 3.5.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Updates all URLs in the database after moving your site from one domain to another.

== Description ==

This plugin updates all URLs in the database after moving your site from one domain to another. It is useful for switching between development, testing and production stages.

Why this plugin ? WordPress stores absolute URLs in :

- posts (including pages and custom post types), drafts
- excerpts
- medias
- custom fields
- options (including 'siteurl' and 'home', in wp_options table)
- GUIDs (only for development)

When moving your website to a new domain, all these links will point to your old domain name and become outdated.

== Installation ==

Installation and uninstallation are extremely simple. You can use WordPress' automatic install or follow the manual instructions below.

= Installation =

1. Download the package.
2. Extract it to the "plugins" folder of your WordPress directory.
3. In the Administration Panel, go to "Plugins" and activate it.
4. Go to "Tools", then "Update URLs" to use it.

= Uninstallation =

1. In the Administration Panel, go to "Plugins" and deactivate the plugin.
2. Go to the "plugins" folder of your WordPress directory and delete the files/folder for this plugin.

= Usage =

Once the plugin has been activated, navigate to "Tools", then "Update URLs" and follow the instructions.

== Frequently Asked Questions ==

= Does it support multisite installation? =

Yes, you only have to run the plugin on each site.

= What about serialized data? =

The plugin handles it in options and postmeta tables.

= Why are the URLs not updated? =

URLs are only replaced when an exact match is found. Note that maching is case-sensitive. Be sure that you have entered the correct URL.

= Why URLs in other plugins are not updated? =

Some plugins store their content and settings in custom tables. Since every plugin's data structure can be different, we will not be able to update these tables, although it might contain URLs.

= Can I choose which links to update? =

Yes, you can choose whether to update links for posts, excerpts, medias, custom fields, options and GUIDs.

== Screenshots ==

1. The plugin's screen

== Changelog ==

= 1.0 =
First stable version.

== Prerequisite ==

Before moving the website, consider making a backup of the database. Then, once the site has been moved, you will need to manually update 'siteurl' in the 'wp_options' table, in order to access the Wordpress Administration panel.
For further information, see http://codex.wordpress.org/Changing_The_Site_URL
