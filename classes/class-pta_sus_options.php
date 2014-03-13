<?php
/**
* Admin Setting page
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Options {
	public $main_options;
	public $email_options;
	public $integration_options;
	public $member_directory_active;
	private $settings_page_slug = 'pta-sus-settings_settings';

	public function __construct() {
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
		add_action('admin_init', array($this, 'register_options'));

	} // End Construct

	/**
    * Admin Page: Options/Settings
    */
    function admin_options() {
        if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta_volunteer_sus' ) );
        }
        $docs_link = '<a href="https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/" target="_blank">'.__('Documentation', 'pta_volunteer_sus') . '</a>';

        ?>
        <div class="wrap pta_sus">
            <div id="icon-themes" class="icon32"></div>
            <h2><?php _e('PTA Volunteer Sign-up Sheets Settings', 'pta_volunteer_sus'); ?></h2>
            <?php settings_errors(); ?>
            <?php $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'main_options'; ?> 
            <h2 class="nav-tab-wrapper">  
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=main_options" class="nav-tab <?php echo $active_tab == 'main_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Main Settings', 'pta_volunteer_sus'); ?></a>  
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=email_options" class="nav-tab <?php echo $active_tab == 'email_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Email Settings', 'pta_volunteer_sus'); ?></a>   
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=integration_options" class="nav-tab <?php echo $active_tab == 'integration_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Integration Settings', 'pta_volunteer_sus'); ?></a> 
            </h2> 
            <form action="options.php" method="post">
                <?php 

                if ( 'main_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_main_options'); 
                	do_settings_sections('pta_volunteer_sus_main'); 
                } elseif ( 'email_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_email_options'); 
                	do_settings_sections('pta_volunteer_sus_email'); 
                } else {
                	settings_fields('pta_volunteer_sus_integration_options'); 
                	do_settings_sections('pta_volunteer_sus_integration'); 
                }
                       
                submit_button();
                ?>
            </form>
            <?php if (!$this->main_options['hide_donation_button']): ?>
                <h5><?php _e('Please help support continued development of this plugin! Any amount helps!', 'pta_volunteer_sus'); ?></h5>
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                    <input type="hidden" name="cmd" value="_s-xclick">
                    <input type="hidden" name="hosted_button_id" value="R4HF689YQ9DEE">
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form>
            <?php endif; ?>
            <h3><?php echo $docs_link; ?></h3>
        </div>        
        <?php
        return;
    }

    public function register_options() {
    	// Main Settings
        register_setting( 'pta_volunteer_sus_main_options', 'pta_volunteer_sus_main_options', array($this, 'pta_sus_validate_main_options') );
        add_settings_section('pta_volunteer_main', __('Main Settings', 'pta_volunteer_sus'), array($this, 'pta_volunteer_main_description'), 'pta_volunteer_sus_main');        
        add_settings_field('enable_test_mode', __('Enable Test Mode', 'pta_volunteer_sus'), array($this, 'enable_test_mode_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('test_mode_message', __('Test Mode Message:', 'pta_volunteer_sus'), array($this, 'test_mode_message_text_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('volunteer_page_id', __('Volunteer Sign Up Page', 'pta_volunteer_sus'), array($this, 'volunteer_page_id_select'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_volunteer_names', __('Hide volunteer names from public?', 'pta_volunteer_sus'), array($this, 'hide_volunteer_names_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_ongoing_in_widget', __('Show Ongoing events in Widget?', 'pta_volunteer_sus'), array($this, 'show_ongoing_in_widget_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_ongoing_last', __('Show Ongoing events last?', 'pta_volunteer_sus'), array($this, 'show_ongoing_last_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('login_required', __('Login Required?', 'pta_volunteer_sus'), array($this, 'login_required_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('login_required_message', __('Login Required Message:', 'pta_volunteer_sus'), array($this, 'login_required_message_text_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_cron_notifications', __('Enable CRON Notifications?', 'pta_volunteer_sus'), array($this, 'enable_cron_notifications_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('detailed_reminder_admin_emails', __('Detailed Reminder Notifications?', 'pta_volunteer_sus'), array($this, 'detailed_reminder_admin_emails_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_expired_tasks', __('Show Expired Tasks?', 'pta_volunteer_sus'), array($this, 'show_expired_tasks_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('clear_expired_signups', __('Automatically clear expired signups?', 'pta_volunteer_sus'), array($this, 'clear_expired_signups_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_donation_button', __('Hide donation button?', 'pta_volunteer_sus'), array($this, 'hide_donation_button_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');

        // Email Settings
        register_setting( 'pta_volunteer_sus_email_options', 'pta_volunteer_sus_email_options', array($this, 'pta_sus_validate_email_options') );
        add_settings_section('pta_volunteer_email', __('Email Settings', 'pta_volunteer_sus'), array($this, 'pta_volunteer_email_description'), 'pta_volunteer_sus_email');
        add_settings_field('from_email', __('FROM email:', 'pta_volunteer_sus'), array($this, 'from_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('replyto_email', __('Reply-To email:', 'pta_volunteer_sus'), array($this, 'replyto_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('confirmation_email_subject', __('Confirmation email subject:', 'pta_volunteer_sus'), array($this, 'confirmation_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('confirmation_email_template', __('Confirmation email template:', 'pta_volunteer_sus'), array($this, 'confirmation_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_subject', __('Reminder email subject:', 'pta_volunteer_sus'), array($this, 'reminder_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_template', __('Reminder email template:', 'pta_volunteer_sus'), array($this, 'reminder_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_limit', __('Max Reminders per Hour:', 'pta_volunteer_sus'), array($this, 'reminder_email_limit_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');

        // Integration Settings
        register_setting( 'pta_volunteer_sus_integration_options', 'pta_volunteer_sus_integration_options', array($this, 'pta_sus_validate_integration_options') );
        add_settings_section('pta_volunteer_integration', __('Integration Settings', 'pta_volunteer_sus'), array($this, 'pta_volunteer_integration_description'), 'pta_volunteer_sus_integration');
        if (is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
            add_settings_field('enable_member_directory', __('Enable PTA Member Directory:', 'pta_volunteer_sus'), array($this, 'enable_member_directory_checkbox'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            add_settings_field('directory_page_id', __('Member Directory Page:', 'pta_volunteer_sus'), array($this, 'directory_page_id_select'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            add_settings_field('contact_page_id', __('Contact Form Page:', 'pta_volunteer_sus'), array($this, 'contact_page_id_select'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            $this->member_directory_active = true;
        } else {
            $this->member_directory_active = false;
        }       
    } // Register Options

    protected function validate_options($inputs, $fields, $options) {
        $err = 0;
        foreach ($fields as $field => $type) {
        	switch ($type) {
        		case 'integer':
        			if( is_numeric($inputs[$field]) || '' === $inputs[$field] ) {
		                $this->{$options}[$field] = (int)$inputs[$field];
		            } else {
		                $err++;
		                $message = sprintf(__('Invalid entry for %s!', 'pta_volunteer_sus'), $field);
		                add_settings_error( $field, $field, $message, $type = 'error' );
		            }
        			break;
    			case 'email':
    				if(is_email($inputs[$field])) {
		                $this->{$options}[$field] = $inputs[$field];
		            } else {
		                $err++;
		                $message = sprintf(__('Invalid email for %s!', 'pta_volunteer_sus'), $field);
		                add_settings_error( $field, $field, $message, $type = 'error' );
		            }
		            break;
        		case 'text':
        			if(!preg_match( "/[^A-Za-z0-9\-\.\,\!\&\(\)\'\/\?\ ]/", stripslashes( $inputs[$field] ) ) ) {
		                $this->{$options}[$field] = $inputs[$field];
		            } else {
		                $err++;
		                $message = sprintf(__('Invalid characters in %s!', 'pta_volunteer_sus'), $field);
		                add_settings_error( $field, $field, $message, $type = 'error' );
		            }
		            break;
	            case 'bool':
	            	$this->{$options}[$field] = (bool)$inputs[$field];
	            	break;
	            case 'textarea':
	            	$this->{$options}[$field] = wp_kses_post($inputs[$field]);
	            	break;
        		default:
        			$err++;
	                $message = sprintf(__('Unrecognized field type: %s!', 'pta_volunteer_sus'), $type);
	                add_settings_error( $field, $field, $message, $type = 'error' );
        			break;
        	}
        }
        
        if(!$err) {
            $field = 'volunteer_settings_form'; // set any field here
            $message = __("Settings successfully saved!", 'pta_volunteer_sus');
            add_settings_error( $field, $field, $message, $type = 'updated' );
        }
        return $this->{$options};
    }

    public function pta_sus_validate_main_options($inputs) {
    	$options = "main_options";
    	$fields = array(
    		'enable_test_mode' => 'bool',
            'test_mode_message' => 'text',
            'volunteer_page_id' => 'integer',
            'hide_volunteer_names' => 'bool',
            'show_ongoing_in_widget' => 'bool',
            'show_ongoing_last' => 'bool',
            'login_required' => 'bool',
            'login_required_message' => 'text',
            'enable_cron_notifications' => 'bool',
            'detailed_reminder_admin_emails' => 'bool',
            'show_expired_tasks' => 'bool',
            'clear_expired_signups' => 'bool',
            'hide_donation_button' => 'bool',
    		);
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_email_options($inputs) {
    	$options = "email_options";
    	$fields = array(
    		'from_email' => 'email',
            'replyto_email' => 'email',
            'confirmation_email_subject' => 'text',
            'confirmation_email_template' => 'textarea',
            'reminder_email_subject' => 'text',
            'reminder_email_template' => 'textarea',
            'reminder_email_limit' => 'integer',
    		);
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_integration_options($inputs) {
    	$options = "integration_options";
    	$fields = array(
    		'enable_member_directory' => 'bool',
            'directory_page_id' =>'integer',
            'contact_page_id' => 'integer',
    		);
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_volunteer_main_description() {
        echo '<p> ' . __('Main plugin settings', 'pta_volunteer_sus') . '</p>';
    }

    public function pta_volunteer_email_description() {
        echo '<p> ' . __('Email settings', 'pta_volunteer_sus') . '</p>';
    }

    public function pta_volunteer_integration_description() {
        echo '<p> ' . __('Integration with other plugins', 'pta_volunteer_sus') . '</p>';
        if (!is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
            $link = '<a href="http://wordpress.org/plugins/pta-member-directory/">http://wordpress.org/plugins/pta-member-directory/</a>';
            echo '<p> ' . __('This plugin can integrate with the PTA Member Directory and Contact Form plugin to set contacts for each sign-up sheet, with contact links being directed to the contact form.', 'pta_volunteer_sus') . '</p>';
            echo '<p> ' . sprintf(__('Search for "PTA Member Directory" from your Install Plugins page, or download from Wordpress.org here: %s', 'pta_volunteer_sus'), $link ) . '</p>';
        }
    }

    public function volunteer_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_main_options[volunteer_page_id]',
            'selected'      => $this->main_options['volunteer_page_id'],
            'show_option_none'  => __('None', 'pta_volunteer_sus'),
            'option_none_value' => 0
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The page where you put the shortcode</em> [pta_sign_up_sheet] <em>to generate the main sign-ups page', 'pta_volunteer_sus') . '</em>';
    }

    public function directory_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_integration_options[directory_page_id]',
            'selected'      => $this->integration_options['directory_page_id'],
            'show_option_none'  => __('None', 'pta_volunteer_sus'),
            'option_none_value' => 0
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The member directory page where you put the shortcode [pta_member_directory]', 'pta_volunteer_sus') . '</em>';
    }

    public function contact_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_integration_options[contact_page_id]',
            'selected'      => $this->integration_options['contact_page_id'],
            'show_option_none'  => __('None', 'pta_volunteer_sus'),
            'option_none_value' => 0
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The member directory contact form page where you put the shortcode [pta_member_contact] if you are using the separate contact form.', 'pta_volunteer_sus') . '</em>';
    }

    public function test_mode_message_text_input() {
        echo "<input id='test_mode_message' name='pta_volunteer_sus_main_options[test_mode_message]' size='80' type='text' value='{$this->main_options['test_mode_message']}' />";
        echo '<em> ' . __('The message users see on volunteer sign-up pages when in test mode.', 'pta_volunteer_sus') . '</em>';
    }

    public function login_required_message_text_input() {
        echo "<input id='login_required_message' name='pta_volunteer_sus_main_options[login_required_message]' size='80' type='text' value='{$this->main_options['login_required_message']}' />";
        echo '<em> ' . __('The message users see on volunteer sign-up pages when they are not logged in and the Login Required option is enabled.', 'pta_volunteer_sus') . '</em>';
    }

    public function from_email_text_input() {
        echo "<input id='from_email' name='pta_volunteer_sus_email_options[from_email]' size='40' type='text' value='{$this->email_options['from_email']}' />";
        echo '<em> ' . __('The email address that confirmation and reminder emails will be sent from.', 'pta_volunteer_sus') . '</em>';
    }

    public function replyto_email_text_input() {
        echo "<input id='replyto_email' name='pta_volunteer_sus_email_options[replyto_email]' size='40' type='text' value='{$this->email_options['replyto_email']}' />";
        echo '<em> ' . __('The Reply-To email address for confirmation and reminder emails.', 'pta_volunteer_sus') . '</em>';
    }

    public function confirmation_email_subject_text_input() {
        echo "<input id='confirmation_email_subject' name='pta_volunteer_sus_email_options[confirmation_email_subject]' size='60' type='text' value='{$this->email_options['confirmation_email_subject']}' />";
        echo '<em> ' . __('Subject line for signup confirmation email messages', 'pta_volunteer_sus') .'</em>';
    }

    public function reminder_email_subject_text_input() {
        echo "<input id='reminder_email_subject' name='pta_volunteer_sus_email_options[reminder_email_subject]' size='60' type='text' value='{$this->email_options['reminder_email_subject']}' />";
        echo '<em> '. __('Subject line for signup reminder email messages.', 'pta_volunteer_sus') . '</em>';
    }

    public function reminder_email_limit_text_input() {
        echo "<input id='reminder_email_limit' name='pta_volunteer_sus_email_options[reminder_email_limit]' size='5' type='text' value='{$this->email_options['reminder_email_limit']}' />";
        echo '<em> '. __('Max # of reminder emails to send out in a hour. Leave blank or zero for no limit.', 'pta_volunteer_sus') . '</em>';
    }

    public function enable_member_directory_checkbox() {
        if(isset($this->integration_options['enable_member_directory']) && true === $this->integration_options['enable_member_directory']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        $pta_md_link = '<a href="http://wordpress.org/plugins/pta-member-directory/">'.__('PTA Member Directory and Contact Form', 'pta_volunteer_sus').'</a>';
        ?>
        <input name="pta_volunteer_sus_integration_options[enable_member_directory]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php printf( __('Enable integration with the %s', 'pta_volunteer_sus'), $pta_md_link); ?></em>
        <?php
    }

    public function enable_test_mode_checkbox() {
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[enable_test_mode]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('Puts Volunteer Sign-up Sheet system in test mode. Only admin level users can view public side sign-up sheets.', 'pta_volunteer_sus'); ?></em>
        <?php
    }

    public function hide_volunteer_names_checkbox() {
        if(isset($this->main_options['hide_volunteer_names']) && true === $this->main_options['hide_volunteer_names']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[hide_volunteer_names]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked filled sign-up slots will show "Filled" instead of first name and last initial of volunteer.', 'pta_volunteer_sus'); ?></em>
        <?php
    }

    public function show_ongoing_in_widget_checkbox() {
        if(isset($this->main_options['show_ongoing_in_widget']) && true === $this->main_options['show_ongoing_in_widget']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_ongoing_in_widget]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, Ongoing events will be shown in sidebar widget.', 'pta_volunteer_sus'); ?></em>
        <?php
    }

    public function show_ongoing_last_checkbox() {
        if(isset($this->main_options['show_ongoing_last']) && true === $this->main_options['show_ongoing_last']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_ongoing_last]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, Ongoing events will be shown at the bottom of sign up sheet lists (and widget, if enabled).', 'pta_volunteer_sus'); ?></em>
        <?php
    }

    public function login_required_checkbox() {
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[login_required]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Force user to be logged in before they can view or sign-up for any volunteer sheets.', 'pta_volunteer_sus').'</em>';
    }

    public function enable_cron_notifications_checkbox() {
        if(isset($this->main_options['enable_cron_notifications']) && true === $this->main_options['enable_cron_notifications']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[enable_cron_notifications]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Sends site admin an email whenever a CRON job is completed (such as sending reminders or deleting expired signups).', 'pta_volunteer_sus').'</em>';
    }

    public function detailed_reminder_admin_emails_checkbox() {
        if(isset($this->main_options['detailed_reminder_admin_emails']) && true === $this->main_options['detailed_reminder_admin_emails']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[detailed_reminder_admin_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Admin reminder emails notification will include the message body of all reminders sent, useful for troubleshooting.', 'pta_volunteer_sus').'</em>';
    }

    public function show_expired_tasks_checkbox() {
        if(isset($this->main_options['show_expired_tasks']) && true === $this->main_options['show_expired_tasks']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_expired_tasks]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Shows expired tasks on the admin View Signups page for a sheet. Expired tasks are not counted in the Total Spots column.', 'pta_volunteer_sus').'</em>';
    }

    public function clear_expired_signups_checkbox() {
        if(isset($this->main_options['clear_expired_signups']) && true === $this->main_options['clear_expired_signups']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[clear_expired_signups]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Automatically clears expired signups from the database (runs with hourly CRON function). Expired signups are not counted in the Filled Spots column.', 'pta_volunteer_sus').'</em>';
    }

    public function hide_donation_button_checkbox() {
        if(isset($this->main_options['hide_donation_button']) && true === $this->main_options['hide_donation_button']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[hide_donation_button]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta_volunteer_sus') . ' <em> '. __('Hides the donation button at bottom of settings pages.', 'pta_volunteer_sus').'</em>';
    }

    public function confirmation_email_template_textarea_input() {
        echo "<textarea id='confirmation_email_template' name='pta_volunteer_sus_email_options[confirmation_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['confirmation_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Email user receives when they sign up for a volunteer slot.', 'pta_volunteer_sus');
        echo '<br />' . __('Available Template Tags: ', 'pta_volunteer_sus') . '{sheet_title} {task_title} {date} {start_time} {end_time} {item_details} {firstname} {lastname} {contact_emails} {site_name} {site_url}';
    }

    public function reminder_email_template_textarea_input() {
        echo "<textarea id='reminder_email_template' name='pta_volunteer_sus_email_options[reminder_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['reminder_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Reminder email sent to volunteers.', 'pta_volunteer_sus');
        echo '<br />' . __('Available Template Tags: ', 'pta_volunteer_sus') . '{sheet_title} {task_title} {date} {start_time} {end_time} {item_details} {firstname} {lastname} {contact_emails} {site_name} {site_url}';
    }

} // End Class
/* EOF */