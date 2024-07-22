=== UiPress Pro ===
Contributors: Mark Ashton
Tags: admin, block builder, dashboard, analytics, dark mode, night mode, customise, ui, modern, dynamic, admin pages, admin theme
Requires at least: 6.0
Tested up to: 6.4.3
Stable tag: 3.4.02
License: GPLv2 or later

A block based visual builder for creating admin side apps, interfaces and themes with WordPress.

== Description ==

UiPress is an all in one solution for tailoring your WordPress admin interactions.
From custom dashboards, profile pages to entire admin frameworks, the uiBuilder can do it all. Pre-made intuitive blocks and a library of professional templates make it super easy to transform the way your site users interact with your  content.

Major features in UiPress Pro include:

* A fast, modern and intuitive block based builder
* Create functional admin pages and ui templates
* Fully responsive templates
* Developer friendly with an extendable API
* Custom forms that can do anything, whether it be sending emails, passing form data to functions or saving the data to site options or user meta, UiPress has you covered.
* Global styles system
* Smart patterns for saving out templates and updating across all your templates
* Over 50+ blocks and counting


== Installation ==

Upload the UiPress plugin to your blog, activate it, and then navigate to the uiBuilder page (admin menu > settings > uiBuilder).

1, 2, 3: You're done!


== Changelog ==

= 3.4.02 =
* Release Date 15 June 2024*

* Fixed issue with menu builder not displaying menu items

= 3.4.01 =
* Release Date 13 June 2024*

* Fixed issue with analytics charts arrow indicators color
* Fixed issue that could cause the menu editor and user management apps to fail when used in the frame with dynamic loading disabled.
* Fixed issue with matomo account switch

= 3.2.2 = 
*Release Date - 11 March 2024*

* Fixed issue that could cause incorrectly called hook warning

= 3.2.09 =
*Release Date - 28 November 2023*

* Fixed image form block name
* Performance improvements with analytic data handling
* Fixed issue that was causing infinite submenus in the menu builder
* Added open in new tab option to the menu builder
* Fixed issue that was causing incorrect decoding

= 3.2.08 =
*Release Date - 21 November 2023*

* Fixed bug with menu creator list where changing roles from the list wouldn't save
* Fixed bug with custom menus not applying on susbites
* Fixed bug with woocommerce charts not showing when not in English language
* Fixed issue preventing the remove from folder option from working

= 3.2.07 =
*Release Date - 15 November 2023*

* Fixed problem with menu builder that could several issues such as inability to edit, change status and delete 

= 3.2.06 =
*Release Date - 11 November 2023*

* Fixed issue with menu creator where dragging a top level item into a sub would drop the submenu
* Fixed issue that could stop certain plugin items' submenus from showing

= 3.2.05 =
*Release Date - 9 November 2023*

* Fixed woocommerce orders map chart

= 3.2.04 =
*Release Date - 9 November 2023*

* Folder toggle state now saves to user prefs
* Fixed issue that could cause apply to subsites option not to show for multisite menus
* Fixed issue with ajax referer check
* Fixed issue with abspath check
* Fixed issue with menu editing permissions
* Fixed issue with folders that could cause them not to show under certain situations
* Fixed issue with google accounts removal
* Fixed several issues with woo blocks

= 3.2.03 =
*Release Date - 31 October 2023*

* Fixed bug that could cause multisite subsites to be inaccessible
* Fixed issue with role application on menu builder

= 3.2.02 =
*Release Date - 31 October 2023*

* Hot fix for a potential fatal on specific server setups

= 3.2.01 =
*Release Date - 31 October 2023*

* Fixed bug in capability search that could throw an error when a cap was blank
* Updated function that returns whether a field is required for form inputs
* Fixed issue with engagement time showing too many digits
* Fixed issue that could cause menus to not show custom links / icons


= 3.2.00 =
*Release Date - 24 October 2023*

* Fixed bug with matomo table block where it could cause errors when no account was connected
* Prepares the plugin for the release of uipress lit 3.3.
* !The following only applies when uipress lite 3.3. or above is installed:
* Rebuilt most of the plugin, moved most server logic into namespaces
* Recoded folders extensions
* Recoded menu builder extension. Droped support for custom admin menus without uiTemplates
* Recoded user management and history logger extension
* Added option to modal to allow closing on page change
* Documented and organised all plugin code
* Performance improvements across the whole plugin

= 3.1.05 =
*Release Date - 2 August 2023*

