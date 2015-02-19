<?php

/**
 * ITIS - PHPDebug Class 
 * @author Mike Corlett
 * @version 1.1 
 * @package mikecouk
 * @about Generic file logging and debugging class I wrote in 2003, thats along time ago :)
 */

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// --~==CVS Information==~--  <--- oooo originally in CVS, then in Subversion, then in GitHub !
//
// $Author: corlettm $
// $Date: 2005/10/25 08:32:31 $
// $Revision: 1.29 $
// $Source: /apps/cvs/Infrastructure/mike/code/extract/phpdebug.class,v $
//
// Title         : PHPDEBUG.CLASS - PHP Class for debugging and info / error loggin
// Author        : Mike Corlett
// Description   : This class is included and called in almost all php applications and scripts
//                 It provides a generic interface to writing logging / debugging information
//                 This class is located in the 'extract' directory, as this is where the class began
// Functions ....
//
//   Constructor phpdebug($log_path, $log_filename, $log_type)
//   function write_info($string) {
//   function write_info_extra($string)
//   function write_info_nodate($string)
//   function write_error($string) 
//   function truncate_log_on_size(filesize_in_bytes_before_truncation)
//   function close() 
//   function count_errors()
//   function display_errors() 
//   function array_tree($array , $prep='')
//   function get_log_filename() 
//   function get_phpdebug_version()
//   function disable_email()
//   function enable_email()
//   function set_email_topic($string) 
//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

class phpdebug {

 private $fp;																		// File pointer for output log file
 private $number_of_errors = 0;									// Track how many error messages have been recorded
 private $full_log_filename = "";								// Name of the log file to return when asked
 private $email_errors = "TRUE";								// Email errors to me, can be turned off with function
 private $log_type = "";
 private $email_address;
 private $buffer = "";
 private $current_user = "";										// Optional variable containing the current user
 private $function_call_disable = false;				// So we have the ability to disable a function call
 private $class_name;														// Name of a calling class
 private $debug_level     = 0;									// Level of debugging we want
 private $max_debug_level = 5;									// max level of debug we can go to
 private $debug_type      = "file";							// screen or file
 private $email_topic			= "PHP Debug Error";  // This is the subject of the email, and can be overwritten
 private $truncate_log    = "no";								// Whether to truncate log or not
 private $trace;																// full trace of this instance
 private $startup_time;													// time reference for when the logging started, we use this in all lines ( new august 2012 )
 private $previous_class_name;
 private $default_class_name = "default";
	 
 public function __construct($log_path, $log_filename, $log_type) {

  // $log_type definitions : "date-stamped", "append"
  $this->log_type = $log_type;

  if ($log_filename == "") {
   $log_filename = "noname";
	}

	$this->class_name = $this->default_class_name;

	$this->startup_time = date("His");

	// Ok next rule, because a script running as root might use the same class as apache, they can have
	// permission conflicts after the file is truncated, i.e root creates the log file first, then apache
	// can't write to it either. Therefore, create a seperate log file for scripts .......
	$server_name = $_SERVER['SERVER_NAME'];
	if ($server_name == "") {
		$server_name = "script";
	}

	$log_filename = $server_name . "_" . $log_filename;

  // Replace any hashes in the filename with a minus
  // If the input file is in a subdirectory, there WILL be a slash character in the filename !
  // The reason we do this is that we pass in the PATH and the FILENAME as two seperated variables
  // Therefore the FILENAME part does not need to have any 'slashes' in it.
  $log_filename = str_replace("/", "-", $log_filename);
  $today = date("d-m-y-Hi");
  if (($_SERVER["SERVER_ADMIN"] == "") || ($_SERVER["SERVER_ADMIN"] == "webmaster@domain_name.com")) {
	  $this->email_address = "mike.corlett@gmail.com";
  } else {
  	 $this->email_address = $_SERVER["SERVER_ADMIN"]; // Get the email address from the administrator email address
  }

  if ($log_type == "date-stamped") {
   $filename = $log_path . "/" . $today . "-" . $log_filename ;
	 $this->fp = fopen($filename, "w");
   $this->full_log_filename = $filename;			// assign the filename to $this
   $this->write_info("phpdebug opened $filename ( datestamp mode ) for writing ...\n");
   // $this->write_info("phpdebug is using the email address " . $this->email_address . " for sending emails  ...\n");
  } elseif (($log_type == "append") || ($log_type = "append-simple")) {
   $filename = $log_path . "/" . $log_filename ;
   $this->full_log_filename = $filename;			// assign the filename to $this
   if (file_exists($filename)) { 
    $open_type = "a";
   } else {
    $open_type = "w";
	 }
	 if (! $this->fp = fopen($filename, $open_type)) {
		  mail($this->email_address, "PHPDEBUG Internal Error", "PHPDEBUG could not open debug log file ( $filename ) in $name_of_this_script\nIP was " . $_SERVER["REMOTE_ADDR"] ."\n$string\n");
	 }

   if ($log_type != "append-simple") {
     fputs($this->fp, "\n\n\n");
     fputs($this->fp, "PHPDEBUG CLASS appending $filename at $today\n");
     // fputs($this->fp, "phpdebug is using the email address " . $this->email_address . " for sending emails  ...\n");
     fputs($this->fp, "==================================================================================================\n");
   }
  } else {
   echo "\nFatal Error in phpdebug class, class initiator called, \n";
   echo "and log_type was either not passed or not of correct definition. \n";
   echo "NOTE : phpdebug class has changed in the past, and requires an\n";
   echo "       extra parameter to function correctly.\n";
   exit;
	}


	// Truncate your own log files if the size of the files gets bigger than 5 meg
	$this->truncate_log_on_size(5120000);

 }

