<?php
/**
* Database queries and actions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Data
{
    
    public $wpdb;
    public $tables = array();
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set table names
        $this->tables = array(
            'sheet' => array(
                'name' => $this->wpdb->prefix.'pta_sus_sheets',
                'allowed_fields' => array(
                    'title' => 'text',
                    'details' => 'textarea',
                    'first_date' => 'date',
                    'last_date' => 'date',
                    'type' => 'text',
                    'position' => 'text',
                    'chair_name' => 'names',
                    'chair_email' => 'emails',
                    'reminder1_days' => 'int',
                    'reminder2_days' => 'int',
                    'visible' => 'bool',
                    'trash' => 'bool',
                ),
                'required_fields' => array(
                    'title' => 'Title',
                    'type' => 'Event Type'
                    ),
            ),
            'task' => array(
                'name' => $this->wpdb->prefix.'pta_sus_tasks',
                'allowed_fields' => array(
                    'sheet_id' => 'int',
                    'title' => 'text',
                    'dates' => 'dates',
                    'time_start' => 'time',
                    'time_end' => 'time',
                    'qty' => 'int',
                    'need_details' => 'yesno',
                    'position' => 'int',
                ),
                'required_fields' => array(),
            ),
            'signup' => array(
                'name' => $this->wpdb->prefix.'pta_sus_signups',
                'allowed_fields' => array(
                    'task_id' => 'int',
                    'date'  => 'date',
                    'user_id' => 'int',
                    'item' => 'text',
                    'firstname' => 'text',
                    'lastname' => 'text',
                    'email' => 'email',
                    'phone' => 'phone',
                    'reminder1_sent' => 'bool',
                    'reminder2_sent' => 'bool',
                ),
            ),
        );

    }
     
    /**
     * Get all Sheets
     * 
     * @param     bool     get just trash
     * @param     bool     get only active sheets or those without a set date
     * @return    mixed    array of sheets
     */
    public function get_sheets($trash=false, $active_only=false, $show_hidden=false) {
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * 
            FROM ".$this->tables['sheet']['name']." 
            WHERE trash = %d
            ".(($active_only) ? " AND (ADDDATE(last_date,1) >= NOW() OR last_date = 0000-00-00)" : "")
            .(($show_hidden) ? "" : " AND visible = 1")." 
            ORDER BY first_date DESC, id DESC
        ", $trash));
        $results = $this->stripslashes_full($results);
        // Hide incomplete sheets (no tasks) from public

        if (!is_admin()) {
            foreach($results as $key => $result) {
                $tasks = $this->get_tasks($result->id);
                if(empty($tasks)) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }
     
    /**
     * Get single sheet
     * 
     * @return    sheet object
     */
    public function get_sheet($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['sheet']['name']." WHERE id = %d" , $id));
        if($results = $this->stripslashes_full($results)) {
            return $results[0];
        } else {
            return false;
        }
        
    }
    
    /**
    * Get number of sheets
    */
    public function get_sheet_count($trash=false)
    { 
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT COUNT(*) AS count 
            FROM ".$this->tables['sheet']['name']." 
            WHERE trash = %d
            ", $trash));
        $results = $this->stripslashes_full($results);
        return $results[0]->count;
    }
    
    /**
     * Return # of entries that have matching title and date
     * @param  [type] $title [description]
     * @return [type]        [description]
     */
    public function check_duplicate_sheet($title) {
        $results = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT COUNT(*) AS count 
            FROM ".$this->tables['sheet']['name']." 
            WHERE title = %s AND trash = 0
        ", $title));
        $results = $this->stripslashes_full($results);
        return $results[0]->count;
    }

    public function toggle_visibility($id) {
        $SQL = "UPDATE ".$this->tables['sheet']['name']." 
                SET visible = IF(visible, 0, 1) 
                WHERE id = %d";
        $results = $this->wpdb->query($this->wpdb->prepare($SQL, $id));
        return $results;
    }

    /**
     * Get tasks by sheet
     * 
     * @param     int        id of sheet
     * @return    mixed    array of tasks
     */
    public function get_tasks($sheet_id, $date = '') {
        $SQL = "SELECT * FROM ".$this->tables['task']['name']." WHERE sheet_id = %d ";
        if ('' != $date ) {
            $SQL .= "AND INSTR(`dates`, %s) > 0 ";
        }
        $SQL .= "ORDER BY position, id";
        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $sheet_id, $date));
        $results = $this->stripslashes_full($results);
        return $results;
    }
     
    /**
     * Get single task
     * 
     * @param     int      task id
     * @return    mixed    array of sheets
     */
    public function get_task($id)
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->tables['task']['name']." WHERE id = %d" , $id));
        $results = $this->stripslashes_full($results);
        return $results[0];
    }
    
    /**
     * Get signups by task & date
     * 
     * @param    int        id of task
     * @return    mixed    array of siginups
     */
    public function get_signups($task_id, $date='')
    {
        $SQL = "SELECT * FROM ".$this->tables['signup']['name']." WHERE task_id = %d ";
        if ('' != $date) {
            $SQL .= "AND date = %s";
        }
        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL , $task_id, $date));
        $results = $this->stripslashes_full($results);
        return $results;
    }
    
    public function get_signup($id)
    {
        $results = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM ".$this->tables['signup']['name']." WHERE id = %d" , $id));
        $results = $this->stripslashes_full($results);
        return $results;
    }
    /**
     * Get all data -- Right now this is only used for CRON remider emails, so can probably get rid of a lot of the select fields
     * 
     * @return    mixed    array of siginups
     */
    public function get_all_data()
    {
        $results = $this->wpdb->get_results("
            SELECT
                sheet.id AS sheet_id
                , sheet.title AS sheet_title
                , sheet.type AS sheet_type
                , sheet.details AS sheet_details
                , sheet.chair_name AS sheet_chair_name
                , sheet.chair_email AS sheet_chair_email
                , sheet.trash AS sheet_trash
                , sheet.reminder1_days AS reminder1_days
                , signup.reminder1_sent AS reminder1_sent
                , sheet.reminder2_days AS reminder2_days
                , signup.reminder2_sent AS reminder2_sent
                , task.id AS task_id
                , task.title AS task_title
                , task.dates AS task_dates
                , task.time_start AS task_time_start
                , task.time_end AS task_time_end
                , task.qty AS task_qty
                , task.need_details AS need_details
                , task.position AS task_position
                , signup.id AS signup_id
                , signup.date AS signup_date
                , signup.user_id AS signup_user_id
                , item
                , firstname
                , lastname
                , email
                , phone
            FROM  ".$this->tables['sheet']['name']." sheet
            LEFT JOIN ".$this->tables['task']['name']." task ON sheet.id = task.sheet_id
            LEFT JOIN ".$this->tables['signup']['name']." signup ON task.id = signup.task_id
        ");
        $results = $this->stripslashes_full($results);
        return $results;
    }
    
    /**
     * Get all unique dates for tasks for the given sheet id
     * @param  integer $id Sheet ID
     * @return array   array of all unique dates for a sheet
     */
    public function get_all_task_dates($id) {
        if ($tasks = $this->get_tasks($id)) {
            $dates = array();
            foreach ($tasks AS $task) {
                // Build an array of all unique dates from all tasks for this sheet
                $task_dates = $this->get_sanitized_dates($task->dates);
                foreach ($task_dates as $date) {
                    if(!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            return $dates;
        } else {
            return false;
        }
    }


    /**
    * Get number of signups on a specific sheet
    * Optionally for a specific date
    * Don't count any signups for past dates
    * 
    * @param    int    sheet id
    */
    public function get_sheet_signup_count($id, $date='') {
        $SQL = "
            SELECT COUNT(*) AS count FROM ".$this->tables['task']['name']." t
            RIGHT OUTER JOIN ".$this->tables['signup']['name']." s ON t.id = s.task_id 
            WHERE t.sheet_id = %d 
            AND (NOW() <= ADDDATE(s.date, 1) OR s.date = 0000-00-00) 
        ";
        if( '' != $date ) {
            $SQL .= " AND s.date = %s ";
        }
        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $id, $date));
        return $results[0]->count;
    }
    
    /**
    * Get number of total spots on a specific sheet
    * And optionally for a specific date
    * @param    int    sheet id
    *           string date
    */
    public function get_sheet_total_spots($id, $date='') {
        $total_spots = 0;
        $tasks = $this->get_tasks($id, $date);
        foreach ($tasks as $task) {
            $task_dates = explode(',', $task->dates);
            $good_dates = 0;
            foreach ($task_dates as $tdate) {
                if('' != $date) {
                    if ($tdate != $date) {
                        continue;
                    }
                }
                if( (strtotime($tdate) >= (time() - (24*60*60))) || "0000-00-00" == $tdate ) {
                    ++$good_dates;
                }
            }
            $total_spots += $good_dates * $task->qty;
        }
        return $total_spots;
    }

    /**
     * Get all the signups for a given user id
     * Return info on what they signed up for
     * @param  int $user_id wordpress uer id
     * @return Object Array    Returns an array of objects with the user's signup info
     */
    public function get_user_signups($user_id) {
        $signup_table = $this->tables['signup']['name'];
        $task_table = $this->tables['task']['name'];
        $sheet_table = $this->tables['sheet']['name'];
        $safe_sql = $this->wpdb->prepare("SELECT
            $signup_table.id AS id,
            $signup_table.user_id AS user_id, 
            $signup_table.date AS date,
            $signup_table.item AS item,
            $task_table.title AS task_title,
            $task_table.time_start AS time_start,
            $task_table.time_end AS time_end,
            $sheet_table.title AS title,
            $task_table.dates AS task_dates 
            FROM  $signup_table 
            INNER JOIN $task_table ON $signup_table.task_id = $task_table.id 
            INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id 
            WHERE $signup_table.user_id = %d AND $sheet_table.trash = 0 
            AND ADDDATE(date, 1) >= NOW() OR date = '0000-00-00' 
            ", $user_id);
        $results = $this->wpdb->get_results($safe_sql);
        $results = $this->stripslashes_full($results);
        return $results;
    }

    public function get_chair_names_html($names_csv) {
        $html_names = '';
        $i = 1;
        $names = str_getcsv($names_csv);
        $count = count($names);
        foreach ($names as $name) {
            if ($i > 1) {
                if ($i < $count) {
                    $html_names .= ', ';
                } else {
                    $html_names .= ' and ';
                }                
            }
            $html_names .= $name;
            $i++;
        }
        return $html_names;
    }

    
    /**
     * Add a new sheet
     * 
     * @param    array    array of fields and values to insert
     * @return    mixed    false if insert fails
     */
    public function add_sheet($fields) {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        // if (isset($clean_fields['date']) && $clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        // Data should be sanitized and prepared before inserting into database
        $sanitized_fields = $this->sanitize_sheet_fields($clean_fields);
        // wpdb->insert does all necessary SQL sanitation before inserting into database
        return $this->wpdb->insert($this->tables['sheet']['name'], $sanitized_fields);
        
    }
    
    /**
     * Add a new task
     * 
     * @param    array    array of fields and values to insert
     * @param   int     sheet id
     * @return    mixed    false if insert fails
     */
    public function add_task($fields, $sheet_id) {
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        $clean_fields['sheet_id'] = $sheet_id;
        if ($clean_fields['qty'] < 2) $clean_fields['qty'] = 1;       
        // wpdb->insert does all necessary sanitation before inserting into database
        return $this->wpdb->insert($this->tables['task']['name'], $clean_fields);       
    }
    
    /**
     * Add a new signup to a task
     * 
     * @param   array   array of fields and values to insert
     * @param   int     task id
     * @return  mixed   false if insert fails
     */
    public function add_signup($fields, $task_id) {
        $clean_fields = $this->clean_array($fields, 'signup_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['signup']['allowed_fields']);
        $clean_fields['task_id'] = $task_id;
        // Set user from email if they weren't logged in and if there is an account with that email
        if(!isset($clean_fields['user_id']) || empty($clean_fields['user_id'])) {
            if($user = get_user_by( 'email', $clean_fields['email'] )) {
                $clean_fields['user_id'] = $user->ID;
            }           
        }
        
        // Check if signup spots are filled
        $task = $this->get_task($task_id);
        $signups = $this->get_signups($task_id, $clean_fields['date']);
        if (count($signups) >= $task->qty) {
            return false;
        }
        // wpdb->insert does all necessary sanitation before inserting into database
        // $fields were also validated before this function was called
        return $this->wpdb->insert($this->tables['signup']['name'], $clean_fields);
    }
    
    /**
     * Update a sheet
     * 
     * @param    int        sheet id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_sheet($fields, $id) {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        if (isset($clean_fields['date']) && $clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        $sanitized_fields = $this->sanitize_sheet_fields($clean_fields);
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['sheet']['name'], $sanitized_fields, array('id' => $id), null, array('%d'));
    }
    
    /**
     * Update a task
     * 
     * @param    int        task id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_task($fields, $id, $dates=array()) {
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        if ($clean_fields['qty'] < 2) $clean_fields['qty'] = 1;
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['task']['name'], $clean_fields, array('id' => $id), null, array('%d'));
    }

    /**
     * Update a signup
     * 
     * @param    int        signup id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_signup( $fields, $id ) {
        $clean_fields = $this->clean_array($fields, 'signup_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['signup']['allowed_fields']);
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['signup']['name'], $clean_fields, array('id' => $id), null, array('%d'));
    }
    
    /**
    * Delete a sheet and all associated tasks and signups
    * 
    * @param    int     sheet id
    */
    public function delete_sheet($id) {
        $tasks = $this->get_tasks($id);
        foreach ($tasks AS $task) {
            // Delete Signups
            if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['signup']['name']." WHERE task_id = %d" , $task->id)) === false) {
                return false;
            }
        }
        // Delete Tasks
        if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['task']['name']." WHERE sheet_id = %d" , $id)) === false) {
            return false;
        }
        // Delete Sheet
        if ($this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['sheet']['name']." WHERE id = %d" , $id)) === false) {
            return false;
        }
        return true;
    }
    
    /**
    * Delete a task
    * 
    * @param    int     task id
    */
    public function delete_task($id) {
        return $this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['task']['name']." WHERE id = %d" , $id));
    }
    
    /**
    * Delete a signup
    * 
    * @param    int     signup id
    */
    public function delete_signup($id) {
        return $this->wpdb->query($this->wpdb->prepare("DELETE FROM ".$this->tables['signup']['name']." WHERE id = %d" , $id));
    }

    public function delete_expired_signups() {
        return $this->wpdb->query("DELETE FROM ".$this->tables['signup']['name']." WHERE NOW() > ADDDATE(date, 1)");
    }
    
    /**
    * Copy a sheet and all tasks to a new sheet for editing
    * 
    * @param    int     sheet id
    * @param date $date The new date
    */
    public function copy_sheet($id) {
        $new_fields = array();
        
        $sheet = $this->get_sheet($id);
        $sheet = (array)$sheet;
        foreach ($this->tables['sheet']['allowed_fields'] AS $field=>$nothing) {
            if ('title' == $field) {
                $new_fields['sheet_title'] = $sheet['title'] . ' Copy';
            } else {
                $new_fields['sheet_'.$field] = $sheet[$field];
            }
            
        }
        if ($this->add_sheet($new_fields) === false) return false;
        
        $new_sheet_id = $this->wpdb->insert_id;
        
        $tasks = $this->get_tasks($id);
        foreach ($tasks AS $task) {
            $new_fields = array();
            $task = (array)$task;
            foreach ($this->tables['task']['allowed_fields'] AS $field=>$nothing) {
                $new_fields['task_'.$field] = $task[$field];
            }
            if ($this->add_task($new_fields, $new_sheet_id) === false) return false;
        }
        
        return $new_sheet_id;
    }

    
    /**
    * Remove prefix from keys of an array and return records that were cleaned
    * 
    * @param    array   input array
    * @param    string  the prefix
    * @return   array   records that were cleaned
    */
    public function clean_array($input=array(), $prefix=false) {
        if (!is_array($input)) return false;
        $clean_fields = array();
        foreach ($input AS $k=>$v) {
            if ($prefix === false || (substr($k, 0, strlen($prefix)) == $prefix)) {
                $clean_fields[str_replace($prefix, '', $k)] = $v;
            }
        }
        return $clean_fields;
    }
    
    /**
    * Remove slashes from strings, arrays and objects
    * 
    * @param    mixed   input data
    * @return   mixed   cleaned input data
    */
    public function stripslashes_full($input) {
        if (is_array($input)) {
            $input = array_map(array('PTA_SUS_Data', 'stripslashes_full'), $input);
        } elseif (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $k=>$v) {
                $input->{$k} = $this->stripslashes_full($v);
            }
        } else {
            $input = stripslashes($input);
        }
        return $input;
    }

    public function validate_post($fields, $post_type="sheet") {
        // Create a results array that we will return
        $results = array(
            'errors' => 0,
            'message' => '',
            );
        $prefix = ( 'sheet' == $post_type ) ? 'sheet_' : 'task_';
        $clean_fields = $this->clean_array($fields, $prefix);
        // Check Required Fields first
        foreach ( $this->tables[$post_type]['required_fields'] as $required_field => $label ) {
            if( empty($clean_fields[$required_field]) ) {
                $results['errors']++;
                $results['message'] .= $label . ' is a required field.<br/>';
            }
        }

        foreach ( $this->tables[$post_type]['allowed_fields'] as $field => $type ) {
            if ( !empty( $clean_fields[$field] ) ) {
                switch ($type) {
                    case 'text':
                    case 'names':
                        if (!$this->check_allowed_text($clean_fields[$field])) {
                            $results['errors']++;
                            $results['message'] .= 'Invalid characters in '.$field.' field.<br/>';
                        }
                        break;

                    case 'textarea':
                        // For now, we allow everything in text area, but it is escaped before display on admin side,
                        // using wp_kses_post on public side to sanitize
                        // need to sanitize before saving to database
                        break;

                    case 'emails':
                        // Validate one or more emails that will be separated by commas
                        // First, get rid of any spaces
                        $emails_field = str_replace(' ', '', $clean_fields[$field]);
                        // Then, separate out the emails into a simple data array, using comma as separator
                        $emails = str_getcsv($emails_field);

                        foreach ($emails as $email) {
                            if (!is_email( $email )) {
                                $results['errors']++;
                                $results['message'] .= 'Invalid email.<br/>';
                            }
                        }
                        break;

                    case 'date':
                        if (!$this->check_date( $clean_fields[$field] )) {
                            $results['errors']++;
                            $results['message'] .= 'Invalid date.<br/>';
                        }
                        break;

                    case 'dates':
                        // Validate one or more dates that will be separated by commas
                        // Format for each date should be yyyy-mm-dd
                        // First, get rid of any spaces
                        $dates_field = str_replace(' ', '', $clean_fields[$field]);
                        // Then, separate out the dates into a simple data array, using comma as separator
                        $dates = str_getcsv($dates_field);
                        foreach ($dates as $date) {
                            if (!$this->check_date( $date )) {
                                $results['errors']++;
                                $results['message'] .= 'Invalid date.<br/>';
                            }
                        }
                        break;

                    case 'int':
                        // Validate input is only numbers
                        if (!$this->check_numbers($clean_fields[$field])) {
                            $results['errors']++;
                            $results['message'] .= 'Numbers only for '.$field.' please!<br/>';
                        }
                        break;

                    case 'yesno':
                        if ("YES" != $clean_fields[$field] && "NO" != $clean_fields[$field]) {
                            $results['errors']++;
                            $results['message'] .= 'YES or NO only for '.$field.' please!<br/>';
                        }
                        break;

                    case 'bool':
                        if ("1" != $clean_fields[$field] && "0" != $clean_fields[$field]) {
                            $results['errors']++;
                            $results['message'] .= 'Invalid Value for '.$field.'!<br/>';
                        }
                        break;

                    case 'time':
                        $pattern = '/^(?:0[1-9]|1[0-2]):[0-5][0-9] (am|pm|AM|PM)$/';
                        if(!preg_match($pattern, $clean_fields[$field])){
                            $results['errors']++;
                            $results['message'] .= 'Invalid time format for '.$field.'!<br/>';
                        }
                        break;
                        
                    default:
                        # code...
                        break;
                }
            }
        }
        return $results;
    }

    public function sanitize_sheet_fields($clean_fields) {
        $sanitized_fields = array();
        foreach ( $this->tables['sheet']['allowed_fields'] as $field => $type ) {
            if ( isset( $clean_fields[$field] ) ) {
                switch ($type) {
                    case 'text':
                    case 'type':
                    case 'position':
                        $sanitized_fields[$field] = sanitize_text_field( $clean_fields[$field] );
                        break;
                    case 'names':
                        // Sanitize and format one or more names that will be separated by commas
                        $names = str_getcsv(sanitize_text_field( $clean_fields[$field] ));
                        $valid_names = '';
                        $count = 1;
                        foreach ($names as $name) {
                            $name = trim($name);
                            if ('' != $name) {
                                if ($count >1) {
                                    $valid_names .= ',';
                                }
                                $valid_names .= $name;
                            }
                            $count++;
                        }
                        $sanitized_fields[$field] = $valid_names;
                        break;
                    case 'textarea':
                        $sanitized_fields[$field] = wp_kses_post( $clean_fields[$field] );
                        break;

                    case 'emails':
                        // Sanitize one or more emails that will be separated by commas
                        // First, get rid of any spaces
                        $emails_field = preg_replace('/\s+/', '', $clean_fields[$field]);
                        // Then, separate out the emails into a simple data array, using comma as separator
                        $emails = str_getcsv($emails_field);
                        // create an empty string to store our valid emails
                        $valid_emails = '';
                        $count = 1;
                        foreach ($emails as $email) {
                            // Only add the email if it's a valid email
                            if (is_email( $email )) {
                                if ($count > 1) {
                                    // separate multiple emails by comma
                                    $valid_emails .= ',';
                                }
                                $valid_emails .= $email;
                            }
                            $count++;
                        }
                        $sanitized_fields[$field] = $valid_emails;
                        break;

                    case 'dates':
                        // Sanitize one date
                        // Format for date should be yyyy-mm-dd
                        // First, get rid of any spaces
                        $date = str_replace(' ', '', $clean_fields[$field]);
                        // Convert the remaining string to a date
                        $sanitized_fields[$field] = date('Y-m-d', strtotime($date));
                        break;

                    case 'int':
                        // Make the value into absolute integer
                        $sanitized_fields[$field] = absint( $clean_fields[$field] );
                        break;

                    case 'bool':
                        if ($clean_fields[$field] == true) {
                            $sanitized_fields[$field] = true;
                        } else {
                            $sanitized_fields[$field] = false;
                        }
                        break;

                    default:
                        // return any other fields unaltered for now
                        $sanitized_fields[$field] = $clean_fields[$field];
                        break;
                }
            }
        }
        return $sanitized_fields;
    }

    public function check_allowed_text($text) {
        // For titles and names, allow letters, numbers, and common punctuation
        // Returns true if good or false if bad
        return !preg_match( "/[^A-Za-z0-9\-\.\,\!\&\(\)\'\/\?\ ]/", stripslashes($text) );
    }

    public function check_date($date) {
        // Our dates should be in yyyy-mm-dd format.  Convert/Reject if not
        // Checks to see if it's a valid date
        // Returns true if good, false if bad
        if ($date = "0000-00-00") return true;
        $date = str_replace(' ', '-', $date);
        $date = str_replace('/', '-', $date);
        $date = str_replace('--', '-', $date);
        if ( '' == $date ) return false;
        preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $xadBits);
        if (count($xadBits) < 3) return false;
        return checkdate($xadBits[2], $xadBits[3], $xadBits[1]);
    }

    public function check_numbers($string) {
        // Returns true if string contains only numbers
        return !preg_match("/[^0-9]/", stripslashes($string));
    }

    public function get_sanitized_dates($dates) {
        // Sanitize one or more dates that will be separated by commas
        // Format for each date should be yyyy-mm-dd
        // First, get rid of any spaces
        $dates = str_replace(' ', '', $dates);
        // Then, separate out the dates into a simple data array, using comma as separator
        $dates = str_getcsv($dates);
        $valid_dates = array();
        foreach ($dates as $date) {
            if ($this->check_date( $date )) {
                $valid_dates[] = $date;
            }
        }
        return $valid_dates;
    }
    
}
/* EOF */