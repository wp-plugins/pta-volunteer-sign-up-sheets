=== PTA Volunteer Sign Up Sheets ===
Contributors: DBAR Productions
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=R4HF689YQ9DEE
Tags: Volunteer,Sign Up, Events
Requires at least: 3.3
Tested up to: 3.8.1
Stable tag: 1.4.4

Easily create and manage sign-up sheets for volunteer activities, while protecting the privacy of the volunteers' personal information.

== Description ==

This plugin allows you to easily create and manage volunteer sign up sheets for your school or organization. You can define four different types of events:  Single, Recurring, Multi-Day, or Ongoing events. Single events are for events that take place on just a single date. Recurring events are events that happen more than once (such as a weekly function), but have the same needs for each date. Multi-Day events are events that are spread across more than one day, but have different needs for each day. Ongoing events do not have any dates associated with them, but are for committees or helpers that are needed on an ongoing basis.

For each of these types of events, you can create as many tasks or items as needed. For each of these tasks/items, you can specify how many items or people are needed, a start and end time, the date (for multi-day events), and whether or not item details are needed (for example, if you want the volunteer to enter the type of dish they are bringing for a luncheon). The order of tasks/items can easily be sorted by drag and drop.

Each sign-up sheet can be set to visible or hidden, so that you can create sign-up sheets ahead of time, but only make them visible to the public when you are ready for them to start signing up. There is also a test mode which will only show sign-up sheets on the public side to admin users or those who you give the "manage_signup_sheets" capability. Everyone else will see a message of your choosing while you are in test mode. When not in test mode, admins and users with the "manage_signup_sheets" capability can still view hidden sheets on the public side (for testing those sheets without putting the whole system into test mode).

In the settings, you can choose to require that users be logged in to view or sign-up for any volunteer sign-up sheets, and pick the message they will see if they are not logged in. Even if you keep the sheets open to the public, no personal information is shown for any slots that have already been filled. Only a first name and last initial is shown for filled slots, along with any item descriptions. Contact info is only shown on the admin side.  Version 1.3 adds an option to simply show "Filled" for filled spots.

There is also a hidden spambot field to prevent signup form submission from spambots.

If a user is logged in when they sign up, the system will keep track of the user ID, and on the main volunteer sign-ups page, they will also see a list of items/tasks that they have signed up for, and it will give them a link to clear each sign up if they need to cancel or reschedule. If they are not logged in when they sign up, but they use the same email as a registered user, that sign-up will be linked to that user's account.

There is a shortcode for a main sign-up sheet page that will show a list of all active (and non-hidden) sign-up sheets, showing the number of open volunteer slots with links to view each individual sheet. Individual sheets have links next to each open task/item for signing up.  When signing up, if the user is already logged in, their name and contact info (phone and email) will be pre-filled in the sign-up page form if that info exists in the user's meta data. You can also enter shortcode arguments to display a specific sign-up sheet on any page.

