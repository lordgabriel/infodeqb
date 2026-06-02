<?php // -*-mode: PHP; coding:utf-8;-*-
namespace MRBS;

use IntlDateFormatter;

require_once 'lib/autoload.inc';

/**************************************************************************
 *   MRBS Configuration File
 *   Configure this file for your site.
 *   You shouldn't have to modify anything outside this file.
 *
 *   This file has already been populated with the minimum set of configuration
 *   variables that you will need to change to get your system up and running.
 *   If you want to change any of the other settings in systemdefaults.inc.php
 *   or areadefaults.inc.php, then copy the relevant lines into this file
 *   and edit them here.   This file will override the default settings and
 *   when you upgrade to a new version of MRBS the config file is preserved.
 *
 *   NOTE: if you include or require other files from this file, for example
 *   to store your database details in a separate location, then you should
 *   use an absolute and not a relative pathname.
 **************************************************************************/

// Set $debug = true to force MRBS to output debugging information to the browser.
// Caching of files is also disabled when $debug is set.
// WARNING!  Do not use this for production systems, as not only will it generate
// unnecessary output in the broswer, but it could also expose sensitive security
// information (eg database usernames and passwords).
$debug = true;

/**********
 * Timezone
 **********/

// The timezone your meeting rooms run in. It is especially important
// to set this if you're using PHP 5 on Linux. In this configuration
// if you don't, meetings in a different DST than you are currently
// in are offset by the DST offset incorrectly.
//
// Note that timezones can be set on a per-area basis, so strictly speaking this
// setting should be in areadefaults.inc.php, but as it is so important to set
// the right timezone it is included here.
//
// When upgrading an existing installation, this should be set to the
// timezone the web server runs in.  See the INSTALL document for more information.
//
// A list of valid timezones can be found at http://php.net/manual/timezones.php
// The following line must be uncommented by removing the '//' at the beginning
$timezone = "Europe/Lisbon";



/*******************
 * Database settings
 ******************/
// Which database system: "pgsql"=PostgreSQL, "mysql"=MySQL
$dbsys = "mysql";
// Hostname of database server. For pgsql, can use "" instead of localhost
// to use Unix Domain Sockets instead of TCP/IP. For mysql "localhost"
// tells the system to use Unix Domain Sockets, and $db_port will be ignored;
// if you want to force TCP connection you can use "127.0.0.1".
$db_host = "webdb.fe.up.pt";
// If you need to use a non standard port for the database connection you
// can uncomment the following line and specify the port number
// $db_port = 1234;
// Database name:
$db_database = "feupptdeqb";
// Schema name.  This only applies to PostgreSQL and is only necessary if you have more
// than one schema in your database and also you are using the same MRBS table names in
// multiple schemas.
//$db_schema = "public";
// Database login user name:
$db_login = "feupptdeqb";
// Database login password:
$db_password = 'HQYbFNxqcYPf7eFTazfkJzT6bJDxYb';
// Prefix for table names.  This will allow multiple installations where only
// one database is available
$db_tbl_prefix = "celldeqbmrbs_";
// Set $db_persist to TRUE to use PHP persistent (pooled) database connections.  Note
// that persistent connections are not recommended unless your system suffers significant
// performance problems without them.   They can cause problems with transactions and
// locks (see http://php.net/manual/en/features.persistent-connections.php) and although
// MRBS tries to avoid those problems, it is generally better not to use persistent
// connections if you can.
$db_persist = false;


/* Add lines from systemdefaults.inc.php and areadefaults.inc.php below here
   to change the default configuration. Do _NOT_ modify systemdefaults.inc.php
   or areadefaults.inc.php.  */

/*********************************
 * Site identification information
 *********************************/
$mrbs_admin = "Direção DEQB - Sala de Cultura Celular";
$mrbs_admin_email = 'lfamartins@gmail.com';
// NOTE:  there are more email addresses in $mail_settings below.    You can also give
// email addresses in the format 'Full Name <address>', for example:
// $mrbs_admin_email = 'Booking System <admin_email@your.org>';
// if the name section has any "peculiar" characters in it, you will need
// to put the name in double quotes, e.g.:
// $mrbs_admin_email = '"Bloggs, Joe" <admin_email@your.org>';