* Fixed bug with role editor with role redirects - when url contained special characters it would fail to decode them on the front end
* Fixed bug with role editor were cloning role could fail in some situations
* Fixed issue that could throw an error when using the folders extension
* Improved UX of the block conditions and allowed for editing of conditions after they are set
* Added new matomo analytics blocks (charts, tables and map)
* Shortcode block no longer displays actual shortcode code while loading
* Fixed several php 8.3 depreciation notices
* Fixed bug with user management role editor where you are unable to add new caps to a blank role
* Added new chart style options to control style and options of charts
* Fixed issue with folders module in dark mode not correctly showing text colours
* Several admin menu builder bug fixes
* Added fathom analytics blocks
* Added matomo analytics blocks

= 3.1.04 =
*Release Date - 10 July 2023*

* Fixed bug with conditions creator
* Fixed issue with menu builder where the option to apply to subsites was missing
* Performance improvements to folders, user management and menu builder. All should now load about 70% quicker
* Fixed bug with menu creator where custom capabilities were not being applied
* Added new notifications block

= 3.1.013 =
*Release Date - 3 July 2023*

* Fixed bug with role redirects where it was only working on login and not resetting the home page
* Fixed bug with content folders where the option to limit to user was always on
* Fixed issue with text colours on analytic charts
* Fixed issue with charts were they would not respond to dimensions when set
* Fixed an issue where licence key prompt would show up even though it was already registered 
* Fixed an issue where licence key prompt would incorrectly show up on subsites on a multisite environment
* Fixed bug with search block where you were unable to change search post types option

= 3.1.012 =
*Release Date - 29 June 2023*

* Fixed bug with off canvas panels on user management that would cut off data without overflow
* Fixed bug with menu creator with auto update enabled where certain top level and sub level items could be duplicated
* Added the ability to delete all items from a menu list in the menu creator
* Added a list of original menu items to the menu creator so any items can be added again 

= 3.1.01 =
*Release Date - 28 June 2023*

* Fixed issue on menu editor where there was no overflow of the menu items in the editor
* Fixed issue with modal plugin where the style editor wouldn't load
* Fixed issue with folders when minimised where tables did not stretch back to correct width
* Fixed issue with menu builder were custom classes were not being applied
* Fixed bug with menu creator where it would show incorrect modified date

= 3.1.0 =
*Release Date - 20 June 2023*

* Fixed issue with folders module that could cause an error when no folder post types were set
* Performance improvements
* New feature Query builder 
* New feature import / export site settings
* Google analytics now uses dummy data in the builder to prevent exhausted quota issues
* Many other improvements and tweaks

= 3.0.9 =
*Release Date - 04 April 2023*

* Added new user management and history extensions
* Added new folder management for posts and media

= 3.0.8 =
*Release Date - 22 March 2023*

* Fixed issue where frontend toolbars would fail to load
* Added new menu creator feature

= 3.0.7 =
*Release Date - 17 February 2023*

* Fixed issue on multisite where analytics accounts were not syncing correctly
* Added two new site options to limit media and posts / pages to users own only
* Added new woocommerce analytics blocks
* Added new Kanban view block for woocommerce orders
* Added recent orders block for woocommerce

= 3.0.6 =
*Release Date - 2 February 2023*

* Added new analytics maps block
* Added new conditional options for showing / hiding blocks

= 3.0.5 =
*Release Date - 27 January 2023*

* Fixed issue with advanced menu editor where adding submenu items to custom admin pages wasn't possible
* Added new shortcode block
* Stopped the head code option double loading
* Removed version number from user enqueued styles and scripts to prevent potential caching issues
* Fixed issue with keyboard shortcuts for dropdowns / offcanvas panels etc not showing up in block settings
* Fixed issue with translations not loading up

= 3.0.4 =
*Release Date - 9 December 2022 November 2022*

* Added new content navigator block

= 3.0.3 =
*Release Date - 2 December 2022*

* Added option to set login redirect url / homepage on the content page block
* Added new user role editor tool
* Added pro site settings like, enqueue styles, scripts, hide uipress from the plugin table and other options
* Added option to buttons to run custom js code on click
* Added option to hide plugin notices

= 3.0.2 =
*Release Date - 24 November 2022*

* Fixed bug with iframe block where custom links would sometimes not show
* Added user meta block
* Added style block for editing chart canvas

= 3.0.1 =
*Release Date - 22 November 2022*

* Fixed bug where uninstalling / deactivating uipress pro without uipress lite active would cause a critical error and prevent from deactivating
* Added option to advanced menu editor to reset custom menu
* Made changes to negate cache issues when updating
* Added options to advanced menu editor to allow you to set items to open in new tab, outside frame and without uipress

= 3.0.0 =
*Release Date - 17 November 2022*

* First public release of version 3.0.0. A complete rewrite of uipress and it's functionality