There is a sidebar widget to show upcoming volunteer events and how many spots still need to be filled for each, linked to each individual sign-up sheet. You can choose whether or not to show Ongoing type events in the widget, and if they should be at the top or bottom of the list (since they don't have dates associated with them).

Admin users can view sign-ups for each sheet, and clear any spots with a simple link. Each sheet can also be exported to a CSV format file for easy import into Excel or other spreadsheet programs.

Committee/Event contact info can be entered for each sheet, or, if you are using the PTA Member Directory plugin, you can select one of the positions from the directory as the contact. When a user signs up, a confirmation email is sent to the user as well as a notification email to the contacts for that event.

Automatic Email reminders can be set for each sign-up sheet. You can specify the number of days before the event to send the reminder emails, and there can be two sets of reminders for each sheet (for example, first reminder can be sent 7 days before the event, and the second reminder can be sent the day before the event). You can set an hourly limit for reminder emails in case your hosting account limits the number of outgoing emails per hour.

Simple to use custom email templates for sign-up confirmations and reminders.

Features:

*   Easily create volunteer sign-up sheets with unlimited number of tasks/items for each
*	Supports Single, Recurring, Ongoing or Multi-Day Events
*  	All Sheets can be hidden from the public (visible only to logged in users)   
*   No volunteer last names or contact info are shown to the public. Default public view shows only first name and last name for filled spots.  Version 1.3 introduces an option to simply show "Filled" for filled spots.
*   Hidden spambot field helps prevent automatic spambot form submissions
*	Up to 2 automatic reminder emails can be set up at individually specified intervals for each sheet (e.g., 7 days and 1 day before event)
*   Shortcodes for all sheets, or use argument to show a specific sheet on a page
*   Widget to show upcoming events that need volunteers in page sidebars
*   CSV Export for each sheet on admin side
*   Individual sheets can be set to hidden until you are ready to have people sign up (useful for testing individual sheets)
*   Test Mode for entire volunteer system, which displays a message of your choosing to the public while you test the system
*   "manage_signup_sheets" capability so you can set up other users who can create and manage sign-up sheets without giving them full admin level access.
*	Integration with the PTA Member Directory & Contact Form plugin to quickly specify contacts for each sign-up sheet, linked to the contact form with the proper recipient already selected. http://wordpress.org/plugins/pta-member-directory/
*	Use the free PTA Zilla Shortcodes extension to easily generate shortcodes with all possible arguments ( available at https://stephensherrardplugins.com )
*	Version 1.4 adds Wordpress Multisite compatibility
*	Version 1.4.2 adds Spanish translation by Simon Samson at http://sitestraduction.com -- half-price translation services for non-profits

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==

**Is there any documentation or help on how to use this?**

Documentation can be found at:
https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/

**How do I display the signup sheets?**

Use the shortcode [pta_sign_up_sheet] to display the sign-up sheets on a page. There are also shortcode arguments for id (specific sheet id), date (specific date), and list_title (change the title of the main list of signup sheets). To make generating shortcodes with arguments easier, download the free PTA Zilla Shortcodes plugin extension from: 
https://stephensherrardplugins.com


== Screenshots ==

Screenshots and extended description can be found at:
https://stephensherrardplugins.com/pta-volunteer-sign-up-sheets/

Documentation can be found at:
https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/


== Changelog ==
**Version 1.4.4**

* Reupload to try to fix corrupted directory structure in the repository

**Version 1.4.2**

*	Added option to append reminder emails content to admin notifications when reminder emails have been sent
*	Settings pages now include a link to the documentation at: https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/
*	Several code fixes for translation
*	Includes Spanish translation by Simon Samson at http://sitestraduction.com -- half-price translation services for non-profits

**Version 1.4.1**

*	Remove some debug code accidentally left in the email function

**Version 1.4**

*	After a user signs up, save the firstname, lastname, and phone in user's meta if it doesn't exist so it will be pre-filled on future signups
*	Modified to work with Wordpress Multisite installations
*	Added reply-to email option for confirmation & reminder emails
*	Minor bug fixes and tweaks

**Version 1.3.4**

*	Tweak for admin side permissions

**Version 1.3.3**

*	Fixed bug dealing with sheet visibility & trash settings

**Version 1.3.2**

*	Patch for people with PHP versions < 5.3 who were getting fatal errors for str_getcsv function

**Version 1.3.1**

*	Small rework/fix to ensure reminder emails function is run every hour with the CRON job

**Version 1.3**

*	Added Wordpress editor for sheet details textarea to allow rich text formatting.
*	Added option to show "Filled" instead of the volunteer's first name and last initial for filled spots on a sign-up sheet.
*	Small change for compatibility with older PHP versions
*	Added hooks, filters, and CSS classes for easier extension & customizing
*	Additional translation coding prep

**Version 1.2.2**

*	First public release

== Additional Info ==

This plugin is a major fork and and almost a complete rewrite of the Sign-Up Sheets plugin by DLS software (now called Sign-Up Sheets Lite). We needed much more functionality than their plugin offered for our PTA web site, so I started with the Sign-Up Sheets plugin and added much more functionality to it, and eventually ended up rewriting quite a bit of it to fit our needs. If you need a much more simple sign-up sheets system, you may want to check out their plugin.