 public function __destruct() {

	  // fputs($this->fp, "DEBUG : " . filesize($this->full_log_filename) . " AND " .  $this->truncate_log . "\n");
    fclose($this->fp);

		if ($this->truncate_log != "no") {
			if (file_exists($this->full_log_filename)) {
				if ((filesize($this->full_log_filename) > $this->truncate_log)) {
					unlink($this->full_log_filename);
				} 
			}
		}

 }

 public function truncate_log_on_size($bytes) {

	 $this->truncate_log = $bytes;

 }


 public function write_info($string) {

  $this->flush_buffer();
  $this->final_write($string);
  
 }

 public function set_email_topic($string) {

	 // Changes the topic of the email to something customisable
	 // Only change it if it meets minimum / maximum length requirements ...... 
	  
   $minimum_topic_length = 0;
	 $maximum_topic_length = 255;

	 if ((strlen($string) > $minimum_top_length) && (strlen($string) < $maximum_topic_length)) {

		 $this->email_topic = $string;

	 }

 }
	 
 public function set_debug_level($debug_level) {
	 if (($debug_level > -1) && ($debug_level <= $this->max_debug_level)) {
	   $this->debug_level = $debug_level;
	 } else {
		 $this->debug_level = 0;
	 }
 }

 public function set_debug_type($debug_type) {

	 if (($debug_type == "file") || ($debug_type == "screen")) {
		 $this->debug_type = $debug_type;
	 } else {
		 $this->debug_type = "file";
	 }
 }

 public function debug($string, $level) {

	 if ($level <= $this->debug_level) {
		  if ($this->debug_type == "file") {
			    $this->write_info("DB$level : $string");
			} else {
				  $this->write_info_extra("DB$level : $string");
			}

		}
	}

 public function set_user($string) {

	 $this->current_user = $string;

 }
 
 public function email_user($user, $topic, $string) {

	 if ($user == "admin") {
		 $email_to_use = $this->email_address;
         } else {
		 $email_to_use = $user;
	 }
         $this->write_info($string);
	 $email_text = "Date : " . date ("Y-m-d H:i:s", time()) . "\n";
	 $email_text .= "Server : " . $_SERVER["REMOTE_ADDR"] . "\n";
	 $email_text .= "Info : " . $string . "\n";
	 mail($email_to_use, $topic, $email_text);

 }

 public function set_class($string) {

	 $this->previous_class_name = $this->class_name;
	 $this->class_name					= $string;
	 $this->set_email_topic($string); // By default give the email subject the class name

 }

 public function unset_class() {

	 $this->class_name = $this->previous_class_name;
	 $this->previous_class_name = $this->default_class_name;

	}

 public function email_info($string) {

	 $this->write_info($string);
	 $email_text = "Date : " . date ("Y-m-d H:i:s", time()) . "\n";
	 $email_text .= "Server : " . $_SERVER["REMOTE_ADDR"] . "\n";
	 $email_text .= "Info : " . $string . "\n";
	 mail($this->email_address, "PHP Email Info", $email_text);
	 
 }
 
 private function final_write($string) {
	 
  	$timestamp      = date ("H:i:s", time());
	$remote_address = $_SERVER["REMOTE_ADDR"];

	$write_output = $this->startup_time . " : $timestamp : ";	// add the startup time as a 6 digit number, to help reference instances

	// Only add the remote address variable if it actually exists, it's blank on many occasions !
	if ($remote_address != "" ) {
		$write_output = $write_output . " $remote_address : ";
	}

	if ($this->current_user != "") {
		$write_output .= "[" . $this->current_user . "] : ";
	}
	if ($this->class_name != "") {
		$write_output .= "*" . $this->class_name . "* : ";
	}
	$write_output .= $string;
	
	fputs($this->fp, $write_output);
	$this->write_to_trace($write_output);
  
 }

