=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, alexsanford1, annezazu, cena, chaselivingston, csonnek, davor.altman, donnapep, donncha, drawmyface, erania-pinnera, fjorgemota, jacobshere, jakeom, jeherve, jenhooks, jgs, jonryan, kraftbj, lamdayap, lschuyler, macmanx, nancythanki, orangesareorange, rachelsquirrel, renathoc, ryancowles, richardmtl, scarstocea
Tags: jobs, careers, company, hiring, job board
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 2.4.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create a careers page for your company website, or build a public job board for your community. 

== Description ==

WP Job Manager is a **lightweight** job listing plugin for adding job board functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage, and categorize job listings using the familiar WordPress UI.
* Searchable & filterable ajax powered job listings added to your pages via shortcodes.
* Frontend forms for guests and registered users to submit & manage job listings.
* Allow job listers to preview their listing before it goes live. The preview matches the appearance of a live job listing.
* Each listing can be tied to an email or website address so that job seekers can apply to the jobs.
* Searches also display RSS links to allow job seekers to be alerted to new jobs matching their search.
* Allow logged in employers to view, edit, mark filled, or delete their active job listings.
* Job statistics for employers about job listing views and search impressions.
* Developer friendly code - Custom post types, endpoints & template files.

The plugin comes with several shortcodes to output jobs in various formats, and since its built with Custom Post Types you are free to extend it further through themes.

