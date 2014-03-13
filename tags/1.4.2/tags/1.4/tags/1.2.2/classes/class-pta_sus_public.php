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
    
    public function __construct() {
        $this->data = new PTA_SUS_Data();
        
        $plugin = plugin_basename(__FILE__);
        
        $this->plugin_path = dirname(__FILE__).'/';

        $this->all_sheets_uri = add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false));

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
        
        add_action('wp_enqueue_scripts', array($this, 'add_css_and_js_to_frontend'));

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
        $this->email_options = get_option( 'pta_volunteer_sus_email_options' );
        $this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
    } // Construct

	/**
     * Output the volunteer signup form
     * 
     * @param   array   attributes from shortcode call
     */
    public function display_sheet($atts) {
        $return = '';
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode'] ) {
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $return .= '<p class="pta-sus error">'.__('Volunteer Sign-Up Sheets are in TEST MODE', 'pta_volunteer_sus').'</p>';
            } elseif (is_page( $this->main_options['volunteer_page_id'] )) {
                return esc_html($this->main_options['test_mode_message']);
            } else {
                return;
            }
        }
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required'] ) {
            if (!is_user_logged_in()) {
                return '<p class="pta-sus error">' . esc_html($this->main_options['login_required_message']) . '</p>';
            }
        }
        extract( shortcode_atts( array(
            'id' => false,
            'date' => false,
            'list_title' => __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus'),
        ), $atts ) );

        // Allow plugins or themes to modify shortcode parameters
        $id = apply_filters( 'pta_sus_shortcode_id', $id );
        if('' == $id) $id = false;
        $date = apply_filters( 'pta_sus_shortcode_date', $date );
        if('' == $date) $date = false;
        if('' == $list_title) $list_title = __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus');
        $list_title = apply_filters( 'pta_sus_shorcode_list_title', $list_title );

        
        if ($id === false && !empty($_GET['sheet_id'])) $id = (int)$_GET['sheet_id'];

        if ($date === false && !empty($_GET['date'])) {
            // Make sure it's a valid date in our format first - Security check
            if ($this->data->check_date($_GET['date'])) {
                $date = $_GET['date'];
            }
        } 
        
        if ($id === false) {
            $show_hidden = false;
            $hidden = '';
            // Allow admin or volunteer managers to view hidden sign up sheets
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $show_hidden = true;
                $hidden = '<br/><span style="color:red;"><strong>(--'.__('Hidden!', 'pta_volunteer_sus').'--)</strong></span>';
            }
            
            // Display all active
            $return .= '<h2>'.esc_html($list_title).'</h2>';
            $sheets = $this->data->get_sheets(false, true, $show_hidden);
            $sheets = array_reverse($sheets);

            // Allow plugins or themes to modify retrieved sheets
            $sheets = apply_filters( 'pta_sus_display_active_sheets', $sheets );

            if (empty($sheets)) {
                $return .= '<p>'.__('No sheets currently available at this time.', 'pta_volunteer_sus').'</p>';
            } else {
                $return .= '
                    <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.__('Title', 'pta_volunteer_sus').'</th>
                                <th class="column-date">'.__('Start Date', 'pta_volunteer_sus').'</th>
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
                            $sheet_url = add_query_arg($sheet_args);
                            $return .= '
                                <tr'.(($open_spots === 0) ? ' class="filled"' : '').'>
                                    <td class="column-title"><a href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a>'.$is_hidden.'</td>
                                    <td class="column-date">'.(($sheet->first_date == '0000-00-00') ? __('Ongoing', 'pta_volunteer_sus') : date_i18n(get_option('date_format'), strtotime($sheet->first_date))).'</td>
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
            }
            
            // If current user has signed up for anything, list their signups and allow them to edit/clear them
            // If they aren't logged in, prompt them to login to see their signup info
            if (!is_user_logged_in()) {
                $return .= '<p>'.__('Please login to view and edit your volunteer sign ups.', 'pta_volunteer_sus').'</p>';
            } else {
                $current_user = wp_get_current_user();
                if ( !($current_user instanceof WP_User) )
                return;

                // Check if they clicked on a CLEAR link
                // Perhaps add some sort of confirmation, maybe with jQuery?
                if (isset($_GET['signup_id'])) {
                    $cleared = $this->data->delete_signup((int)$_GET['signup_id']);
                    if ($cleared) {
                        $return .= '<h4>'.__('Signup Cleared', 'pta_volunteer_sus').'</h4>';
                    } else {
                        $return .= '<h4>'.__('ERROR clearing signup!', 'pta_volunteer_sus').'</h4>';
                    }
                }

                $signups = $this->data->get_user_signups($current_user->ID);
                if ($signups) {
                    $return .= '
                    <h3>'.__('You have signed up for the following', 'pta_volunteer_sus').'</h3>
                    <h4>'.__('Click on Clear to remove yourself from a signup.', 'pta_volunteer_sus').'</h4>
                        <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.__('Title', 'pta_volunteer_sus').'</th>
                                <th class="column-date">'.__('Date', 'pta_volunteer_sus').'</th>
                                <th class="column-task">'.__('Task/Item', 'pta_volunteer_sus').'</th>
                                <th class="column-time" style="text-align:right;">'.__('Start Time', 'pta_volunteer_sus').'</th>
                                <th class="column-time" style="text-align:right;">'.__('End Time', 'pta_volunteer_sus').'</th>
                                <th class="column-details" style="text-align:center;">'.__('Item Details', 'pta_volunteer_sus').'</th>
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
                            <td style="text-align:right;"><a href="'.esc_url($clear_url).'">'.__('Clear', 'pta_volunteer_sus').'</a></td>
                        </tr>';
                    }
                    $return .= '</tbody></table>';
                }
            }

        } else {
            
            // Display Individual Sheet
            if (($sheet = $this->data->get_sheet($id)) === false) {
                $return .= '<p class="pta-sus error">'.__("Sign-up sheet not found.", 'pta_volunteer_sus').'</p>';
                return $return;
            } else {
                // Check is the sheet is visible and don't show unless it's an admin user
                if ( false == $sheet->visible && !current_user_can( 'manage_signup_sheets' ) ) return;
                // TODO 
                // USE THE ANTISPAMBOT WP FEATURE TO OBFUSCATE THE EMAIL ADDRESSES AND EITHER PUT THEM BACK TOGETHER
                // SEPARATED BY COMMAS, OR USE A BCC FOR OTHER EMAIL ADDRESSES AFTER THE FIRST
                // 
                // AFTER MAKING PTA MEMBER DIRECTORY A CLASS, WE CAN ALSO CHECK IF IT EXISTS
                if( isset($this->integration_options['enable_member_directory']) && true === $this->integration_options['enable_member_directory'] && '' != $sheet->position ) {
                    // Create Contact Form link
                    if($position = get_term_by( 'slug', $sheet->position, 'member_category' )) {
                        if ( isset($this->integration_options['contact_page_id']) && 0 < $this->integration_options['contact_page_id']) {               
                            $contact_url = get_permalink( $this->integration_options['contact_page_id'] ) . '?id=' . esc_html($sheet->position);
                            $display_chair = 'Contact: <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
                        } elseif ( isset($this->integration_options['directory_page_id']) && 0 < $this->integration_options['directory_page_id']) {               
                            $contact_url = get_permalink( $this->integration_options['directory_page_id'] ) . '?id=' . $sheet->position;
                            $display_chair = 'Contact: <a href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
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
                    } elseif ( 1 == $count && is_email($sheet->chair_email)) {
                        $display_chair = __('Event Chair:', 'pta_volunteer_sus').' <a href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
                    } else {
                        $display_chair = __("No Event Chair contact info provided", 'pta_volunteer_sus');
                    }
                }



                // *****************************************************************************
                // If it's not the main
                // volunteer page, then change the header info -- don't show "view all..." and
                // don't show title and chair... instead just make a simple heading
                if (is_page( $this->main_options['volunteer_page_id'] )) {
                    $return .= '
                        <p><a href="'.esc_url($this->all_sheets_uri).'">&laquo; '.__('View all Sign-up Sheets', 'pta_volunteer_sus').'</a></p>
                        <div class="pta-sus-sheet">
                            <h2>'.esc_html($sheet->title).'</h2>
                            <h2>'.$display_chair.'</h2>
                    ';
                } else {
                    $return .= '<div class="pta-sus-sheet">';
                }
                if ( false == $sheet->visible && current_user_can( 'manage_signup_sheets' ) ) {
                    $return .= '<p style="color:red";><strong>'.__('This sheet is currently hidden from the public.', 'pta_volunteer_sus') .'</strong></p>';
                }
                
                // ******************************************************************************
        	    
			    $submitted = (isset($_POST['mode']) && $_POST['mode'] == 'submitted');
			    $err = 0;
			    $success = false;
			    
			    // Process Sign-up Form
			    if ($submitted) {
                    // Check for spambots
                    if (!empty($_POST['website'])) {
                        $err++;
                        echo '<p class="pta-sus error">'.__('Oops! You filled in the spambot field. Please leave it blank and try again.', 'pta_volunteer_sus').'</p>';
                    }
				    //Error Handling
				    if (
					    empty($_POST['signup_firstname'])
					    || empty($_POST['signup_lastname'])
					    || empty($_POST['signup_email'])
                        || empty($_POST['signup_phone'])
				    ) {
					    $err++;
					    echo '<p class="pta-sus error">'.__('Please complete all required fields.', 'pta_volunteer_sus').'</p>';
				    }

                    if("YES" == $_POST['need_details'] && '' == $_POST['signup_item']) {
                        $err++;
                        echo '<p class="pta-sus error">'.__('Please enter the item you are bringing.', 'pta_volunteer_sus').'</p>';
                    }

                    // Check for non-allowed characters
                    elseif (preg_match("/[^A-Za-z\-\.\'\ ]/", stripslashes($_POST['signup_firstname'])))
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Invalid Characters in First Name!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }
                    elseif (preg_match("/[^A-Za-z\-\.\'\ ]/", stripslashes($_POST['signup_lastname'])))
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Invalid Characters in Last Name!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }
                    elseif ( !is_email( $_POST['signup_email'] ) )
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Invalid Email!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }
                    elseif (preg_match("/[^0-9\-\.\(\)\ ]/", $_POST['signup_phone']))
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Invalid Characters in Phone Number!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }
                    elseif (preg_match("/[^A-Za-z0-9\-\.\'\(\)\ ]/", stripslashes($_POST['signup_item'])))
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Invalid Characters in Signup Item!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }
                    elseif (!$this->data->check_date($_POST['signup_date']))
                        {
                            $err++;
                            echo '<p class="pta-sus error">'.__('Hidden signup date field is invalid!  Please try again.', 'pta_volunteer_sus').'</p>';
                        }

                    // Add Signup
                    if (!$err) {
                        if ( $this->data->add_signup($_POST, $_GET['task_id']) === false) {
                            $err++;
                            $return .= '<p class="pta-sus error">'.__('Error adding signup record.  Please try again.', 'pta_volunteer_sus').'</p>';
                        } else {
                            global $wpdb;
                            if(!class_exists('PTA_SUS_Emails')) {
                            	include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
                            }
                            $emails = new PTA_SUS_Emails();
                            $success = true;
                            $return .= '<p class="pta-sus updated">'.__('You have been signed up!', 'pta_volunteer_sus').'</p>';
                            if ($emails->send_mail($wpdb->insert_id) === false) { 
                                $return .= __('ERROR SENDING EMAIL', 'pta_volunteer_sus'); 
                            }
                        }
                    }
                    
			    }
                
                // Display Sign-up Form
			    if (!$submitted || $err) {
				    if (isset($_GET['task_id']) && $date) {
					    $return .= $this->display_signup_form($_GET['task_id'], $date);
					    return false;
				    }
			    }
			    
			    // Sheet Details
                // Need to escape/sanitize all output to screen
			    if (!$submitted || $success || $err) {
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
                            $return .= '
                                <p><strong>'.__('DETAILS:', 'pta_volunteer_sus').' </strong>'.wp_kses_post($sheet->details).'</p>
                            ';
                        }
                        $return .= '<h3>'.__('Sign up below...', 'pta_volunteer_sus').'</h3>';
                        $task_dates = $this->data->get_all_task_dates($sheet->id);
                        foreach ($task_dates as $tdate) {
                            if( "0000-00-00" != $tdate && $tdate < date('Y-m-d')) continue; // Skip dates that have passed already
                            $return .= '<h4><strong>'.mysql2date( get_option('date_format'), $tdate, $translate = true ).'</strong></h4>';
                            $return .= $this->display_task_list($sheet->id, $tdate);
                        }
                    }

                    $return .= '</div>';
	            }
            }
        }
        return $return;
    } // Display Sheet

    public function display_task_list($sheet_id, $date) {
        // Tasks
        $return = '';
        if (!($tasks = $this->data->get_tasks($sheet_id, $date))) {
            $return .= '<p>'.__('No tasks were found for ', 'pta_volunteer_sus') . mysql2date( get_option('date_format'), $tdate, $translate = true ).'</p>';
        } else {
            $show_details = false;
            foreach ($tasks as $task) {
                if ( 'YES' == $task->need_details ) {
                    $show_details = true;
                }
            }
            $return .= '
                <table class="pta-sus-tasks" cellspacing="0">
                    <thead>
                        <tr>
                            <th>'.__('Task/Item', 'pta_volunteer_sus').'</th>
                            <th>'.__('Start Time', 'pta_volunteer_sus').'</th>
                            <th>'.__('End Time', 'pta_volunteer_sus').'</th>
                            <th>'.__('Available Spots', 'pta_volunteer_sus').'</th>';
            if ($show_details) {
                $return .= '<th>'.__('Item Details', 'pta_volunteer_sus').'</th>';
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
                                <td>'.(("" == $task->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start)) ).'</td>
                                <td>'.(("" == $task->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end)) ).'</td>';
                            } else {
                                $return .= '
                                <td></td>
                                <td></td>
                                <td></td>';
                            }
                            $return .= '<td>#'.$i.': <em>'.esc_html($signup->firstname).' '.esc_html(substr($signup->lastname, 0, 1)).'.</em></td>';
                            if ($show_details) {
                                    $return .= '<td><em>'.esc_html($signup->item).'</em></td>';
                            }
                            $i++;
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
                                <td>'.(("" == $task->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start)) ).'</td>
                                <td>'.(("" == $task->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end)) ).'</td>';
                            } else {
                                $return .= '
                                <td></td>
                                <td></td>
                                <td></td>';
                            }
                            $return .= '<td>#'.$i.': <a href="'.esc_url($task_url).'">'.__('Sign up ', 'pta_volunteer_sus').'&raquo;</a></td>';
                            if($show_details) {
                            	$return .= '<td></td>';
                            }
                        	$return .= '</tr>';
                        }
                    }
                    $return .= '
                    </tbody>
                </table>';
            return $return;
        }
    } // Display task list

	public function display_signup_form($task_id, $date)
	{	
        // echo 'Function:  Display Signup Form for task id: ' . $task_id ;
        $task = $this->data->get_task($task_id);

        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( !($current_user instanceof WP_User) )
            return;
            
            // Prefill user data if they are signed in
            // Using Woocommerce to handle site registrations stores a "billing_phone" user meta field
            // since we set single to "true", this will return an empty string if the field doesn't exist
            $phone = get_user_meta( $current_user->ID, 'billing_phone', true );
            if ("0000-00-00" == $date) {
                $show_date = false;
            } else {
                $show_date = date_i18n(get_option('date_format'), strtotime($date));
            }
			echo '
            <h3>'.__('Sign Up', 'pta_volunteer_sus').'</h3>';
            echo '<h4>'. __('You are signing up for... ', 'pta_volunteer_sus').'<br/><strong>'.esc_html($task->title).'</strong> ';
            if ($show_date) {
                echo sprintf(__('on %s', 'pta_volunteer_sus'), $show_date);
            }
            echo '</h4>';
            if ('' != $task->time_start) {
                echo '<strong>'.__('Start Time: ', 'pta_volunteer_sus') . date_i18n(get_option("time_format"), strtotime($task->time_start)) . '</strong><br/>';
            }
            if ('' != $task->time_end) {
                echo '<strong>'.__('End Time: ', 'pta_volunteer_sus') . date_i18n(get_option("time_format"), strtotime($task->time_end)) . '</strong>';
            }
            echo '
			<form name="form1" method="post" action="">
                <input type="hidden" name="signup_user_id" value="'.$current_user->ID.'" />
				<p>
					<label for="signup_firstname">'.__('First Name', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_firstname" name="signup_firstname" value="'. esc_attr($current_user->user_firstname) .'" />
				</p>
				<p>
					<label for="signup_lastname">'.__('Last Name', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_lastname" name="signup_lastname" value="'. esc_attr($current_user->user_lastname) .'" />
				</p>
				<p>
					<label for="signup_email">'.__('E-mail', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_email" name="signup_email" value="'. esc_attr($current_user->user_email) .'" />
				</p>
                <p>
                    <label for="signup_phone">'.__('Phone', 'pta_volunteer_sus').'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'. esc_attr($phone).'" />
                </p>';
        } else { 
        	// If not signed in, get the user data
            echo '
			<h3>'.__('Sign-up below', 'pta_volunteer_sus').'</h3>
            <h4>'.__('You are signing up for... ', 'pta_volunteer_sus').'<em>'.esc_html($task->title).'</em></h4>
            <p><strong>'.__('If you have an account, it is strongly recommended that you <em style="text-decoration:underline;">login before you sign up</em> so that you can view and edit all your signups.', 'pta_volunteer_sus').'</strong></p>
			<form name="form1" method="post" action="">
				<p>
					<label for="signup_firstname">'.__('First Name', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_firstname" name="signup_firstname" value="'.((isset($_POST['signup_firstname'])) ? esc_attr($_POST['signup_firstname']) : '').'" />
				</p>
				<p>
					<label for="signup_lastname">'.__('Last Name', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_lastname" name="signup_lastname" value="'.((isset($_POST['signup_lastname'])) ? esc_attr($_POST['signup_lastname']) : '').'" />
				</p>
				<p>
					<label for="signup_email">'.__('E-mail', 'pta_volunteer_sus').'</label>
					<input type="text" id="signup_email" name="signup_email" value="'.((isset($_POST['signup_email'])) ? esc_attr($_POST['signup_email']) : '').'" />
				</p>
                <p>
                    <label for="signup_phone">'.__('Phone', 'pta_volunteer_sus').'</label>
                    <input type="text" id="signup_phone" name="signup_phone" value="'.((isset($_POST['signup_phone'])) ? esc_attr($_POST['signup_phone']) : '').'" />
                </p>';
        }

        // Get the remaining fields, whether or not they are signed in

        // If details are needed for the task, show the field to fill in details.
        // Otherwise don't show the field, but fill it with a blank space
        if ($task->need_details == "YES") {
            echo '
            <p>
			    <label for="signup_item">'.__('Item you are bringing', 'pta_volunteer_sus').'</label>
			    <input type="text" id="signup_item" name="signup_item" value="'.((isset($_POST['signup_item'])) ? esc_attr($_POST['signup_item']) : '').'" />
                <input type="hidden" name="need_details" value="YES" />
		    </p>';
        } else {
            echo '<input type="hidden" name="signup_item" value=" " />
            <input type="hidden" name="need_details" value="NO" />'; 
        }

        // Spam check and form submission
        $go_back_args = array('task_id' => false, 'date' => $date, 'sheet_id' => $_GET['sheet_id']);
        $go_back_url = add_query_arg($go_back_args);
        echo '
			<div style="visibility:hidden"> 
	            <input name="website" type="text"size="20" />
	        </div>
	        <p class="submit">
	            <input type="hidden" name="signup_date" value="'.esc_attr($date).'" />
	            <input type="hidden" name="signup_task_id" value="'.esc_attr($_GET['task_id']).'" />
	        	<input type="hidden" name="mode" value="submitted" />
	        	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Sign me up!').'" />
	            or <a href="'.esc_url($go_back_url).'">&laquo; go back to the Sign-Up Sheet</a>
	        </p>
		</form>
		';       
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