// The company name is mandatory.   It is used in the header and also for email notifications.
// The company logo, additional information and URL are all optional.

$mrbs_company = "DEQB - Sala de Cultura Celular";   // This line must always be uncommented ($mrbs_company is used in various places)

// Uncomment this next line to use a logo instead of text for your organisation in the header
$mrbs_company_logo = "images/logo_deq.png";    // name of your logo file.   This example assumes it is in the MRBS directory

// Uncomment this next line for supplementary information after your company name or logo
//$mrbs_company_more_info = "Faculdade de Engenharia da Universidade do Porto";  // e.g. "XYZ Department"

// Uncomment this next line to have a link to your organisation in the header
$mrbs_company_url = "https://deq.fe.up.pt/infodeqb/cell";

// This is to fix URL problems when using a proxy in the environment.
// If links inside MRBS appear broken, then specify here the URL of
// your MRBS root directory, as seen by the users. For example:
 $url_base =  "https://deq.fe.up.pt/";
// It is also recommended that you set this if you intend to use email
// notifications, to ensure that the correct URL is displayed in the
// notification.
$url_base = "https://deq.fe.up.pt/infodeqb/cell/";

/***********************************************
 * Authentication settings - read AUTHENTICATION
 ***********************************************/

$auth["session"] = "shibboleth"; // How to get and keep the user ID. One of
// "http" "php" "cookie" "ip" "host" "nt" "omni"
// "remote_user"

$auth["type"] = "shibboleth"; // How to validate the user/password. One of "none"
// "config" "db" "db_ext" "pop3" "imap" "ldap" "nis"
// "nw" "ext".

// Configuration parameters for 'shibboleth' session scheme
// URI used to create the shibboleth auth request.
$auth['shibboleth']['base_uri'] = "https://deq.fe.up.pt";

// URI used to redirect to after Shibboleth does the authentication.
$auth['shibboleth']['site_uri'] = "https://deq.fe.up.pt/infodeqb/cell/";

// Shibboleth handler URI
$auth['shibboleth']['handler_uri'] = "Shibboleth.sso";

// Attribute where the complete user name is stored. This is displayed after user logs in.
$auth['shibboleth']['name_attribute'] = "DisplayName";

// Attribute where the unique user name is stored.
$auth['shibboleth']['username_attribute'] = "Mail";

// Attribute where email is stored.
$auth['shibboleth']['email_attribute'] = 'Mail';


// The set of Shibboleth attributes values that define a valid user
// only one item is needed, not all of them at once
$auth['shibboleth']['user_roles'] = array(
        'eduPersonPrimaryAffiliation' => array('staff', 'student', 'alum', 'faculty')
);


// The set of Shibboleth attributes values that define a valid administrator
// only one item is needed, not all of them at once
$auth['shibboleth']['admin_roles'] = array(
    'eppn' => array('up403485@up.pt', 'up242686@up.pt', 'up619087@up.pt')
);


/**********************************************
 * Email settings
 **********************************************/

// BASIC SETTINGS
// --------------

// Set the email address of the From field. Default is 'admin_email@your.org'
$mail_settings['from'] = 'FEUP | Direção do DEQB <deqbdir@fe.up.pt>';


// By default MRBS will send some emails (eg booking approval emails) as though they have come from
// the user, rather than the From address above.   However some email servers will not allow this in
// order to prevent email spoofing.   If this is the case then set this to true in order that the
// From address above is used for all emails.
$mail_settings['use_from_for_all_mail'] = false;

// By default MRBS will set a Reply-To address and use current user's email address.  Set this to
// false in order not to set a Reply-To address.
$mail_settings['use_reply_to'] = false;

// The address to be used for the ORGANIZER in an iCalendar event.   Do not make
// this email address the same as the admin email address or the recipients
// email address because on some mail systems, eg IBM Domino, the iCalendar email
// notification is silently discarded if the organizer's email address is the same
// as the recipient's.  On other systems you may get a "Meeting not found" message.
$mail_settings['organizer'] = 'mrbs@your.org';