[Read more about WP Job Manager](https://wpjobmanager.com/).

= Documentation =

Documentation for the core plugin and extensions can be found [on the docs site here](https://wpjobmanager.com/documentation/). Please take a look before requesting support because it covers all frequently asked questions!

= Demo =

For a real-life example site, check out [jobs.blog](https://jobs.blog), built by the WP Job Manager team! To try out the plugin in an expendable demo site, click the Live Preview button above.

= Extensions =

The core WP Job Manager plugin is free and always will be. It covers all functionality we consider 'core' to running a simple job board site.

Additional, advanced functionality is available through extensions. Not only do these extend the usefulness of the core plugin, they also help fund the development and support of core.

You can browse available extensions after installing the plugin by going to `Job Manager > Marketplace`. Our popular extensions include:

**[Applications](https://wpjobmanager.com/add-ons/applications/)**

Allow candidates to apply to jobs using a form & employers to view and manage the applications from their job dashboard.

**[WooCommerce Paid Listings](https://wpjobmanager.com/add-ons/wc-paid-listings/)**

Paid listing functionality powered by WooCommerce. Create custom job packages which can be purchased or redeemed during job submission. Requires the WooCommerce plugin.

**[Resume Manager](https://wpjobmanager.com/add-ons/resume-manager/)**

Resume Manager is a plugin built on top of WP Job Manager which adds a resume submission form to your site and resume listings, all manageable from WordPress admin.

**[Job Alerts](https://wpjobmanager.com/add-ons/job-alerts/)**

Allow registered users to save their job searches and create alerts which send new jobs via email daily, weekly or fortnightly.

**[Job Manager Pro Bundle](https://wpjobmanager.com/add-ons/bundle/)**

You can get the above extensions and several others at discount with our [WPJM Pro Bundle](https://wpjobmanager.com/add-ons/bundle/). Take a look!

= Contributing and reporting bugs =

You can contribute code to this plugin via GitHub: [https://github.com/Automattic/WP-Job-Manager](https://github.com/Automattic/WP-Job-Manager) and localizations via [https://translate.wordpress.org/projects/wp-plugins/wp-job-manager](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager)

Thanks to all of our contributors.

= Support =

Use the WordPress.org forums for community support where we try to help all users. If you spot a bug, you can log it (or fix it) on [Github](https://github.com/Automattic/WP-Job-Manager) where we can act upon them more efficiently.

If you need help with one of our extensions, [please raise a ticket in our help desk](https://wpjobmanager.com/support/).

If you want help with a customization, please consider hiring a developer! [http://jobs.wordpress.net/](http://jobs.wordpress.net/) is a good place to start.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic install, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "WP Job Manager" and click Search Plugins. Once you've found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by clicking _Install Now_.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your web server via your favorite FTP application.

* Download the plugin file to your computer and unzip it
* Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.
* Activate the plugin from the Plugins menu within the WordPress admin.

= Getting started =

Once installed:

1. Create a page called "jobs" and inside place the `[jobs]` shortcode. This will list your jobs.
2. Create a page called "submit job" and inside place the `[submit_job_form]` shortcode if you want front-end submissions.
3. Create a page called "job dashboard" and inside place the `[job_dashboard]` shortcode for logged in users to manage their listings.

**Note when using shortcodes**, if the content looks blown up/spaced out/poorly styled, edit your page and above the visual editor click on the 'text' tab. Then remove any 'pre' or 'code' tags wrapping your shortcode.

For more information, [read the documentation](https://wpjobmanager.com/documentation/).

== Frequently Asked Questions ==

= How do I setup WP Job Manager? =
View the getting [installation](https://wpjobmanager.com/document/installation/) and [setup](https://wpjobmanager.com/document/setting-up-wp-job-manager/) guide for advice getting started with the plugin. In most cases it's just a case of adding some shortcodes to your pages!

= Can I use WP Job Manager without frontend job submission? =
Yes! If you don't setup the [submit_job_form] shortcode, you can just post from the admin backend.

= How can I customize the job application process? =
There are several ways to customize the job application process in WP Job Manager, including using some extra plugins (some are free on Wordpress.org).

See: [Customizing the Job Application Process](https://wpjobmanager.com/document/customising-job-application-process/)

= How can I customize the job submission form? =
There are three ways to customize the fields in WP Job Manager;

1. For simple text changes, using a localisation file or a plugin such as https://wordpress.org/plugins/say-what/
2. For field changes, or adding new fields, using functions/filters inside your theme's functions.php file: [https://wpjobmanager.com/document/editing-job-submission-fields/](https://wpjobmanager.com/document/editing-job-submission-fields/)
3. Use a 3rd party plugin such as [https://plugins.smyl.es/wp-job-manager-field-editor/](https://plugins.smyl.es/wp-job-manager-field-editor/?in=1) which has a UI for field editing.

If you'd like to learn about WordPress filters, here is a great place to start: [https://pippinsplugins.com/a-quick-introduction-to-using-filters/](https://pippinsplugins.com/a-quick-introduction-to-using-filters/)

= How can I be notified of new jobs via email? =
If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

= What language files are available? =
You can view (and contribute) translations via the [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager).

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

### 2.4.0 - 2024-08-08
* Fix job dashboard actions menu in Safari
* Fix PHP 8.3 support
* Remove support for Internet Explorer 11
* Fix Wordpress 6.6 compatibility
* Fix classic editor support for job listings

### 2.3.0 - 2024-04-29
New!

* Job Statistics — enable insights like job listing page views, unique visits and search impressions to be collected and displayed to employers in the jobs dashboard.
* Add Google reCAPTCHA v3 support

Improvements: 

* New: Job statistics overlay
* Change: Redesign job dashboard
* Change: Allow job duplication in the job dashboard for any job 
* Security: Don't return unpublished jobs only in the promote job endpoint
* Fix renewals for WordPress.com licenses
* Fix issues with rich e-mails on some e-mail providers
* Fix e-mail styling in some e-mail clients  
* Fix expiry date not showing up in backend editor
* Fix: Add fallback to date format in case it's missing
* Fix: Prevent past dates from being used in the datepicker

For developers:

* Add filter to disable promoted jobs
* Add placeholder options to select field
* Job dashboard template has been rewritten

### 2.2.2 - 2024-02-15
* Fix issue with rich e-mails on some e-mail providers (#2753)
* Fix: 'featured_first' argument now works when 'show_filters' is set to false.
* Improve checkbox and radio inputs for styled forms

### 2.2.1 - 2024-01-31
* Fix PHP 7.x error for mixed returned type (#2726)

### 2.2.0 - 2024-01-29
New:

* Allow scheduling listings during job submission — add an option to show a 'Scheduled Date' field in the job submission form
* Add new [jobs] shortcode parameter, featured_first so you can ensure featured listings always show up on top.
* Add support for user sessions without a full account (used in the Job Alerts extension)

Changes:

* Improve styling for rich text e-mails
* Include plain text alternative for rich text e-mails for better compatibility
* Store previous license when plugin is deactivated for easier reactivation later.
* Update design for settings and marketplace pages

Fixes:

* Fix custom role permission issues (#2673)
* Fix RSS, Reset, Add Alert links not showing on search page without a keyword
* Improve PHP 8 support
* Fix numeric settings field issues
* Improve e-mail formatting and encoding, remove extra whitespace
* Add file type validation and error message to company logo upload
* Fix cache issue when marking jobs as filled/not filled via bulk actions
* Do not emit warning when user with insufficient access to Job Manager menu tries to access wp-admin