 private function write_to_trace($string) {

	 $this->trace = $this->trace . " " . $string;

 }

 public function get_trace() {

		return $this->trace;

 }

 function flush_buffer() {

	 if ($this->buffer != "") {
		 $final_write($this->buffer);
		 $this->$buffer = "";
	 }

 }
 
 function write_info_extra($string) {

  echo $string;
  $this->write_info($string);
 
 }

 function write_info_nodate($string) {
 
  // Write a string to a file with no timestamp
  fputs($this->fp, $string);
  
 }

 function write_error($string) {

	$this->email_topic = "PHP Debug Error : " . $this->class_name;
  $this->write_info($string);
  $this->number_of_errors ++;
  $name_of_this_script = $_SERVER['PHP_SELF'];
  if ($this->email_errors == "TRUE") {
     mail($this->email_address, $this->email_topic, "PHP detected the following error in $name_of_this_script \r\nIP was " . $_SERVER["REMOTE_ADDR"] ." \r\n $string \r\n Trace below; \r\n". $this->get_trace() . "\r\n");
  }
  // This next line doesn't seem to work correctly
  // trigger_error($_SERVER["REMOTE_ADDR"] . " : " . $name_of_this_script . " : " . $string, E_USER_WARNING);
  
 }

 function write_error_onscreen($string) {

	 echo "$string";
	 $this->write_error($string);

 }

 function write_html($string ) {
	 echo $string;
 }

 function write_buffer($string) {
	 $this->buffer .= $string;
 }

 function close() {

	$this->set_class("Closing Summary");

  if ($this->number_of_errors > 0) {
   $this->write_info("phpdebug class closing - received " . $this->number_of_errors . " errors whilst it was open\n");
   $name_of_this_script = $_SERVER['PHP_SELF'];
   if ($this->email_errors == "TRUE") {
      mail($this->email_address, "PHPDEBUG : " . $this->email_topic, "PHP detected $this->number_of_errors errors during phpdebug\nName of script was $name_of_this_script\n");
   }
  } else {
   if ($this->log_type != "append-simple") {  
     $this->write_info("phpdebug class closing - didn't have to write out any errors, which is rather nice.\n");
   }
  }
  if ($this->log_type != "append-simple") {  
     fputs($this->fp, "====================================================================================================================\n");
  } else {
    // During a append-simple write, echo a blank line at the end of each session, make it look more readable
     fputs($this->fp, "\n");
  }

 }
 
 function count_errors() {
  // Return the number of errors encountered so far 
  return $this->number_of_errors;
 } 

 function display_errors() {
  // Write out how many errors ahve occured so far
  $this->write_info("PHP Debug class has received " . $this->number_of_errors . " write_error's so far ..... \n");
 }
 
 function display_array($myarray, $description) {
  $array_expanded = $this->array_tree($myarray);
  $this->write_info("Array output for $description \n\n" . $array_expanded . "\n\n");
 }
 
 function array_tree($array , $prep='') {
  if(!is_array($array)) {
   print '<b>array_tree:</b> This is not an array';
   return false;
  }
  $prep .= '|';
  while(list($key, $val) = each($array)) {
   $type = gettype($val);
   if(is_array($val)) {
    $line = "-+ $key ($type)\n";
    $line .= $this->array_tree($val, "$prep ");
   } else {
    $line = "-> $key = \"$val\" ($type)\n";
   }
   $ret .= $prep.$line;
  }
  return $ret;
 }

 function get_log_filename() {
  return $this->full_log_filename;
 }
 
 function get_phpdebug_version() {
  $Revision = "";
  $result = "$Revision: 1.29 $";
  return $result;
 }

 function disable_email() {
  // Disable emailing people
  $this->email_errors = "FALSE";
 }
 
 function enable_email() {
  // re-enable emailing people
  $this->email_errors = "TRUE";
 }

 function user_action($string) {
   // Record a user action
   $this->write_info("USER ACTION   --> " . $string . "\n");
 }

 function function_call($string) {
   // Record a call to a function
   if ($this->function_call_disable != true) {
     $this->write_info("FUNCTION CALL --> " . $string . "\n");
   }
 }

 function disable_function_call() {
	 // Disables function call
	 $this->function_call_disable = true;
 }

}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

?>