// Set the recipient email. Default is 'admin_email@your.org'. You can define
// more than one recipient like this "john@doe.com,scott@tiger.com"
$mail_settings['recipients'] = 'lfamartins@gmail.com, cmferreira@fe.up.pt,smfaia@fe.up.pt, joanasrocha@fe.up.pt';

// Set email address of the Carbon Copy field. Default is ''. You can define
// more than one recipient (see 'recipients')
$mail_settings['cc'] = '';

// Set to true if you want the cc addresses to be appended to the to line.
// (Some email servers are configured not to send emails if the cc or bcc
// fields are set)
$mail_settings['treat_cc_as_to'] = false;



// WHO TO EMAIL
// ------------
// The following settings determine who should be emailed when a booking is made,
// edited or deleted (though the latter two events depend on the "When" settings below).
// Set to true or false as required
// (Note:  the email addresses for the area and room administrators are set from the
// edit_area.php and edit_room.php pages in MRBS)
$mail_settings['admin_on_bookings']      = false;  // the addresses defined by $mail_settings['recipients'] below
$mail_settings['area_admin_on_bookings'] = true;  // the area administrator
$mail_settings['room_admin_on_bookings'] = false;  // the room administrator
$mail_settings['booker']                 = true;  // the person making the booking
$mail_settings['book_admin_on_approval'] = true;  // the booking administrator when booking approval is enabled
// (which is the MRBS admin, but this setting allows MRBS
// to be extended to have separate booking approvers)

// WHEN TO EMAIL
// -------------
// These settings determine when an email should be sent.
// Set to true or false as required
//
// (Note:  (a) the variables $mail_settings['admin_on_delete'] and
        // $mail_settings['admin_all'], which were used in MRBS versions 1.4.5 and
        // before are now deprecated.   They are still supported for reasons of backward
        // compatibility, but they may be withdrawn in the future.  (b)  the default
        // value of $mail_settings['on_new'] is true for compatibility with MRBS 1.4.5
        // and before, where there was no explicit config setting, but mails were always sent
        // for new bookings if there was somebody to send them to)

$mail_settings['on_new']    = true;   // when an entry is created
$mail_settings['on_change'] = true;  // when an entry is changed
$mail_settings['on_delete'] = true;  // when an entry is deleted

// It is also possible to allow all users or just admins to choose not to send an
// email when creating or editing a booking.  This can be useful if an inconsequential
// change is being made, or many bookings are being made at the beginning of a term or season.
$mail_settings['allow_no_mail']        = false;
$mail_settings['allow_admins_no_mail'] = false;  // Ignored if 'allow_no_mail' is true
$mail_settings['no_mail_default'] = false; // Default value for the 'no mail' checkbox.
// true for checked (ie don't send mail),
// false for unchecked (ie do send mail)


// WHAT TO EMAIL
// -------------
// These settings determine what should be included in the email
// Set to true or false as required
$mail_settings['details']   = true; // Set to true if you want full booking details;
// otherwise you just get a link to the entry
$mail_settings['html']      = true; // Set to true if you want HTML mail
$mail_settings['icalendar'] = false; // Set to true to include iCalendar details
// which can be imported into a calendar.  (Note:
// iCalendar details will not be sent for areas
        // that use periods as there isn't a mapping between
        // periods and time of day, so the calendar would not
// be able to import the booking)

// HOW TO EMAIL - LANGUAGE
// -----------------------------------------

// Set the language used for emails (choose an available lang.* file).
$mail_settings['admin_lang'] = 'en';   // Default is 'en'.


// HOW TO EMAIL - ADDRESSES
// ------------------------
// The email addresses of the MRBS administrator are set in the config file, and those of
// the room and area administrators are set though the edit_area.php and edit_room.php
// pages in MRBS.  But if you have set $mail_settings['booker'] above to true, MRBS will
// need the email addresses of ordinary users.   If you are using the "db"
// authentication method then MRBS will be able to get them from the users table.  But
// if you are using any other authentication scheme then the following settings allow
// you to specify a domain name that will be appended to the username to produce a
// valid email address (eg "@domain.com").  MRBS will add the '@' character for you.
$mail_settings['domain'] = '';
// If you use $mail_settings['domain'] above and the username returned by mrbs contains extra
// strings appended like the domain name ('username.domain'), you need to provide
// this extra string here so that it will be removed from the username.
$mail_settings['username_suffix'] = '';


