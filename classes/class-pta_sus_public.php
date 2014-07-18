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
    
    public function __construct() {
        $this->data = new PTA_SUS_Data();
        
        $plugin = plugin_basename(__FILE__);
        
        $this->plugin_path = dirname(__FILE__).'/';

        $this->all_sheets_uri = add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false));

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
        
        add_action('wp_enqueue_scripts', array($this, 'add_css_and_js_to_frontend'));

        add_action('wp_loaded', array($this, 'process_signup_form'));

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
        $this->email_options = get_option( 'pta_volunteer_sus_email_options' );
        $this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
    } // Construct

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
                || empty($_POST['signup_phone'])
            ) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Please complete all required fields.', 'pta_volunteer_sus').'</p>', 'required_fields_error_message' );
            }

            if("YES" == $_POST['need_details'] && '' == $_POST['signup_item']) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Please enter the item you are bringing.', 'pta_volunteer_sus').'</p>', 'item_details_required_error_message' );
            }
            if("YES" == $_POST['enable_quantities'] && '' == $_POST['signup_item_qty']) {
                $this->err++;
                $this->errors .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Please enter the item quantity you are bringing.', 'pta_volunteer_sus').'</p>', 'item_quantity_required_error_message' );
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
            elseif (preg_match("/[^0-9\-\.\(\)\ ]/", $_POST['signup_phone']))
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
     * Output the volunteer signup form
     * 
     * @param   array   attributes from shortcode call
     */
    public function display_sheet($atts) {
        do_action( 'pta_sus_before_process_shortcode', $atts );
        $return = '';
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode'] ) {
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('Volunteer Sign-Up Sheets are in TEST MODE', 'pta_volunteer_sus').'</p>', 'admin_test_mode_message' );
            } elseif (is_page( $this->main_options['volunteer_page_id'] )) {
                $message = apply_filters( 'pta_sus_public_output', esc_html($this->main_options['test_mode_message']), 'public_test_mode_message' );
                return $message;
            } else {
                return;
            }
        }
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required'] ) {
            if (!is_user_logged_in()) {
                $message = apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">' . esc_html($this->main_options['login_required_message']) . '</p>', 'login_required_message' );
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
                $hidden = apply_filters( 'pta_sus_public_output', '<br/><span class="pta-sus-hidden">(--'.__('Hidden!', 'pta_volunteer_sus').'--)</span>', 'hidden_notice' );
            }
            
            // Display all active
            $return .= apply_filters( 'pta_sus_public_output', '<h2>'.esc_html($list_title).'</h2>', 'sheet_list_title' );
            $sheets = $this->data->get_sheets(false, true, $show_hidden);
            $sheets = array_reverse($sheets);

            // Allow plugins or themes to modify retrieved sheets
            $sheets = apply_filters( 'pta_sus_display_active_sheets', $sheets, $atts );

            if (empty($sheets)) {
                $return .= apply_filters( 'pta_sus_public_output', '<p>'.__('No sheets currently available at this time.', 'pta_volunteer_sus').'</p>', 'no_sheets_message' );
            } else {
                $return .= apply_filters( 'pta_sus_before_sheet_list_table', '' );
                $return .= '
                    <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.__('Title', 'pta_volunteer_sus').'</th>';
                $return .= apply_filters( 'pta_sus_sheet_list_table_header_after_title', '', $atts );
                $return .=     '<th class="column-date">'.__('Start Date', 'pta_volunteer_sus').'</th>
                                <th class="column-date">'.__('End Date', 'pta_volunteer_sus').'</th>
                                <th class="column-open_spots">'.__('Open Spots', 'pta_volunteer_sus').'</th>
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
                            $return .= '
                                <tr'.(($open_spots === 0) ? ' class="filled"' : '').'>
                                    <td class="column-title"><a href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a>'.$is_hidden.'</td>';
                            $return .= apply_filters( 'pta_sus_sheet_list_table_content_after_title', '', $sheet, $atts );
                            $return .= '<td class="column-date">'.(($sheet->first_date == '0000-00-00') ? __('Ongoing', 'pta_volunteer_sus') : date_i18n(get_option('date_format'), strtotime($sheet->first_date))).'</td>
                                    <td class="column-date">'.(($sheet->last_date == '0000-00-00') ? __('Ongoing', 'pta_volunteer_sus') : date_i18n(get_option('date_format'), strtotime($sheet->last_date))).'</td>
                                    <td class="column-open_spots">'.(int)$open_spots.'</td>
                                    <td class="column-view_link">'.(($open_spots > 0) ? '<a href="'.esc_url($sheet_url).'">'.__('View &amp; sign-up', 'pta_volunteer_sus').' &raquo;</a>' : '&#10004; '.__('Filled','pta_volunteer_sus')).'</td>
                                </tr>
                            ';                           
                        }
                        $return .= '
                        </tbody>
                    </table>
                ';
                $return .= apply_filters( 'pta_sus_after_sheet_list_table', '' );
            }
            
            // If current user has signed up for anything, list their signups and allow them to edit/clear them
            // If they aren't logged in, prompt them to login to see their signup info
            if (!is_user_logged_in()) {
                $return .= apply_filters( 'pta_sus_public_output', '<p>'.__('Please login to view and edit your volunteer sign ups.', 'pta_volunteer_sus').'</p>', 'user_not_loggedin_signups_list_message' );
            } else {
                $current_user = wp_get_current_user();
                if ( !($current_user instanceof WP_User) )
                return;

                // Check if they clicked on a CLEAR link
                // Perhaps add some sort of confirmation, maybe with jQuery?
                if (isset($_GET['signup_id'])) {
                    $cleared = $this->data->delete_signup((int)$_GET['signup_id']);
                    if ($cleared) {
                        $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus updated">'.__('Signup Cleared', 'pta_volunteer_sus').'</p>', 'signup_cleared_message' );
                    } else {
                        $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__('ERROR clearing signup!', 'pta_volunteer_sus').'</p>', 'error_clearing_signup_message' );
                    }
                }

                $signups = apply_filters( 'pta_sus_user_signups', $this->data->get_user_signups($current_user->ID) );
                if ($signups) {
                    $return .= apply_filters( 'pta_sus_before_user_signups_list_headers', '' );
                    $return .= apply_filters( 'pta_sus_public_output', '
                        <h3>'.__('You have signed up for the following', 'pta_volunteer_sus').'</h3>
                        <h4>'.__('Click on Clear to remove yourself from a signup.', 'pta_volunteer_sus').'</h4>', 'user_signups_list_headers' );
                    $return .= apply_filters( 'pta_sus_before_user_signups_list_table', '' );
                    $return .= '
                        <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.__('Title', 'pta_volunteer_sus').'</th>
                                <th class="column-date">'.__('Date', 'pta_volunteer_sus').'</th>
                                <th class="column-task">'.__('Task/Item', 'pta_volunteer_sus').'</th>
                                <th class="column-time" style="text-align:right;">'.__('Start Time', 'pta_volunteer_sus').'</th>
                                <th class="column-time" style="text-align:right;">'.__('End Time', 'pta_volunteer_sus').'</th>
                                <th class="column-details" style="text-align:center;">'.__('Item Details', 'pta_volunteer_sus').'</th>
                                <th class="column-qty" style="text-align:center;">'.__('Item Qty', 'pta_volunteer_sus').'</th>
                                <th class="column-clear_link">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>';
                    foreach ($signups as $signup) {
                        $clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
                        $clear_url = add_query_arg($clear_args);
                        $return .= '<tr>
                            <td>'.esc_html($signup->title).'</td>
                            <td>'.(($signup->date == "0000-00-00") ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("date_format"), strtotime($signup->date))).'</td>
                            <td>'.esc_html($signup->task_title).'</td>
                            <td style="text-align:right;">'.(("" == $signup->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($signup->time_start)) ).'</td>
                            <td style="text-align:right;">'.(("" == $signup->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($signup->time_end)) ).'</td>
                            <td style="text-align:center;">'.((" " !== $signup->item) ? esc_html($signup->item) : __("N/A", 'pta_volunteer_sus') ).'</td>
                            <td style="text-align:center;">'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : __("N/A", 'pta_volunteer_sus') ).'</td>
                            <td style="text-align:right;"><a href="'.esc_url($clear_url).'">'.__('Clear', 'pta_volunteer_sus').'</a></td>
                        </tr>';
                    }
                    $return .= '</tbody></table>';
                    $return .= apply_filters( 'pta_sus_after_user_signups_list_table', '' );
                }
            }

        } else {
            $return .= $this->messages;
            // Display Individual Sheet
            $sheet = apply_filters( 'pta_sus_display_individual_sheet', $this->data->get_sheet($id), $id );
            if ($sheet === false) {
                $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus error">'.__("Sign-up sheet not found.", 'pta_volunteer_sus').'</p>', 'sheet_not_found_error_message' );
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
                                $display_chair = __('Contact:', 'pta_volunteer_sus' ) . ' <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
                            } elseif ( isset($this->integration_options['directory_page_id']) && 0 < $this->integration_options['directory_page_id']) {               
                                $contact_url = get_permalink( $this->integration_options['directory_page_id'] ) . '?id=' . $sheet->position;
                                $display_chair = __('Contact:', 'pta_volunteer_sus' ) . ' <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
                            } else {
                                $display_chair = __("No Event Chair contact info provided", 'pta_volunteer_sus');
                            }
                        } else {
                            $display_chair = __("No Event Chair contact info provided", 'pta_volunteer_sus');
                        }
                        
                    } else {
                        $chair_names = $this->data->get_chair_names_html($sheet->chair_name);
                        // Check if there is more than one chair name to display either Chair or Chairs
                        $names = str_getcsv($sheet->chair_name);
                        $count = count($names);
                        if ( $count > 1) {
                            $display_chair = __('Event Chairs:', 'pta_volunteer_sus').' <a href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
                        } elseif ( 1 == $count ) {
                            $display_chair = __('Event Chair:', 'pta_volunteer_sus').' <a href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
                        } else {
                            $display_chair = __("No Event Chair contact info provided", 'pta_volunteer_sus');
                        }
                    }

                    $display_chair = apply_filters( 'pta_sus_display_chair_contact', $display_chair, $sheet );
                    $view_all_text = apply_filters('pta_sus_public_output', __('View all Sign-up Sheets', 'pta_volunteer_sus'), 'view_all_sheets_link_text');
                    $return .= '
                        <p><a href="'.esc_url($this->all_sheets_uri).'">&laquo; '.esc_html( $view_all_text ).'</a></p>
                        <div class="pta-sus-sheet">
                            <h2>'.esc_html($sheet->title).'</h2>
                            <h2>'.$display_chair.'</h2>
                    ';
                } else {
                    $return .= '<div class="pta-sus-sheet">';
                }
                if ( false == $sheet->visible && current_user_can( 'manage_signup_sheets' ) ) {
                    $return .= apply_filters( 'pta_sus_public_output', '<p class="pta-sus-hidden";>'.__('This sheet is currently hidden from the public.', 'pta_volunteer_sus') .'</p>', 'sheet_hidden_message' );
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
                        if($tdate >= date('Y-m-d') || "0000-00-00" == $tdate) {
                            $future_dates = true;
                            break;
                        }
                    }
                    
                    // Make sure there are some future dates before showing anything
                    if($future_dates) {
                        // Only show details if there is something to show
                        if('' != $sheet->details) {
                            $return .= apply_filters( 'pta_sus_public_output', '<h3>'.__('DETAILS:', 'pta_volunteer_sus').'</h3>', 'sheet_details_heading' );
                            $return .= wp_kses_post($sheet->details);
                        }
                        $return .= apply_filters( 'pta_sus_public_output', '<h3>'.__('Sign up below...', 'pta_volunteer_sus').'</h3>', 'sign_up_below' );
                        $task_dates = apply_filters( 'pta_sus_sheet_task_dates', $task_dates, $sheet->id );
                        foreach ($task_dates as $tdate) {
                            if( "0000-00-00" != $tdate && $tdate < date('Y-m-d')) continue; // Skip dates that have passed already
                            if( "0000-00-00" != $tdate ) {
                                $return .= '<h4><strong>'.mysql2date( get_option('date_format'), $tdate, $translate = true ).'</strong></h4>';
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
        if (!($tasks = $this->data->get_tasks($sheet_id, $date))) {
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
            $return .= '
                <table class="pta-sus-tasks" cellspacing="0">
                    <thead>
                        <tr>
                            <th>'.apply_filters( 'pta_sus_public_output', __('Task/Item', 'pta_volunteer_sus'), 'task_name_header' ).'</th>
                            <th>'.apply_filters( 'pta_sus_public_output', __('Start Time', 'pta_volunteer_sus'), 'task_start_time_header' ).'</th>
                            <th>'.apply_filters( 'pta_sus_public_output', __('End Time', 'pta_volunteer_sus'), 'task_end_time_header' ).'</th>
                            <th>'.apply_filters( 'pta_sus_public_output', __('Available Spots', 'pta_volunteer_sus'), 'task_available_spots_header' ).'</th>';
            if ($show_details) {
                $return .= '<th>'.apply_filters( 'pta_sus_public_output', __('Item Details', 'pta_volunteer_sus'), 'task_item_details_header' ).'</th>';
            }
            if ($show_qty) {
                $return .= '<th>'.apply_filters( 'pta_sus_public_output', __('Item Qty', 'pta_volunteer_sus'), 'task_item_quantity_header' ).'</th>';
            }
            $return .= '                
                        </tr>
                    </thead>
                    <tbody>
                    ';
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
                        $signups = $this->data->get_signups($task->id, $date);
                        
                        foreach ($signups AS $signup) {
                            if ($i == $task->qty) {
                                $return .= '<tr class="pta-sus-tasks-bb">';
                            } else {
                                $return .= '<tr>';
                            }
                            if (1 == $i) {
                                $return .= '
                                <td>'.esc_html($task->title).'</td>
                                <td>'.(("" == $task->time_start) ? apply_filters( 'pta_sus_public_output', __("N/A", 'pta_volunteer_sus'), 'task_time_na' ) : date_i18n(get_option("time_format"), strtotime($task->time_start)) ).'</td>
                                <td>'.(("" == $task->time_end) ? apply_filters( 'pta_sus_public_output', __("N/A", 'pta_volunteer_sus'), 'task_time_na' ) : date_i18n(get_option("time_format"), strtotime($task->time_end)) ).'</td>';
                            } else {
                                $return .= '
                                <td></td>
                                <td></td>
                                <td></td>';
                            }
                            if($this->main_options['hide_volunteer_names']) {
                                $display_signup = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'task_spot_filled_message' );
                            } else {
                                $display_signup = esc_html($signup->firstname).' '.esc_html(substr($signup->lastname, 0, 1)) . '.';
                            }
                            // hook to allow others to modify how the signed up names are displayed
                            $display_signup = apply_filters( 'pta_sus_display_signup_name', $display_signup, $signup );
                            $return .= '<td class="pta-sus-em">#'.$i.': '.$display_signup.'</td>';
                            if ($show_details) {
                                    $return .= '<td class="pta-sus-em">'.esc_html($signup->item).'</td>';
                            }
                            if ($show_qty) {
                                    $return .= '<td>'.("YES" === $task->enable_quantities ? (int)($signup->item_qty) : "").'</td>';
                            }
                            if ('YES' === $task->enable_quantities) {
                                $i += $signup->item_qty;
                            } else {
                                $i++;
                            }                           
                            $return .= '</tr>';
                        }
                        for ($i=$i; $i<=$task->qty; $i++) {
                            if ($i == $task->qty) {
                                $return .= '<tr class="pta-sus-tasks-bb">';
                            } else {
                                $return .= '<tr>';
                            }
                            if (1 == $i) {
                                $return .= '
                                <td>'.esc_html($task->title).'</td>
                                <td>'.(("" == $task->time_start) ? apply_filters( 'pta_sus_public_output', __("N/A", 'pta_volunteer_sus'), 'task_time_na' ) : date_i18n(get_option("time_format"), strtotime($task->time_start)) ).'</td>
                                <td>'.(("" == $task->time_end) ? apply_filters( 'pta_sus_public_output', __("N/A", 'pta_volunteer_sus'), 'task_time_na' ) : date_i18n(get_option("time_format"), strtotime($task->time_end)) ).'</td>';
                            } else {
                                $return .= '
                                <td></td>
                                <td></td>
                                <td></td>';
                            }
                            $return .= '<td>#'.$i.': <a href="'.esc_url($task_url).'">'.apply_filters( 'pta_sus_public_output', __('Sign up ', 'pta_volunteer_sus').'&raquo;', 'task_sign_up_link_text' ) . '</a></td>';
                            if($show_details) {
                            	$return .= '<td></td>';
                            }
                            if($show_qty) {
                                $return .= '<td></td>';
                            }
                        	$return .= '</tr>';
                        }
                    }
                    $return .= '
                    </tbody>
                </table>';
            $return .= apply_filters( 'pta_sus_after_task_list', '', $tasks );
            return $return;
        }
    } // Display task list

	public function display_signup_form($task_id, $date) {	
        $task = $this->data->get_task($task_id);
        do_action( 'pta_sus_before_signup_form', $task, $date );
        if ("0000-00-00" == $date) {
            $show_date = false;
        } else {
            $show_date = date_i18n(get_option('date_format'), strtotime($date));
        }
        $form = apply_filters( 'pta_sus_signup_page_before_form_title', '', $task, $date );
        $form .= apply_filters( 'pta_sus_public_output', '<h3>'.__('Sign Up', 'pta_volunteer_sus').'</h3>', 'sign_up_form_heading' );
        $form .= '<h4>'. apply_filters( 'pta_sus_public_output', __('You are signing up for... ', 'pta_volunteer_sus'), 'you_are_signing_up_for' ).'<br/><strong>'.esc_html($task->title).'</strong> ';
        if ($show_date) {
            $form .= sprintf(__('on %s', 'pta_volunteer_sus'), $show_date);
        }
        $form .= '</h4>';
        if ('' != $task->time_start) {
            $form .= '<strong>'.apply_filters( 'pta_sus_public_output', __('Start Time: ', 'pta_volunteer_sus'), 'start_time_label' ) . date_i18n(get_option("time_format"), strtotime($task->time_start)) . '</strong><br/>';
        }
        if ('' != $task->time_end) {
            $form .= '<strong>'.apply_filters( 'pta_sus_public_output', __('End Time: ', 'pta_volunteer_sus'), 'end_time_label' ) . date_i18n(get_option("time_format"), strtotime($task->time_end)) . '</strong>';
        }
        $firstname_label = apply_filters( 'pta_sus_public_output', __('First Name', 'pta_volunteer_sus'), 'firstname_label' );
        $lastname_label = apply_filters( 'pta_sus_public_output', __('Last Name', 'pta_volunteer_sus'), 'lastname_label' );
        $email_label = apply_filters( 'pta_sus_public_output', __('E-mail', 'pta_volunteer_sus'), 'email_label' );
        $phone_label = apply_filters( 'pta_sus_public_output', __('Phone', 'pta_volunteer_sus'), 'phone_label' );

        $form .= apply_filters( 'pta_sus_signup_form_before_form_fields', '', $task, $date );
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( !($current_user instanceof WP_User) )
            return;           
            // Prefill user data if they are signed in
            // Using Woocommerce to handle site registrations stores a "billing_phone" user meta field
            // since we set single to "true", this will return an empty string if the field doesn't exist
            $phone = apply_filters('pta_sus_user_phone', get_user_meta( $current_user->ID, 'billing_phone', true ), $current_user );
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
				</p>
                <p>
                    <label for="signup_phone">'.$phone_label.'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'. esc_attr($phone).'" />
                </p>';
        } else { 
        	// If not signed in, get the user data
            if (false == $this->main_options['disable_signup_login_notice']) {
                $form .= '<p><strong>'.apply_filters( 'pta_sus_public_output', __('If you have an account, it is strongly recommended that you <em style="text-decoration:underline;">login before you sign up</em> so that you can view and edit all your signups.', 'pta_volunteer_sus'), 'signup_login_notice' ).'</strong></p>';
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
				</p>
                <p>
                    <label for="signup_phone">'.$phone_label.'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'.((isset($_POST['signup_phone'])) ? esc_attr($_POST['signup_phone']) : '').'" />
                </p>';
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
                $form .= '<label for="signup_item_qty">'.esc_html( apply_filters( 'pta_sus_public_output', sprintf(__('Item QTY (1 - %d): ', 'pta_volunteer_sus'), (int)$available), 'item_quantity_input_label' ) ).'</label>
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