<?php
/**
* Public pages
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Public {

	private $data;
    private $table;
    private $plugin_path;
    private $plugin_prefix = 'pta-sus';
    private $request_uri;
    private $all_sheets_uri;
    public $main_options;
    public $email_options;
    public $integration_options;
    public $member_directory_active;
    public $submitted;
    public $err;
    public $success;
    public $errors;
    public $messages;
    private $task_item_header;
    private $start_time_header;
    private $end_time_header;
    private $item_details_header;
    private $item_qty_header;
    private $na_text;
    
    public function __construct() {
        $this->data = new PTA_SUS_Data();
        
        $plugin = plugin_basename(__FILE__);
        
        $this->plugin_path = dirname(__FILE__).'/';

        $this->all_sheets_uri = add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false));

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
        
        add_action('wp_enqueue_scripts', array($this, 'add_css_and_js_to_frontend'));

        add_action('wp_loaded', array($this, 'process_signup_form'));
        add_action('wp_loaded', array($this, 'set_up_filters'));

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
        $this->email_options = get_option( 'pta_volunteer_sus_email_options' );
        $this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );

        
    } // Construct

    public function set_up_filters() {
        // Set up some public output strings used by multiple functions
        $this->task_item_header = apply_filters( 'pta_sus_public_output', __('Task/Item', 'pta_volunteer_sus'), 'task_item_header' );
        $this->start_time_header = apply_filters( 'pta_sus_public_output', __('Start Time', 'pta_volunteer_sus'), 'start_time_header' );
        $this->end_time_header = apply_filters( 'pta_sus_public_output', __('End Time', 'pta_volunteer_sus'), 'end_time_header' );
        $this->item_details_header = apply_filters( 'pta_sus_public_output', __('Item Details', 'pta_volunteer_sus'), 'item_details_header' );
        $this->item_qty_header = apply_filters( 'pta_sus_public_output', __('Item Qty', 'pta_volunteer_sus'), 'item_qty_header' );
        $this->na_text = apply_filters( 'pta_sus_public_output', __('N/A', 'pta_volunteer_sus'), 'not_applicable_text' );
    }

    public function process_signup_form() {
        
        $this->submitted = (isset($_POST['pta_sus_form_mode']) && $_POST['pta_sus_form_mode'] == 'submitted');
        $this->err = 0;
        $this->success = false;
        $this->errors = '';
        $this->messages = '';
        
        // Process Sign-up Form
        if ($this->submitted) {
            // NONCE check
            if ( ! isset( $_POST['pta_sus_signup_nonce'] ) || ! wp_verify_nonce( $_POST['pta_sus_signup_nonce'], 'pta_sus_signup' ) ) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Sorry! Your security nonce did not verify!', 'pta_volunteer_sus').'</p>', 'nonce_error_message' );
                return;
            }
            // Check for spambots
            if (!empty($_POST['website'])) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Oops! You filled in the spambot field. Please leave it blank and try again.', 'pta_volunteer_sus').'</p>', 'spambot_error_message' );
                return;
            }
            //Error Handling
            if (
                empty($_POST['signup_firstname'])
                || empty($_POST['signup_lastname'])
                || empty($_POST['signup_email'])
                || (false == $this->main_options['no_phone'] && empty($_POST['signup_phone']))
                || ("YES" == $_POST['need_details'] && '' == $_POST['signup_item'])
                || ("YES" == $_POST['enable_quantities'] && '' == $_POST['signup_item_qty'])
            ) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Please complete all required fields.', 'pta_volunteer_sus').'</p>', 'required_fields_error_message' );
            }

            // Check for non-allowed characters
            elseif (! $this->data->check_allowed_text(stripslashes($_POST['signup_firstname'])))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Invalid Characters in First Name!  Please try again.', 'pta_volunteer_sus').'</p>', 'firstname_error_message' );
                }
            elseif (! $this->data->check_allowed_text(stripslashes($_POST['signup_lastname'])))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Invalid Characters in Last Name!  Please try again.', 'pta_volunteer_sus').'</p>', 'lastname_error_message' );
                }
            elseif ( !is_email( $_POST['signup_email'] ) )
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Invalid Email!  Please try again.', 'pta_volunteer_sus').'</p>', 'email_error_message' );
                }
            elseif (false == $this->main_options['no_phone'] && preg_match("/[^0-9\-\.\(\)\ ]/", $_POST['signup_phone']))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Invalid Characters in Phone Number!  Please try again.', 'pta_volunteer_sus').'</p>', 'phone_error_message' );
                }
            elseif ( "YES" == $_POST['need_details'] && ! $this->data->check_allowed_text(stripslashes($_POST['signup_item'])))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Invalid Characters in Signup Item!  Please try again.', 'pta_volunteer_sus').'</p>', 'item_details_error_message' );
                }
            elseif ( "YES" == $_POST['enable_quantities'] && (! $this->data->check_numbers($_POST['signup_item_qty']) || (int)$_POST['signup_item_qty'] < 1 || (int)$_POST['available_qty'] < (int)$_POST['signup_item_qty']))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.sprintf(__('Please enter a number between 1 and %d for Item QTY!', 'pta_volunteer_sus'), (int)$_POST['available_qty']).'</p>', 'item_quantity_error_message' );
                }
            elseif (!$this->data->check_date($_POST['signup_date']))
                {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Hidden signup date field is invalid!  Please try again.', 'pta_volunteer_sus').'</p>', 'signup_date_error_message' );
                }
            // If no errors so far, Check for duplicate signups if not allowed
            if (!$this->err && (!isset($_POST['allow_duplicates']) || 'NO' == $_POST['allow_duplicates'])) {
                if( $this->data->check_duplicate_signup( $_GET['task_id'], $_POST['signup_date'], $_POST['signup_firstname'], $_POST['signup_lastname']) ) {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('You are already signed up for this task!', 'pta_volunteer_sus').'</p>', 'signup_duplicate_error_message' );
                }
            }
            // Add Signup
            if (!$this->err) {
                do_action( 'pta_sus_before_add_signup', $_POST, $_GET['task_id'] );
                if ( $this->data->add_signup($_POST, $_GET['task_id']) === false) {
                    $this->err++;
                    $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Error adding signup record.  Please try again.', 'pta_volunteer_sus').'</p>', 'add_signup_database_error_message' );
                } else {
                    global $wpdb;
                    if(!class_exists('PTA_SUS_Emails')) {
                        include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
                    }
                    $emails = new PTA_SUS_Emails();
                    $this->success = true;
                    $this->messages .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus updated">'.__('You have been signed up!', 'pta_volunteer_sus').'</p>', 'signup_success_message' );
                    if ($emails->send_mail($wpdb->insert_id) === false) { 
                        $this->messages .= apply_filters( 'pta_sus_public_output', __('ERROR SENDING EMAIL', 'pta_volunteer_sus'), 'email_send_error_message' ); 
                    }
                }
            }
            
        }
    }

	/**
     * Return the day of month as a string,
     * along with any markup that should accompany it,
     * affecting whether it's the beginning of a month or end of a week.
     * 
     * @param   d   The date to be formatted.
     */
    public function date_with_markup($d) {
        $dateparts = date_parse($d);
        $jd =  cal_to_jd(CAL_GREGORIAN, $dateparts["month"],
                         $dateparts["day"], $dateparts["year"]);
        $dayofweek = jddayofweek($jd);
        $ret = '';

        // On Sunday, we need to end the UL to start a new calendar week.
        if ($dayofweek === 0) {
            //if ($dateparts["day"] > 1)
            if (isset($this->lastday))
                $ret .= '
</ul>
';
            $ret .= '
<ul>
';
        }

        // On the first day of a month, we may need to insert some
        // extra <li> since the first day probably isn't Sunday.
        // But this applies to the first day *we see* of any month,
        // which is not necessarily the first calendar day.
        //else if ($dateparts["day"] == 1) {
        else if (!isset($this->lastmonth)
                 || $dateparts["month"] != $this->lastmonth) {
            for ($d=0; $d < $dayofweek; ++$d)
                $ret .= '<li class="vsus_empty_day">';
        }

        // If we skipped days, then we'll have to insert extra <li>s.
        else if (isset($this->lastdayofweek) &&
                 ($dayofweek != $this->lastdayofweek) &&
                 ($dayofweek != ($this->lastdayofweek+1))) {
            // Are we in a new week?
            if ($dayofweek < $this->lastdayofweek) {
                $ret .= '
</ul>
<ul>
';
                // Don't try to insert as many </ul><ul> as there are
                // intervening weeks; if we miss a week row, that's okay.
                for ($wd=0; $wd < $dayofweek; ++$wd)
                    $ret .= '<li class="vsus_empty_day">';
            }
            // Else we've just skipped a day or so in the same week:
            else {
                for ($i = $this->lastdayofweek+1, $d=$this->lastday+1;
                     $i < $dayofweek; ++$i, ++$d)
                    $ret .= '<li class="vsus_empty_day">' . $d;
            }
        }

        $ret .= '
<li><p class="vsus_date weekdays">' . $dateparts["day"] . '</p>
<p class="vsus_date day_cell">'
            . jddayofweek($jd, 1) . ", " . jdmonthname($jd, 0)
            . ' ' . $dateparts["day"] . '</p>
';
        $this->lastday = $dateparts["day"];
        $this->lastmonth = $dateparts["month"];
        $this->lastdayofweek = $dayofweek;
        return $ret;
    }

	/**
     * Return a time range as the shortest string we can manage,
     * e.g. 9:00-11:00am instead of 9:00 am - 11:00 am.
     *
     * @param   start   Start time
     * @param   end     End time
     */
    public function times_with_markup($startt, $endt) {
        $st = strtotime($startt);
        $et = strtotime($endt);
        $start_ampm = date("A", $st);
        $end_ampm = date("A", $et);
        if ($start_ampm === $end_ampm) $start_ampm = "";
        return '<p class="vsus_time">'
            . date("g:i", $st) . $start_ampm . '-'
            . date("g:i", $et) . $end_ampm
            . '<br />' . PHP_EOL;
    }

	/**
     * Output the volunteer signup form
     * 
     * @param   array   attributes from shortcode call
     */
    public function display_sheet($atts) {
        // Set up some common strings for translation and customizing by customizer extension
        $title_header = apply_filters( 'pta_sus_public_output', __('Title', 'pta_volunteer_sus'), 'title_header' );
        $start_date_header = apply_filters( 'pta_sus_public_output', __('Start Date', 'pta_volunteer_sus'), 'start_date_header' );
        $end_date_header = apply_filters( 'pta_sus_public_output', __('End Date', 'pta_volunteer_sus'), 'end_date_header' );
        $open_spots_header = apply_filters( 'pta_sus_public_output', __('Open Spots', 'pta_volunteer_sus'), 'open_spots_header' );
        $date_header = apply_filters( 'pta_sus_public_output', __('Date', 'pta_volunteer_sus'), 'date_header' );
        $no_contact_message = apply_filters( 'pta_sus_public_output', __('No Event Chair contact info provided', 'pta_volunteer_sus'), 'no_contact_message' );
        $contact_label = apply_filters( 'pta_sus_public_output', __('Contact:', 'pta_volunteer_sus'), 'contact_label' );


        do_action( 'pta_sus_before_process_shortcode', $atts );
        $return = '';
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode'] ) {
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Volunteer Sign-Up Sheets are in TEST MODE', 'pta_volunteer_sus').'</p>', 'admin_test_mode_message' );
            } elseif (is_page( $this->main_options['volunteer_page_id'] )) {
                $message = esc_html($this->main_options['test_mode_message']);
                return $message;
            } else {
                return;
            }
        }
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required'] ) {
            if (!is_user_logged_in()) {
                $message = '<p class="pta-sus error">' . esc_html($this->main_options['login_required_message']) . '</p>';
                return $message;
            }
        }
        extract( shortcode_atts( array(
            'id' => false,
            'date' => false,
            'list_title' => __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus'),
        ), $atts, 'pta_sign_up_sheet' ) );

        // Allow plugins or themes to modify shortcode parameters
        $id = apply_filters( 'pta_sus_shortcode_id', $id );
        if('' == $id) $id = false;
        $date = apply_filters( 'pta_sus_shortcode_date', $date );
        if('' == $date) $date = false;
        if('' == $list_title) $list_title = __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus');
        $list_title = apply_filters( 'pta_sus_shortcode_list_title', $list_title );

        
        if ($id === false && !empty($_GET['sheet_id'])) $id = (int)$_GET['sheet_id'];

        if ($date === false && !empty($_GET['date'])) {
            // Make sure it's a valid date in our format first - Security check
            if ($this->data->check_date($_GET['date'])) {
                $date = $_GET['date'];
            }
        } 

        $return = apply_filters( 'pta_sus_before_display_sheets', $return, $id, $date );
        do_action( 'pta_sus_begin_display_sheets', $id, $date );
        
        if ($id === false) {
            $show_hidden = false;
            $hidden = '';
            // Allow admin or volunteer managers to view hidden sign up sheets
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $show_hidden = true;
                $hidden = '<br/><span class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', '(--'.__('Hidden!', 'pta_volunteer_sus').'--)', 'hidden_notice' ).'</span>';
            }
            
            // Display all active
            $return .= '<h2 class="pta-sus-list-title">'.apply_filters( 'pta_sus_public_output', esc_html($list_title), 'sheet_list_title' ).'</h2>';
            $sheets = $this->data->get_sheets(false, true, $show_hidden);
            $sheets = array_reverse($sheets);

            // Move ongoing sheets to bottom of list if that setting is checked
            if ($this->main_options['show_ongoing_last']) {
                // Move ongoing events to end of our sheets array
                foreach ($sheets as $key => $sheet) {
                    if ('Ongoing' == $sheet->type) {
                        $move_me = $sheet;
                        unset($sheets[$key]);
                        $sheets[] = $move_me;
                    }
                }
            }

            // Allow plugins or themes to modify retrieved sheets
            $sheets = apply_filters( 'pta_sus_display_active_sheets', $sheets, $atts );

            if (empty($sheets)) {
                $return .= '<p>'.apply_filters( 'pta_sus_public_output', __('No sheets currently available at this time.', 'pta_volunteer_sus'), 'no_sheets_message' ).'</p>';
            } else {
                $return .= apply_filters( 'pta_sus_before_sheet_list_table', '' );
                $return .= '<div class="pta-sus-sheets main">
                    <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.esc_html( $title_header ).'</th>';
                $return .= apply_filters( 'pta_sus_sheet_list_table_header_after_title', '', $atts );
                $return .=     '<th class="column-date">'.esc_html( $start_date_header ).'</th>
                                <th class="column-date">'.esc_html( $end_date_header ).'</th>
                                <th class="column-open_spots">'.esc_html( $open_spots_header ).'</th>
                                <th class="column-view_link">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                        ';
                        foreach ($sheets AS $sheet) {
                            if ( 'Single' == $sheet->type ) {
                                // if a date was passed in, skip any sheets not on that date
                                if($date && $date != $sheet->first_date) continue;
                            } else {
                                // Recurring or Multi-day sheets
                                $dates = $this->data->get_all_task_dates($sheet->id);
                                if($date && !in_array($date, $dates)) continue;
                            }
                            if ( '1' == $sheet->visible) {
                                $is_hidden = '';
                            } else {
                                $is_hidden = $hidden;
                            }
                            $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
                            $sheet_args = array('sheet_id' => $sheet->id, 'date' => false, 'signup_id' => false, 'task_id' => false);
                            $sheet_url = apply_filters('pta_sus_view_sheet_url', add_query_arg($sheet_args), $sheet);
                            $ongoing_label = apply_filters( 'pta_sus_public_output', __('Ongoing', 'pta_volunteer_sus'), 'ongoing_event_type_start_end_label' );
                            $view_signup_text = apply_filters( 'pta_sus_public_output', __('View &amp; sign-up', 'pta_volunteer_sus'), 'view_and_signup_link_text' );
                            $sheet_filled_text = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'sheet_filled_text' );
                            $return .= '
                                <tr'.(($open_spots === 0) ? ' class="filled"' : '').'>
                                    <td class="column-title"><a href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a>'.$is_hidden.'</td>';
                            $return .= apply_filters( 'pta_sus_sheet_list_table_content_after_title', '', $sheet, $atts );
                            $return .= '<td class="column-date">'.(($sheet->first_date == '0000-00-00') ? esc_html( $ongoing_label ) : date_i18n(get_option('date_format'), strtotime($sheet->first_date))).'</td>
                                    <td class="column-date">'.(($sheet->last_date == '0000-00-00') ? esc_html( $ongoing_label ) : date_i18n(get_option('date_format'), strtotime($sheet->last_date))).'</td>
                                    <td class="column-open_spots">'.(int)$open_spots.'</td>
                                    <td class="column-view_link">'.(($open_spots > 0) ? '<a href="'.esc_url($sheet_url).'">'.esc_html( $view_signup_text ).' &raquo;</a>' : '&#10004; '.esc_html( $sheet_filled_text )).'</td>
                                </tr>
                            ';                           
                        }
                        $return .= '
                        </tbody>
                    </table>
                    </div>
                ';
                $return .= apply_filters( 'pta_sus_after_sheet_list_table', '' );
            }
            
            // If current user has signed up for anything, list their signups and allow them to edit/clear them
            // If they aren't logged in, prompt them to login to see their signup info
            if ( !is_user_logged_in() ) {
                if (!$this->main_options['disable_signup_login_notice']) {
                    $return .= '<p>'. apply_filters( 'pta_sus_public_output', __('Please login to view and edit your volunteer sign ups.', 'pta_volunteer_sus'), 'user_not_loggedin_signups_list_message' ).'</p>';
                }
            } else {
                $current_user = wp_get_current_user();
                if ( !($current_user instanceof WP_User) )
                return;

                // Check if they clicked on a CLEAR link
                // Perhaps add some sort of confirmation, maybe with jQuery?
                if (isset($_GET['signup_id'])) {
                    // Make sure the signup exists first
                    if (null == $this->data->get_signup((int)$_GET['signup_id'])) {
                        $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Not a valid signup!', 'pta_volunteer_sus'), 'clear_invalid_signup_error_message' ).'</p>';
                    } else {
                        // Send cleared emails
                        if(!class_exists('PTA_SUS_Emails')) {
                            include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
                        }
                        $emails = new PTA_SUS_Emails();
                        $emails->send_mail((int)$_GET['signup_id'], $reminder=false, $clear=true);
                        $cleared = $this->data->delete_signup((int)$_GET['signup_id']);
                        if ($cleared) {
                            $return .= '<p class="pta-sus updated">'.apply_filters( 'pta_sus_public_output', __('Signup Cleared', 'pta_volunteer_sus'), 'signup_cleared_message' ).'</p>';
                        } else {
                            $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('ERROR clearing signup!', 'pta_volunteer_sus'), 'error_clearing_signup_message' ).'</p>';
                        }
                    }
                }

                $signups = apply_filters( 'pta_sus_user_signups', $this->data->get_user_signups($current_user->ID) );
                if ($signups) {
                    $return .= apply_filters( 'pta_sus_before_user_signups_list_headers', '' );
                    $return .= '<h3>'.apply_filters( 'pta_sus_public_output', __('You have signed up for the following', 'pta_volunteer_sus'), 'user_signups_list_headers_h3' ).'</h3>';
                    $return .= '<h4>'.apply_filters( 'pta_sus_public_output', __('Click on Clear to remove yourself from a signup.', 'pta_volunteer_sus'), 'user_signups_list_headers_h4' ).'</h4>';
                    $return .= apply_filters( 'pta_sus_before_user_signups_list_table', '' );
                    $return .= '<div class="pta-sus-sheets user">
                        <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.esc_html($title_header).'</th>
                                <th class="column-date">'.esc_html($date_header).'</th>
                                <th class="column-task">'.esc_html($this->task_item_header).'</th>
                                <th class="column-time" style="text-align:right;">'.esc_html($this->start_time_header).'</th>
                                <th class="column-time" style="text-align:right;">'.esc_html($this->end_time_header).'</th>
                                <th class="column-details" style="text-align:center;">'.esc_html($this->item_details_header).'</th>
                                <th class="column-qty" style="text-align:center;">'.esc_html($this->item_qty_header).'</th>
                                <th class="column-clear_link">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>';
                    foreach ($signups as $signup) {
                            
                        if ( true == $signup->clear && ( 0 == $signup->clear_days || $signup->signup_date == "0000-00-00" 
                            || ( strtotime( $signup->signup_date ) - current_time( 'timestamp' ) > ((int)$signup->clear_days * 60 * 60 * 24) ) ) ) {
                            $clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
                            $clear_url = add_query_arg($clear_args);
                            $clear_text = apply_filters( 'pta_sus_public_output', __('Clear', 'pta_volunteer_sus'), 'clear_signup_link_text');
                        } else {
                            $clear_url = '';
                            $clear_text = '';
                        }
                        
                        $return .= '<tr>
                            <td>'.esc_html($signup->title).'</td>
                            <td>'.(($signup->signup_date == "0000-00-00") ? esc_html($this->na_text) : date_i18n(get_option("date_format"), strtotime($signup->signup_date))).'</td>
                            <td>'.esc_html($signup->task_title).'</td>
                            <td style="text-align:right;">'.(("" == $signup->time_start) ? esc_html($this->na_text) : date_i18n(get_option("time_format"), strtotime($signup->time_start)) ).'</td>
                            <td style="text-align:right;">'.(("" == $signup->time_end) ? esc_html($this->na_text) : date_i18n(get_option("time_format"), strtotime($signup->time_end)) ).'</td>
                            <td style="text-align:center;">'.((" " !== $signup->item) ? esc_html($signup->item) : esc_html($this->na_text) ).'</td>
                            <td style="text-align:center;">'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($this->na_text) ).'</td>
                            <td style="text-align:right;"><a href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a></td>
                        </tr>';
                    }
                    $return .= '</tbody></table></div>';
                    $return .= apply_filters( 'pta_sus_after_user_signups_list_table', '' );
                }
            }

        } else {
            $return .= $this->messages;
            // Display Individual Sheet
            $sheet = apply_filters( 'pta_sus_display_individual_sheet', $this->data->get_sheet($id), $id );
            if ($sheet === false) {
                $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("Sign-up sheet not found.", 'pta_volunteer_sus'), 'sheet_not_found_error_message' ).'</p>';
                return $return;
            } else {
                // Check is the sheet is visible and don't show unless it's an admin user
                if ( false == $sheet->visible && !current_user_can( 'manage_signup_sheets' ) ) return;
                // Allow extensions to choose if they want to show header info if not on the main volunteer page
                $show_headers = apply_filters( 'pta_sus_show_sheet_headers', $show = false, $sheet );
                $return .= apply_filters( 'pta_sus_before_display_single_sheet', '', $sheet );
                // *****************************************************************************
                // If it's not the main
                // volunteer page, then change the header info -- don't show "view all..." and
                // don't show title and chair... instead just make a simple heading
                if ( is_page( $this->main_options['volunteer_page_id'] ) || $show_headers ) {
                    // TODO 
                    // USE THE ANTISPAMBOT WP FEATURE TO OBFUSCATE THE EMAIL ADDRESSES AND EITHER PUT THEM BACK TOGETHER
                    // SEPARATED BY COMMAS, OR USE A BCC FOR OTHER EMAIL ADDRESSES AFTER THE FIRST
                    // 
                    // AFTER MAKING PTA MEMBER DIRECTORY A CLASS, WE CAN ALSO CHECK IF IT EXISTS
                    if( isset($this->integration_options['enable_member_directory']) && true === $this->integration_options['enable_member_directory'] && function_exists('pta_member_directory_init') && '' != $sheet->position ) {
                        // Create Contact Form link
                        if($position = get_term_by( 'slug', $sheet->position, 'member_category' )) {
                            if ( isset($this->integration_options['contact_page_id']) && 0 < $this->integration_options['contact_page_id']) {               
                                $contact_url = get_permalink( $this->integration_options['contact_page_id'] ) . '?id=' . esc_html($sheet->position);
                                $display_chair = esc_html($contact_label) . ' <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
                            } elseif ( isset($this->integration_options['directory_page_id']) && 0 < $this->integration_options['directory_page_id']) {               
                                $contact_url = get_permalink( $this->integration_options['directory_page_id'] ) . '?id=' . $sheet->position;
                                $display_chair = esc_html($contact_label)  . ' <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
                            } else {
                                $display_chair = esc_html( $no_contact_message );
                            }
                        } else {
                            $display_chair = esc_html( $no_contact_message );
                        }
                        
                    } else {
                        $chair_names = $this->data->get_chair_names_html($sheet->chair_name);
                        // Check if there is more than one chair name to display either Chair or Chairs
                        $names = str_getcsv($sheet->chair_name);
                        $count = count($names);
                        if ( $count > 1) {
                            $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chairs:', 'pta_volunteer_sus'), 'event_chairs_label_plural') .' <a href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
                        } elseif ( 1 == $count && '' != $sheet->chair_name && '' != $sheet->chair_email ) {
                            $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chair:', 'pta_volunteer_sus'), 'event_chair_label_singular') .' <a href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
                        } else {
                            $display_chair = esc_html( $no_contact_message );
                        }
                    }

                    $display_chair = apply_filters( 'pta_sus_display_chair_contact', $display_chair, $sheet );
                    $view_all_text = apply_filters('pta_sus_public_output', __('View all Sign-up Sheets', 'pta_volunteer_sus'), 'view_all_sheets_link_text');
                    $return .= '
                        <p><a href="'.esc_url($this->all_sheets_uri).'">&laquo; '.esc_html( $view_all_text ).'</a></p>
                        <div class="pta-sus-sheet">
                            <h2>'.esc_html($sheet->title).'</h2>
                    ';
                    if ( false == $this->main_options['hide_contact_info'] ) {
                        $return .= '<h2>'.$display_chair.'</h2>';
                    }
                } else {
                    $return .= '<div class="pta-sus-sheet">';
                }
                if ( false == $sheet->visible && current_user_can( 'manage_signup_sheets' ) ) {
                    $return .= '<p class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', __('This sheet is currently hidden from the public.', 'pta_volunteer_sus'), 'sheet_hidden_message' ).'</p>';
                }
                
                // Display Sign-up Form
			    if (!$this->submitted || $this->err) {
				    if (isset($_GET['task_id']) && $date) {
                        do_action('pta_sus_before_display_signup_form', $_GET['task_id'], $date );
					    return $this->errors . $this->display_signup_form($_GET['task_id'], $date);
				    }
			    }
			    
			    // Sheet Details
                // Need to escape/sanitize all output to screen
			    if (!$this->submitted || $this->success || $this->err) {
                    $future_dates = false;
                    $task_dates = $this->data->get_all_task_dates($sheet->id);
                    foreach ($task_dates as $tdate) {
                        if($tdate >= current_time('Y-m-d') || "0000-00-00" == $tdate) {
                            $future_dates = true;
                            break;
                        }
                    }
                    
                    // Make sure there are some future dates before showing anything
                    if($future_dates) {
                        // Only show details if there is something to show
                        if('' != $sheet->details) {
                            $return .= '<h3>'.apply_filters( 'pta_sus_public_output', __('DETAILS:', 'pta_volunteer_sus'), 'sheet_details_heading' ).'</h3>';
                            $return .= wp_kses_post($sheet->details);
                        }
                        $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
                        if ($open_spots > 0) {
                            $return .= '<h3>'.apply_filters( 'pta_sus_public_output', __('Sign up below...', 'pta_volunteer_sus'), 'sign_up_below' ).'</h3>';               
                        } else {
                            $return .= '<h3>'.apply_filters( 'pta_sus_public_output', __('All spots have been filled.', 'pta_volunteer_sus'), 'sheet_all_spots_filled' ).'</h3>';
                        }
                        
                        $task_dates = apply_filters( 'pta_sus_sheet_task_dates', $task_dates, $sheet->id );
                        $return .= '
<div class="vsus_container">
<ul class="weekdays">
<li>Sun</li>
<li>Mon</li>
<li>Tues</li>
<li>Wed</li>
<li>Thur</li>
<li>Fri</li>
<li>Sat</li>
</ul>
<ul>';
/*
<li>
<div class="date day_cell"><span class="day">Sun,</span> <span class="month">Nov</span> 1</div>';
*/
                        foreach ($task_dates as $tdate) {
                            if( "0000-00-00" != $tdate && $tdate < current_time('Y-m-d')) continue; // Skip dates that have passed already
                            if( "0000-00-00" != $tdate ) {
                                $return .= $this->date_with_markup($tdate);
                            }                           
                            $return .= $this->display_task_list($sheet->id, $tdate);
                        }
                    }

                    $return .= '</div>';
	            }
            }
        }
        $return .= apply_filters( 'pta_sus_after_display_sheets', '', $id, $date );
        return $return;
    } // Display Sheet

    public function display_task_list($sheet_id, $date) {
        // Tasks
        $return = '';
        if (!($tasks = apply_filters('pta_sus_public_sheet_get_tasks', $this->data->get_tasks($sheet_id, $date), $sheet_id, $date) ) ) {
            $return .= '<p>'.apply_filters( 'pta_sus_public_output', __('No tasks were found for ', 'pta_volunteer_sus'), 'no_tasks_found_for_date' ) . mysql2date( get_option('date_format'), $tdate, $translate = true ).'</p>';
        } else {
            $show_details = false;
            $show_qty = false;
            foreach ($tasks as $task) {
                if ( 'YES' == $task->need_details ) {
                    $show_details = true;
                }
                if ( 'YES' == $task->enable_quantities ) {
                    $show_qty = true;
                }
            }
            $return .= apply_filters( 'pta_sus_before_task_list', '', $tasks );
            $cur_time_range = '';
                    foreach ($tasks AS $task) {
                        $task_dates = explode(',', $task->dates);
                        // Don't show tasks that don't include our date, if one was passed in
                        if ($date && !in_array($date, $task_dates)) continue;

                        $task_args = array('sheet_id' => $sheet_id, 'task_id' => $task->id, 'date' => $date, 'signup_id' => false);
                        if (is_page($this->main_options['volunteer_page_id'])) {         
                            $task_url = add_query_arg($task_args);
                        } else {
                            $task_query = http_build_query($task_args);
                            $task_url = get_permalink( $this->main_options['volunteer_page_id'] ) . '?' . $task_query;
                        }
                        $task_url = apply_filters( 'pta_sus_task_signup_url', $task_url, $task, $sheet_id, $date );
                        
                        $i=1;
                        $signups = apply_filters( 'pta_sus_task_get_signups', $this->data->get_signups($task->id, $date), $task->id, $date);
                        
                        foreach ($signups AS $signup) {
                            if ($i == $task->qty) {
                                $return .= '<tr class="pta-sus-tasks-bb">';
                            }
                            if (1 == $i) {
                                $timerange = $this->times_with_markup(
                                    $task->time_start, $task->time_end);
                                if ($timerange !== $cur_time_range) {
                                    $return .= $timerange;
                                    $cur_time_range = $timerange;
                                }
                            }
                            if($this->main_options['hide_volunteer_names']) {
                                $display_signup = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'task_spot_filled_message' );
                            } else {
                                $display_signup = esc_html($signup->firstname).' '.esc_html(substr($signup->lastname, 0, 1)) . '.';
                            }
                            // hook to allow others to modify how the signed up names are displayed
                            $display_signup = apply_filters( 'pta_sus_display_signup_name', $display_signup, $signup );
                            $return .= '#'.$i.': '.$display_signup.'<br />' . PHP_EOL;
                            if ($show_details) {
                                $return .= esc_html($signup->item) . PHP_EOL;
                            }
                            if ($show_qty) {
                                    $return .= ("YES" === $task->enable_quantities ? (int)($signup->item_qty) : "").'<br />';
                            }
                            if ('YES' === $task->enable_quantities) {
                                $i += $signup->item_qty;
                            } else {
                                $i++;
                            }                           
                        }
                        for ($i=$i; $i<=$task->qty; $i++) {
                            if (1 == $i) {
                                $timerange = $this->times_with_markup(
                                    $task->time_start, $task->time_end);
                                if ($timerange !== $cur_time_range) {
                                    $return .= $timerange;
                                    $cur_time_range = $timerange;
                                }
                            }
                            $return .= '#' . $i . ': <a href="'
                                . esc_url($task_url) . '">'
                                . apply_filters( 'pta_sus_public_output',
                                                 __('Sign up ',
                                                    'pta_volunteer_sus'),
                                                'task_sign_up_link_text' )
                                . '&raquo;</a><br />' . PHP_EOL;
                        }
                    }
            $return .= apply_filters( 'pta_sus_after_task_list', '', $tasks );
            return $return;
        }
    } // Display task list

	public function display_signup_form($task_id, $date) {	
        $task = apply_filters( 'pta_sus_public_signup_get_task', $this->data->get_task($task_id), $task_id);
        do_action( 'pta_sus_before_signup_form', $task, $date );
        if ("0000-00-00" == $date) {
            $show_date = false;
        } else {
            $show_date = date_i18n(get_option('date_format'), strtotime($date));
        }
        $form = '<div class="pta-sus-sheets signup-form">';
        $form .= apply_filters( 'pta_sus_signup_page_before_form_title', '', $task, $date );
        $form .= '<h3>'.apply_filters( 'pta_sus_public_output', __('Sign Up', 'pta_volunteer_sus'), 'sign_up_form_heading' ).'</h3>';
        $form .= '<h4>'. apply_filters( 'pta_sus_public_output', __('You are signing up for... ', 'pta_volunteer_sus'), 'you_are_signing_up_for' ).'<br/><strong>'.esc_html($task->title).'</strong> ';
        if ($show_date) {
            $form .= sprintf(__('on %s', 'pta_volunteer_sus'), $show_date);
        }
        $form .= '</h4>';
        if ('' != $task->time_start) {
            $form .= '<span class="time_start">'.esc_html($this->start_time_header) . ': '. date_i18n(get_option("time_format"), strtotime($task->time_start)) . '</span><br/>';
        }
        if ('' != $task->time_end) {
            $form .= '<span class="time_end">'.esc_html($this->end_time_header) . ': '. date_i18n(get_option("time_format"), strtotime($task->time_end)) . '</span><br/>';
        }
        $firstname_label = apply_filters( 'pta_sus_public_output', __('First Name', 'pta_volunteer_sus'), 'firstname_label' );
        $lastname_label = apply_filters( 'pta_sus_public_output', __('Last Name', 'pta_volunteer_sus'), 'lastname_label' );
        $email_label = apply_filters( 'pta_sus_public_output', __('E-mail', 'pta_volunteer_sus'), 'email_label' );
        $phone_label = apply_filters( 'pta_sus_public_output', __('Phone', 'pta_volunteer_sus'), 'phone_label' );

        $form .= apply_filters( 'pta_sus_signup_form_before_form_fields', '<br/>', $task, $date );
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( !($current_user instanceof WP_User) )
            return;           
            // Prefill user data if they are signed in
            $form .= '
			<form name="pta_sus_signup_form" method="post" action="">
                <input type="hidden" name="signup_user_id" value="'.$current_user->ID.'" />
				<p>
					<label for="signup_firstname">'.$firstname_label.'</label>
					<input type="text" id="signup_firstname" name="signup_firstname" value="'. esc_attr($current_user->user_firstname) .'" />
				</p>
				<p>
					<label for="signup_lastname">'.$lastname_label.'</label>
					<input type="text" id="signup_lastname" name="signup_lastname" value="'. esc_attr($current_user->user_lastname) .'" />
				</p>
				<p>
					<label for="signup_email">'.$email_label.'</label>
					<input type="text" id="signup_email" name="signup_email" value="'. esc_attr($current_user->user_email) .'" />
				</p>';
            if( false == $this->main_options['no_phone'] ) {
                // Using Woocommerce to handle site registrations stores a "billing_phone" user meta field
                // since we set single to "true", this will return an empty string if the field doesn't exist
                $phone = apply_filters('pta_sus_user_phone', get_user_meta( $current_user->ID, 'billing_phone', true ), $current_user );
                $form .= '
                <p>
                    <label for="signup_phone">'.$phone_label.'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'. esc_attr($phone).'" />
                </p>';
            }
        } else { 
        	// If not signed in, get the user data
            if (false == $this->main_options['disable_signup_login_notice']) {
                $form .= '<p>'.apply_filters( 'pta_sus_public_output', __('If you have an account, it is strongly recommended that you <strong>login before you sign up</strong> so that you can view and edit all your signups.', 'pta_volunteer_sus'), 'signup_login_notice' ).'</p>';
            }
            $form .= '
			<form name="pta_sus_signup_form" method="post" action="">
				<p>
					<label for="signup_firstname">'.$firstname_label.'</label>
					<input type="text" id="signup_firstname" name="signup_firstname" value="'.((isset($_POST['signup_firstname'])) ? esc_attr($_POST['signup_firstname']) : '').'" />
				</p>
				<p>
					<label for="signup_lastname">'.$lastname_label.'</label>
					<input type="text" id="signup_lastname" name="signup_lastname" value="'.((isset($_POST['signup_lastname'])) ? esc_attr($_POST['signup_lastname']) : '').'" />
				</p>
				<p>
					<label for="signup_email">'.$email_label.'</label>
					<input type="text" id="signup_email" name="signup_email" value="'.((isset($_POST['signup_email'])) ? esc_attr($_POST['signup_email']) : '').'" />
				</p>';
            if( false == $this->main_options['no_phone'] ) {
                $form .= '
                <p>
                    <label for="signup_phone">'.$phone_label.'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'.((isset($_POST['signup_phone'])) ? esc_attr($_POST['signup_phone']) : '').'" />
                </p>';
            }
        }

        $form .= apply_filters( 'pta_sus_signup_form_before_details_field', '', $task, $date );

        // Get the remaining fields, whether or not they are signed in

        // If details are needed for the task, show the field to fill in details.
        // Otherwise don't show the field, but fill it with a blank space
        if ($task->need_details == "YES") {
            $form .= '
            <p>
			    <label for="signup_item">'.esc_html($task->details_text).'</label>
			    <input type="text" id="signup_item" name="signup_item" value="'.((isset($_POST['signup_item'])) ? esc_attr($_POST['signup_item']) : '').'" />
                <input type="hidden" name="need_details" value="YES" />
		    </p>';
        } else {
            $form .= '<input type="hidden" name="signup_item" value=" " />
            <input type="hidden" name="need_details" value="NO" />'; 
        }
        if ($task->enable_quantities == "YES") {
            $form .= '<p>';
            $available = $this->data->get_available_qty($task_id, $date, $task->qty);
            if ($available > 1) {
                $form .= '<label for="signup_item_qty">'.esc_html( apply_filters( 'pta_sus_public_output', sprintf(__('Item QTY (1 - %d): ', 'pta_volunteer_sus'), (int)$available), 'item_quantity_input_label', (int)$available ) ).'</label>
                <input type="text" id="signup_item_qty" name="signup_item_qty" value="'.((isset($_POST['signup_item_qty'])) ? (int)($_POST['signup_item_qty']) : '').'" />';
            } elseif ( 1 == $available) {
                $form .= '<strong>'.apply_filters( 'pta_sus_public_output', __('Only 1 remaining! Your quantity will be set to 1.', 'pta_volunteer_sus'), 'only_1_remaining' ).'</strong>';
                $form .= '<input type="hidden" name="signup_item_qty" value="1" />';
            }
            $form .= '<input type="hidden" name="enable_quantities" value="YES" />
                    <input type="hidden" name="available_qty" value="'.esc_attr($available).'" />
            </p>';
        } else {
            $form .= '<input type="hidden" name="signup_item_qty" value="1" />
            <input type="hidden" name="enable_quantities" value="NO" />'; 
        }

        $form .= apply_filters( 'pta_sus_signup_form_after_details_field', '', $task, $date );

        // Spam check and form submission
        $go_back_args = array('task_id' => false, 'date' => $date, 'sheet_id' => $_GET['sheet_id']);
        $go_back_url = apply_filters( 'pta_sus_signup_goback_url', add_query_arg($go_back_args) );
        $form .= '
			<div style="visibility:hidden"> 
	            <input name="website" type="text" size="20" />
	        </div>
	        <p class="submit">
	            <input type="hidden" name="signup_date" value="'.esc_attr($date).'" />
                <input type="hidden" name="allow_duplicates" value="'.$task->allow_duplicates.'" />
	            <input type="hidden" name="signup_task_id" value="'.esc_attr($_GET['task_id']).'" />
	        	<input type="hidden" name="pta_sus_form_mode" value="submitted" />
	        	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr( apply_filters( 'pta_sus_public_output', __('Sign me up!', 'pta_volunteer_sus'), 'signup_button_text' ) ).'" />
	            <a href="'.esc_url($go_back_url).'">'.esc_html( apply_filters( 'pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta_volunteer_sus'), 'go_back_to_signup_sheet_text' ) ).'</a>
	        </p>
            ' . wp_nonce_field('pta_sus_signup','pta_sus_signup_nonce') . '
		</form>
		';
        $form .= '</div>';
        return $form;       
	} // Display Sign up form

	/**
    * Enqueue plugin css and js files
    */
    public function add_css_and_js_to_frontend() {
        wp_register_style('pta-sus-style', plugins_url('../assets/css/style.css', __FILE__));
        wp_enqueue_style('pta-sus-style');
    }

} // End of class
/* EOF */