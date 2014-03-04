<?php
/*
Plugin Name: PTA Volunteer Sign Up Sheets
Plugin URI: http://wordpress.org/plugins/pta-volunteer-sign-up-sheets
Description: Volunteer sign-up sheet manager
Version: 1.3.1
Author: Stephen Sherrard
Author URI: https://stephensherrardplugins.com
License: GPL2
Text Domain: pta_volunteer_sus
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Save version # in database for future upgrades
if (!defined('PTA_VOLUNTEER_SUS_VERSION_KEY'))
    define('PTA_VOLUNTEER_SUS_VERSION_KEY', 'pta_volunteer_sus_version');

if (!defined('PTA_VOLUNTEER_SUS_VERSION_NUM'))
    define('PTA_VOLUNTEER_SUS_VERSION_NUM', '1.3.1');

add_option(PTA_VOLUNTEER_SUS_VERSION_KEY, PTA_VOLUNTEER_SUS_VERSION_NUM);

if (!class_exists('PTA_SUS_Data')) require_once 'classes/data.php';
if (!class_exists('PTA_SUS_List_Table')) require_once 'classes/list-table.php';
if (!class_exists('PTA_SUS_Widget')) require_once 'classes/widget.php';
if (!class_exists('PTA_CSV_EXPORTER')) require_once 'classes/class-pta_csv_exporter.php';
if (!class_exists('PTA_SUS_Emails')) require_once 'classes/class-pta_sus_emails.php';

if(!class_exists('PTA_Sign_Up_Sheet')):

class PTA_Sign_Up_Sheet {
	
    private $data;
    private $emails;
    public $db_version = '1.1.2';
    private $wp_roles;
    public $main_options;
    
    public function __construct() {
        $this->data = new PTA_SUS_Data();
        $this->emails = new PTA_SUS_Emails();

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook( __FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('pta_sus_cron_job', array($this, 'cron_functions'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'public_init' ));

        add_action( 'widgets_init', array($this, 'register_sus_widget') );

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
    }

    public function register_sus_widget() {
        register_widget( 'PTA_SUS_Widget' );
    }   
        
    /**
    * Admin Menu
    */
    public function admin_menu() {
        if (current_user_can( 'manage_options' )) {
            if (!class_exists('PTA_SUS_Admin')) {
                include_once(dirname(__FILE__).'/classes/class-pta_sus_admin.php');
                $pta_sus_admin = new PTA_SUS_Admin();
            }
        }
    }


    public function cron_functions() {
        // Let other plugins hook into our hourly cron job
        do_action( 'pta_sus_hourly_cron' );

        // Run our reminders email check
        $this->emails->send_reminders();

        // If automatic clearing of expired signups is enabled, run the check
        if($this->main_options['clear_expired_signups']) {
            $results = $this->data->delete_expired_signups();
            if($results && $this->main_options['enable_cron_notifications']) {
                $to = get_bloginfo( 'admin_email' );
                $subject = __("Volunteer Signup Housekeeping Completed!", 'pta_volunteer_sus');
                $message = __("Volunteer signup sheet CRON job has been completed.", 'pta_volunteer_sus')."\n\n" . 
                sprintf(__("%d expired signups were deleted.", 'pta_volunteer_sus'), (int)$results) . "\n\n";
                wp_mail($to, $subject, $message);            
            }
        }
    }

    public function public_init() {
        if(!is_admin()) {
            if (!class_exists('PTA_SUS_Public')) {
                include_once(dirname(__FILE__).'/classes/class-pta_sus_public.php');
                $pta_sus_public = new PTA_SUS_Public();
            }
        }
    }

    public function init() {
        load_plugin_textdomain( 'pta_volunteer_sus', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
    }
    
    /**
    * Activate the plugin
    */
    public function activate() {
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "activate-plugin_{$plugin}" );

        // Database Tables
        // **********************************************************
        $sql = "CREATE TABLE {$this->data->tables['sheet']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            first_date DATE NOT NULL,
            last_date DATE NOT NULL,
            details LONGTEXT NOT NULL,
            type VARCHAR(200) NOT NULL,
            position VARCHAR(200) NOT NULL,
            chair_name VARCHAR(100) NOT NULL,
            chair_email VARCHAR(100) NOT NULL,
            reminder1_days INT NOT NULL,
            reminder2_days INT NOT NULL,
            visible BOOL NOT NULL DEFAULT TRUE,
            trash BOOL NOT NULL DEFAULT FALSE,
            UNIQUE KEY id (id)
        );";
        $sql .= "CREATE TABLE {$this->data->tables['task']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            sheet_id INT NOT NULL,
            dates VARCHAR(500) NOT NULL,
            title VARCHAR(200) NOT NULL,
            time_start VARCHAR(50) NOT NULL,
            time_end VARCHAR(50) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            need_details VARCHAR(3) NOT NULL DEFAULT 'NO',
            position INT NOT NULL,
            UNIQUE KEY id (id)
        );";
        $sql .= "CREATE TABLE {$this->data->tables['signup']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            date DATE NOT NULL,
            item VARCHAR(100) NOT NULL,
            user_id INT NOT NULL,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            reminder1_sent BOOL NOT NULL DEFAULT FALSE,
            reminder2_sent BOOL NOT NULL DEFAULT FALSE,
            UNIQUE KEY id (id)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option("pta_sus_db_version", $this->db_version);
        
        // Add custom role and capability
        $role = get_role( 'author' );
        add_role('signup_sheet_manager', 'Sign-up Sheet Manager', $role->capabilities);
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
        }

        $role = get_role('administrator');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
        }

        // Schedule our Cron job for sending out email reminders
        // Wordpress only checks when someone visits the site, so
        // we'll keep this at hourly so that it hopefully runs at 
        // least once a day
        wp_schedule_event( time(), 'hourly', 'pta_sus_cron_job');

        // If options haven't previously been setup, create the default options
        // MAIN OPTIONS
        $defaults = array(
                    'enable_test_mode' => false,
                    'test_mode_message' => 'The Volunteer Sign-Up System is currently undergoing maintenance. Please check back later.',
                    'volunteer_page_id' => 0,
                    'hide_volunteer_names' => false,
                    'show_ongoing_in_widget' => true,
                    'show_ongoing_last' => true,
                    'login_required' => false,
                    'login_required_message' => 'You must be logged in to a valid account to view and sign up for volunteer opportunities.',
                    'enable_cron_notifications' => true,
                    'show_expired_tasks' => false,
                    'clear_expired_signups' => true,
                    'hide_donation_button' => false,
                    'reset_options' => false
                    );
        $options = get_option( 'pta_volunteer_sus_main_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_main_options', $options );

        // EMAIL OPTIONS
