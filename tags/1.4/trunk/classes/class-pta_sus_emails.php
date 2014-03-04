<?php
/**
* Email Functions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Emails {

	public $email_options;
	public $main_options;
	public $data;

	public function __construct() {
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->data = new PTA_SUS_Data();

	} // Construct

	/**
    * Send email when user signs up
    * 
    * @param    int  the signup id
    *           bool signup or reminder email
    * @return   bool
    */
    public function send_mail($signup_id, $reminder=false) {
        $signup = $this->data->get_signup($signup_id);
        $task = $this->data->get_task($signup->task_id);
        $sheet = $this->data->get_sheet($task->sheet_id);
        
        $from = $this->email_options['from_email'];
        if (empty($from)) $from = get_bloginfo('admin_email');
        $replyto = $this->email_options['replyto_email'];
        if (empty($replyto)) $replyto = get_bloginfo('admin_email');

        $to = $signup->firstname . ' ' . $signup->lastname . ' <'. $signup->email . '>';

    
        if($reminder) {
            $subject = $this->email_options['reminder_email_subject'];
            $message = $this->email_options['reminder_email_template'];
        } else {
            $subject = $this->email_options['confirmation_email_subject'];
            $message = $this->email_options['confirmation_email_template'];
        }

        // Get Chair emails
        if (isset($sheet->position) && '' != $sheet->position) {
            $chair_emails = $this->get_member_directory_emails($sheet->position);
        } else {
            if('' == $sheet->chair_email) {
                $chair_emails = false;
                wp_die('OOPS!');
            } else {
                $chair_emails = explode(',', $sheet->chair_email);
            }
        }

        $headers = array();
            $headers[]  = "From: " . get_bloginfo('name') . " <" . $from . ">";
            $headers[]  = "Reply-To: " . $replyto;
            $headers[]  = "Content-Type: text/plain; charset=UTF-8";
            $headers[]  = "Content-Transfer-Encoding: 8bit";
            if (!empty($chair_emails) && !$reminder) { 
            	// CC to all chairs for signups, but not reminders
                foreach ($chair_emails as $cc) {
                    $headers[] = 'Bcc: ' . $cc;
                }
            }

        // Calculate some Variables for display
        $date = ($signup->date == '0000-00-00') ? __('N/A', 'pta_volunteer_sus') : mysql2date( get_option('date_format'), $signup->date, $translate = true );
        $start_time = ($task->time_start == "") ? __('N/A', 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start));
        $end_time = ($task->time_end == "") ? __('N/A', 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end));
        if (isset($signup->item) && $signup->item != " ") {
        	$item = $signup->item;
        } else {
        	$item = __('N/A', 'pta_volunteer_sus');
        }
        if (!empty($chair_emails)) {
        	$contact_emails = implode("\r\n", $chair_emails);
        } else {
        	$contact_emails = __('N/A', 'pta_volunteer_sus');
        }
        
        // Replace any template tags with the appropriate variables
        $message = str_replace('{sheet_title}', $sheet->title, $message);
        $message = str_replace('{task_title}', $task->title, $message);
        $message = str_replace('{date}', $date, $message);
        $message = str_replace('{start_time}', $start_time, $message);
        $message = str_replace('{end_time}', $end_time, $message);
        $message = str_replace('{item_details}', $item, $message);
        $message = str_replace('{firstname}', $signup->firstname, $message);
        $message = str_replace('{lastname}', $signup->lastname, $message);
        $message = str_replace('{contact_emails}', $contact_emails, $message);
        $message = str_replace('{site_name}', get_bloginfo('name'), $message);
        $message = str_replace('{site_url}', get_bloginfo('url'), $message);

        return wp_mail($to, $subject, $message, $headers);
    }

    public function get_member_directory_emails($group='') {
        $args = array( 'post_type' => 'member', 'member_category' => $group );
        $members = get_posts( $args );
        if(!$members) return false;
        $emails = array();
        foreach ($members as $member) {
            if (is_email( esc_html( $email = get_post_meta( $member->ID, '_pta_member_directory_email', true ) ) )) {
                $emails[] = $email;
            }             
        }
        if(0 == count($emails)) return false;
        return $emails;
    }

    public function send_reminders() {
    	$limit = false;
    	$now = time();
    	// This function is used to check if we need to send out reminder emails or not
    	if(isset($this->email_options['reminder_email_limit']) && '' != $this->email_options['reminder_email_limit'] && 0 < $this->email_options['reminder_email_limit']) {
    		$limit = (int)$this->email_options['reminder_email_limit'];
    		if ( $last_batch = get_option( 'pta_sus_reminders_last_batch' ) ) {
    			if( ( $now - $last_batch['time'] < 60 * 60 ) && ( $limit <= $last_batch['num'] ) ) {
    				// past our limit and less than an hour, so return
    				return false;
    			} elseif ( $now - $last_batch['time'] >= 60 * 60 ) {
    				// more than an hour has passed, reset last batch
    				$last_batch['num'] = 0;
    				$last_batch['time'] = $now;
    			}
    		} else {
    			// Option doesn't exist yet, set default
    			$last_batch = array();
    			$last_batch['num'] = 0;
				$last_batch['time'] = $now;
    		}
    	}
        
        // Go through all sheets and check the dates, if they need reminders,
        // if we are within the reminder date and the reminder hasn't been sent, and then build an array of 
        // objects for which we need to send reminders -- Use our modified Get All function from DLS
        $events = $this->data->get_all_data();       
        $reminder_events = array();
        foreach ($events as $event) {
            if ($event->sheet_trash) continue; // skip trashed events
            if (empty($event->email)) continue; // skip if nobody signed up
            $event_time = strtotime($event->signup_date);
            if ( $event->reminder1_days > 0 && !$event->reminder1_sent ) {
                $reminder1_time = $event->reminder1_days * 24 * 60 * 60;
                if (($event_time - $reminder1_time ) < $now) {
                    $event->reminder_num = 1;
                    $reminder_events[] = $event;
                }
            } elseif ( $event->reminder2_days > 0 && !$event->reminder2_sent ) {
                $reminder2_time = $event->reminder2_days * 24 * 60 * 60;
                if (($event_time - $reminder2_time ) < $now) {
                    $event->reminder_num = 2;
                    $reminder_events[] = $event;
                }
            } 
        }

        $reminder_count = 0;

        if (!empty($reminder_events)) {
            // Next, go through the each reminder event and prepare/send an email
            // Each event object returned by get_all_data is actually an individual signup,
            // so each one can be used to create a personalized email.  However, if there are
            // no signups for a given task on a sheet, an event object for that task will still
            // be created, so need to see if there is a valid email first before sending.
            foreach ($reminder_events as $event) {
                if(!is_email( $event->email)) continue; // skip any invalid emails

                // Check if we have reached our hourly limit or not
                if ($limit) {
                	if ( $limit <= ($last_batch['num'] + $reminder_count) ) {
                		// limit reached, so break out of foreach loop
                		break;
                	}
                }

                if ($this->send_mail($event->signup_id, $reminder = true) === TRUE) { 
                    // Keep track of # of reminders sent
                    $reminder_count++; 

                    // Here we need to set the reminder_sent to true
                    $update = array();
                    if ( 1 === $event->reminder_num ) {
                        $update['signup_reminder1_sent'] = TRUE;
                    }
                    if ( 2 === $event->reminder_num ) {
                        $update['signup_reminder2_sent'] = TRUE;
                    }
                    $updated = $this->data->update_signup($update, $event->signup_id);

                }
            }

            if($limit) {
            	// increment our last batch num by number of reminders sent
            	$last_batch['num'] += $reminder_count;
            	update_option( 'pta_sus_reminders_last_batch', $last_batch );
            }
            
            if ( 0 < $reminder_count && $this->main_options['enable_cron_notifications'] ) {
                // Send site admin an email with number of reminders sent
                $to = get_bloginfo( 'admin_email' );
                $subject = __("Volunteer Signup Reminders sent", 'pta_volunteer_sus');
                $message = __("Volunteer signup sheet CRON job has been completed.", 'pta_volunteer_sus')."\r\n\r\n";
                $message .= sprintf( __("%d reminder emails were sent.", 'pta_volunteer_sus'), $reminder_count ); 
                //$reminder_count . __(" reminder emails were sent.", 'pta_volunteer_sus'). "\r\n\r\n";
                wp_mail($to, $subject, $message);
            }
        }

        // Set another option to save the last time any reminders were sent
        if (!$sent = get_option('pta_sus_last_reminders')) {
            $sent = array('time' => 0, 'num' => 0, 'last' => 0);
        }
        $sent['last'] = $now;
        if ( 0 < $reminder_count ) {
            $sent['time'] = $now;
            $sent['num'] = $reminder_count;          
        }  
        update_option( 'pta_sus_last_reminders', $sent );   
        return $reminder_count;
    } // Send Reminders

} // End of class
/* EOF */