// HOW TO EMAIL - BACKEND
// ----------------------
// Set the name of the backend used to transport your mails. Either 'mail',
// 'smtp', 'sendmail' or 'qmail'. Default is 'mail'.
$mail_settings['admin_backend'] = 'smtp';

// Set this to true if you want MRBS to output debug information when you are sending email.
// If you are not getting emails it can be helpful by telling you (a) whether the mail functions
// are being called in the first place (b) whether there are addresses to send email to and (c)
// the result of the mail sending operation.
$mail_settings['debug'] = false;
// Where to send the debug output.  Can be 'browser' or 'log' (for the error_log)
$mail_settings['debug_output'] = 'browser';

/*******************
 * SMTP settings
 */

// These settings are only used with the "smtp" backend
$smtp_settings['host'] = 'mail.up.pt';  // SMTP server
$smtp_settings['port'] = 587;           // SMTP port number
$smtp_settings['auth'] = true;        // Whether to use SMTP authentication
$smtp_settings['secure'] = 'SSL';         // Encryption method: '', 'tls' or 'ssl' - note that 'tls' means TLS is used even if the SMTP
// server doesn't advertise it. Conversely if you specify '' and the server advertises TLS, TLS
// will be used, unless the 'disable_opportunistic_tls' configuration parameter shown below is
// set to true.
$smtp_settings['username'] = 'up356946@up.pt';       // Username (if using authentication)
$smtp_settings['password'] = 'Altruism2@25Sin';       // Password (if using authentication)

/*******************
 * Themes
 *******************/

// Choose a theme for the MRBS.   The theme controls two aspects of the look and feel:
//   (a) the styling:  the most commonly changed colours, dimensions and fonts have been
//       extracted from the main CSS file and put into the styling.inc file in the appropriate
//       directory in the Themes directory.   If you want to change the colour scheme, you should
//       be able to do it by changing the values in the theme file.    More advanced styling changes
//       can be made by changing the rules in the CSS file.
//   (b) the header:  the header.inc file which contains the function used for producing the header.
//       This enables organisations to plug in their own header functions quite easily, in cases where
//       the desired corporate look and feel cannot be changed using the CSS alone and the mark-up
//       itself needs to be changed.
//
//  MRBS will look for the files "styling.inc" and "header.inc" in the directory Themes/$theme and
//  if it can't find them will use the files in Themes/default.    A theme directory can contain
//  a replacement styling.inc file or a replacement header.inc file or both.

// Available options are:

// "default"        Default MRBS theme
// "classic126"     Same colour scheme as MRBS 1.2.6

$theme = "infodeq";

// Use the $custom_css_url to override the standard MRBS CSS.
$custom_css_url = 'css/custom.css';

// Use the $custom_js_url to add your own JavaScript.
//$custom_js_url = 'js/custom.js';

/******************
 * Booking policies
 ******************/

// Most booking policies can be configured on a per-area basis, so these variables
// appear in the areadefaults.inc.php file.

// Set this to true if you want to prevent users editing or deleting approved bookings.
// Note that this setting only applies if booking approval is in force for the area.
// If it isn't in force you can prevent bookings being edited or deleted by using the
// min and max delete ahead settings.
$approved_bookings_cannot_be_changed = false;


// By default, bookings cannot be made on days that are designated holidays (see $holidays).
$prevent_booking_on_holidays = true;

// Set this to true to prevent bookings being made on weekends (see $weekdays).
$prevent_booking_on_weekends = true;

/******************
 * Display settings
 ******************/

// [These are all variables that control the appearance of pages and could in time
//  become per-user settings]

// Start of week: 0 for Sunday, 1 for Monday, etc.
$weekstarts = 1;

// Set this to true to add styling to weekend days
$style_weekends = true;