$confirm_template = 
"Dear {firstname} {lastname},

This is to confirm that you volunteered for the following:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
Item Details: {item_details}

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
$remind_template = 
"Dear {firstname} {lastname},

This is to remind you that you volunteered for the following:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
Item Details: {item_details}

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
        $defaults = array(
                    'from_email' => get_bloginfo( $show='admin_email' ),
                    'confirmation_email_subject' => 'Thank you for volunteering!',
                    'confirmation_email_template' => $confirm_template,
                    'reminder_email_subject' => 'Volunteer Reminder',
                    'reminder_email_template' => $remind_template,
                    'reminder_email_limit' => "",
                    );
        $options = get_option( 'pta_volunteer_sus_email_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_email_options', $options );
        
        // INTEGRATION OPTIONS
        $defaults = array(
                    'enable_member_directory' => false,
                    'directory_page_id' =>0,
                    'contact_page_id' => 0,
                    );
        $options = get_option( 'pta_volunteer_sus_integration_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_integration_options', $options );
    }
    
    /**
    * Deactivate the plugin
    */
    public function deactivate() {
        // Check permissions and referer
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$plugin}" );

        // Remove custom role and capability
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
            $role->remove_cap('read');
            remove_role('signup_sheet_manager');
        }
        $role = get_role('administrator');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
        }

        wp_clear_scheduled_hook('pta_sus_cron_job');
    }
	
}

$pta_sus = new PTA_Sign_Up_Sheet();

endif; // class exists

$pta_vol_sus_plugin_file = 'pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php';
add_filter( "plugin_action_links_{$pta_vol_sus_plugin_file}", 'pta_vol_sus_plugin_action_links', 10, 2 );
function pta_vol_sus_plugin_action_links( $links, $file ) {
    $extensions_link = '<a href="https://stephensherrardplugins.com">' . __( 'Extensions', 'pta_volunteer_sus' ) . '</a>';
    array_unshift( $links, $extensions_link );
    $settings_link = '<a href="' . admin_url( 'admin.php?page=pta-sus-settings_settings' ) . '">' . __( 'Settings', 'pta_volunteer_sus' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/* EOF */