// A two-dimensional array of holidays in yyyy-mm-dd format, indexed first by year, for example
// $holidays[2022] = array('2022-01-01', '2022-11-24');  // New Year's Day and US Thanksgiving 2022
// Dates can include ranges in the form 'yyyy-mm-dd..yyyy-mm-dd', eg
// $holidays[2022] = array('2022-01-01', '2022-07-01..2022-07-31');  // New Year's Day and all of July
// By default, bookings cannot be made on days that are designated holidays (see $prevent_booking_on_holidays).
// Holidays are styled differently in the main calendar views.
$holidays[2024] = array('2024-01-01', '2024-02-13','2024-03-29', '2024-04-25', '2024-05-01', '2024-05-30', '2024-06-10', '2024-06-24', '2024-08-15', '2024-10-05', '2024-11-01', '2024-12-01', '2024-12-08', '2024-12-24');
$holidays[2025] = array('2025-01-01', '2025-04-18','2025-04-20', '2025-04-25', '2025-05-01', '2025-06-10', '2025-06-19', '2025-06-24', '2025-08-15', '2025-10-05', '2025-11-01', '2025-12-01', '2025-12-08', '2025-12-25');
// Whether or not to display the timezone
$display_timezone = true;

// To show ISO week numbers in the main calendar, set this to true. The week
// numbers are only displayed if you set $weekstarts to 1 (Monday), i.e. the
// start of the ISO week.
$view_week_number = true;

// To display week numbers in the mini-calendars, set this to true. The week
// numbers are only displayed if you set $weekstarts to 1 (Monday), i.e. the
// start of the ISO week.
$mincals_week_numbers = true;

// Define default starting view (month, week or day)
// Default is day
$default_view = "week";

// The default setting for the week and month views: whether to view all the
// rooms (true) or not (false).
$default_view_all = false;

// Define default room to start with (used by index.php)
// Room numbers can be determined by looking at the Edit or Delete URL for a
// room on the admin page.
// Default is 0
$default_room = 2;

/*************
 * Entry Types
 *************/

// This array lists the configured entry type codes. The values map to a
// single char in the MRBS database, and so can be any permitted PHP array
// character.
//
// The default descriptions of the entry types are held in the language files
// as "type.X" where 'X' is the entry type.  If you want to change the description
// you can override the default descriptions by setting the $vocab_override config
// variable.   For example, if you add a new booking type 'C' the minimum you need
// to do is add a line to config.inc.php like:
//
// $vocab_override["en"]["type.C"] =     "New booking type";
//
// Below is a basic default array which ensures there are at least some types defined.
// The proper type definitions should be made in config.inc.php.
//
// Each type has a color which is defined in the array $color_types in the styling.inc
// file in the Themes directory

unset($booking_types);    // Include this line when copying to config.inc.php
$booking_types[] = "I";
$booking_types[] = "I";

// If you don't want to use types then uncomment the following line.  (The booking will
// still have a type associated with it in the database, which will be the default type.)
// unset($booking_types);

// Default brief description for new bookings
$default_name = "";

// Set this to true if you want the booking name (brief description) to
// default to the current user's display name.  If set, this setting overrides
// $default_name.
$default_name_display_name = false;

// Default long description for new bookings
$default_description = "";



/************************
 * Miscellaneous settings
 ************************/

// PRIVATE BOOKINGS SETTINGS

// Note:  some settings for private bookings can be set on a per-area basis and
// so appear in the areadefaults.inc.php file

// Choose which fields should be private by setting
// $is_private_field['tablename.columnname'] = true
// At the moment only fields in the entry and users table can be marked as private,
// including custom fields, but with the exception of the following entry table fields:
// start_time, end_time, entry_type, repeat_id, room_id, timestamp, type, status,
// reminded, info_time, info_user, info_text.
$is_private_field['entry.name'] = true;
$is_private_field['entry.description'] = true;
$is_private_field['entry.create_by'] = true;
$is_private_field['entry.modified_by'] = true;


// Set to true if you want admins to be able to perform bulk deletions
// on the Report page.  (It also only shows up if JavaScript is enabled)
$auth['show_bulk_delete'] = true;

// Set this to true if you want to restrict the ability to use the "Copy" button on
// the view_entry page to ordinary users viewing their own entries and to admins.
$auth['only_admin_can_copy_others_entries'] = true;
$enable_registration = false;
$is_mandatory_field['entry.description'] = true;