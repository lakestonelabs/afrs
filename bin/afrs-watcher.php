#!/usr/bin/php 
<?php
// afrs-logger monitors filesystem changes.
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
// 
//
// ToDos:   * Implement -o afrs option and start doing cool shit for replication.
//			* Need to validate all option paramaters and error out accordingly.
//			* Parse cli.ini file to determine if php-inotify library is compiled and installed.
//			* Need to figure out how to handle files/dirs that are deleted and we are called using the -u or -g
//			  options since we have no owner info to compare to since the file was deleted and therefore we
//			  can't get any user info.
//			* (FINISHED: 7/2/2010)   Currently not complete and somewhat broken.
//			* Need to support ACLs.
//			* Need to figure out how to get the inotify_rm_watch() function to work.  I suspect there may be a bug
//			  in how the php version of this function is implemented since when passing the watch descriptor to
//			  this function, as directed in the doco, the function errrors stating that the watch descriptor that
//			  was provided is invalid.  This is needed for when -r option is enabled and sub dirs are deleted.
//			* When using -o afrs option, need to figure out a better way to get the server SID instead of using a db query.
//			* Need a dynamic way to exclude the afrs-watcher.log file, or whatever log file is specified, when using the -o log
//			  or afrsd -v option.
//			* Print how to increase inotify watch limit to use if limit is reached.  
//			
//	
//	Last status:
/*
 *		   (06/12/2010) (v0.9)	* Made the -l option work for not allowing to follow symbolic links.
 *		   (06/06/2010)		* Removed -y option for "yelling" and now by default afrs-watcher will not report on files that
 *							  are changed very frequently (i.e. temp download files).  I may add this option in the future to
 *							  allow yelling, but for now, I can't see a reason to add this functionality.
 *		   (06/11/2010)		* If ran with -r option and new sub dirs are created, the program will watch those also.
 *		   (06/12/2010)			* Reserved -s option to output to clamscan (ClamAV).  This option will scan changed files for viruses before
 *							  reporting them.  If a file is infected then the file will be logged and the appropriate error
 *							  message will be outputed to the specified output type.
 *		   (06/12/2010)		* Now checks if running user is root when specifying to watch the '/' root directory.
 *		   (06/12/2010)		* Removed the -x option for using XML files to specify watch locations and options.
 *		   (06/16/2010) (v0.9.1)	* Removed the -w option to wait for x amount of file changes before outputing the changes.
 *							* This was removed due to complexity problems when used in conjunction logic to not report
 *							* on yelling/loud files.
 *		   (06/6/2010)			* Fixed problem of not reporting correct file sizes when under heavy load (tons of files being changed at the same time.)
 *		   (07/2/2010)			* Finish all logic pertaining to -o afrs.
 *		   (07/2/2010)	 (v0.9.5)	* Hardcoded to exclude the afrs-watcher.log file from being watch (causes endless change reporting loop).
 *							  Need to make this more dynamic when afrs-watcher -o log is specifed to not watch the log file the user specifies.
 *
 */
//		   11/15/2008 (v0.3.2):  * Fixed missing value when using %t shortcut.  The date was only defined if the output 
//								   type was afrs.  Now it works for any output type.
//								 * Reserved the -p option for future developement.
//								 * Completed the implementation of the -u and -g options.
//		   (11/14/2008)(v0.3.1)  * Implemented shortcuts to use in the -c option so certain information about the 
//								  file changes can be used by the command the user supplies.
//								 * Fixed stupid previous logic to determine watched directory.
//								 * Misc. code cleanups. 	
//		   (8/17/2008)(v0.2.0):  * Was working on db design and also getting/creating the watch id db records
//				  				   for the watch path specified when output is afrs.
//		   (11/15/2009)(v0.2.1): * Modified logic for not reporting on hidden files.  It now will not report files ending in '~'.
//								   This accounts for VI's temp files it creates.


// END USERS SHOULD NOT MODIFY ANYTING IN THIS FILE.

require(__DIR__."/../conf/afrs_config.php");
require(__DIR__."/../conf/afrs_vars.php");
require(__DIR__."/../includes/FSNotify.php");
require(__DIR__."/../includes/functions.php");
require_once(__DIR__."/../includes/orm/rb.php");  // The ORM technology used by Afrs.

$afrs_watcher_frequency = 500000;
$afrs_watcher_log_file_handle = null;
$afrs_watcher_log_file_location = __DIR__."/../logs/afrs-watcher.log";
$call_options = null; // An array that will hold the options passed to this program.
$watch_path = null;
$watch_answers = null;  // Will store the array of watches returned from inotify_read();
$exclude_list = array();
$watch_events = array();
$frequency = null;
$run_command = null;
$output_type = null;
$report_user = null;
$sid = null;  // Used in building the afrs messages when sending watch events to an afrs daemon.
$membership_password = null;
$report_group = null;
$output_file_pointer = null;
$mydbconn = null; // Used for afrs output type.
$local_event_map = null;  // Will store the events returned from FSNotify::getEvents().
$prev_file_info = null;  // Holds information about the previous file info.  Used in the event that the -y option is set.
$sym_link_count = 0;
$output_to_file = false;
$watch_id = null;  // Used when the -o afrs,-i,-j options are used for building the afrs messages to send to the daemon.
$daemon_socket_connector = null;  // The stream_socket_client resource use to write watch events to the daeomon when -o is afrs.
$tick = null;  // Used to keep track of how many times the invinite "while" loop has been run.

$changed_file_array = null;  // A temp storage place to hold changed file stat info.
$output_wait_timeout = 2;
$output_buffer_array = null;  // Holds the list of files to output specified by the -o option.  All changed files are first processed and then 
						// depending on the options specified are added to this buffer.  After all changes files for each iteration are 
						// processed, then we process this array and output each listing to the output type specified at runtime.
$last_daemon_write = 0;  // The last time we wrote file change information to the afrs_daemon.  Used in conjunction with $output_wait_timeout .
$afrs_message_file_list = null;

$svn = null;  // The full path to the svn program, if it exists on the system.
$svnadmin = null;  // The full path to the svnadmin program if it exists on the system.
$svn_afrs_home = null;  // The full path to where afrs-watcher will use as it's starting point to create svn repos.

if ($argc >= '2')  // See if we were called correctly.
{
	$mynotify = new FSNotify();  // Creates an inotify_init() system call and FSNotify object.
	
	$call_options = $argv;
	$inotify_proc_dir = "/proc/sys/fs/inotify";  // Where are the inotify system stat files.
	$inotify_proc_max_user_watches = "max_user_watches";
	
	// Get our watch path and set the variable.
	
	$watch_path = $argv[$argc-1]; // Per the usage statement the watch path is always the last argument.
	if ($watch_path == "/" && posix_getuid() != 0)
	{
		echo "ERROR:  Only root can do that and you are not root\n";
		exit(1);
	}
	else if ( $watch_path != "/")  // Replace trailing forward slash if anything other than root path "/"
	{
		$watch_path = $path = ereg_replace("\/$", "", $watch_path); // Check to see if they had a / at the end of the path listing.  If so, remove it.;
	}

	if (in_array("--help", $call_options))
	{
		print_usage();
	}

	if (in_array("-c", $call_options))
	{
		$c_index = array_search("-c", $call_options); // Get the position of the -c option in array.
		$run_command = $call_options[$c_index + 1];
	}

	// TODO:  Need to exclude all afrs related files from being watched since this could cause problems.
	if (in_array("-e", $call_options))
	{
		$e_index = array_search("-e", $call_options); // Get the position of the -e option in array.
		if ($e_index + 1 < sizeof($call_options))  // Make sure the exclude list is not the last argument, if so then we were called wrong.
		{
			// TODO:  Need to fix the below preg_match since it will match on ecluded filename with dashes in them.
			//if (preg_match("/-/", $call_options[$e_index + 1]) == 0)  // Make sure the eclusion list is not another option.
			//{
				$exclude_list = preg_split("/ /", addcslashes($call_options[$e_index + 1], "/")); // Build and array of exclusions.
			//}
			//else
			//{
			//	print_usage();
			//}
		}
		else
		{
			print_usage;
		}
	}
	// TODO:  Need to make this dynamic base on what the user chooses to use as the log file and where it's located.
	$exclude_list[] = "afrs-watcher.log"; // Don't watch the log file in case it's located in the same location being watched.
	
	if (in_array("-f", $call_options))
	{
		$f_index = array_search("-f", $call_options); // Get the position of the -f option in array.
		if (is_int($call_options[$f_index + 1]))  // Make sure the user specified a valid integer for the frequency.
		{
			$frequency = $call_options[$f_index + 1];
			if ($frequency < 200000)
			{
				echo "WARNING:  Frequency can't go below 200000 milliseconds (.2 seconds).  Defaulting to .2 seconds.\n";
				$frequency = 200000;
			}
		}
		else
		{
			echo "Your frequency entered is not a valid integer\n";
			print_usage;
		}
	}
	else
	{
		$frequency = $afrs_watcher_frequency;  // Default is 1/2 second, .5 seconds, 500000 milliseconds.
	}
	
	if (in_array("-E", $call_options))
	{
		$bige_index = array_search("-E", $call_options); // Get the position of the -E option in array.
		$watch_events = split(",", $call_options[$bige_index + 1]);
		
		// Check to make sure that each event entered by the user is valid.
		$local_event_map = $mynotify->getPossibleEvents();
		foreach($watch_events as $check_this_event)
		{
			if (!in_array($check_this_event, $local_event_map))
			{
				echo "\nERROR: The event you specified: " . $check_this_event . " is not valid\n";
				print_usage();
			}
		}
	}
	// If no events to look for were supplied, build the default event list.
	else
	{
		$watch_events[] = "create";
		$watch_events[] = "delete";
		$watch_events[] = "modify";
		$watch_events[] = "move";
	}
	
	if (in_array("-D", $call_options) && in_array("-d", $call_options))	// Can't have both -D and -d as it would be impossible to watch anything.
	{
		echo "ERROR: Can't specify both -D and -d options since this nothing would be watched\n";
		print_usage();
	}
	
	if (in_array("-o", $call_options))
	{
		$o_index = array_search("-o", $call_options); // Get the position of the -o option in array.
		$output_type = $call_options[$o_index + 1];
		
		if ($output_type != "stdout" && $output_type != "afrs" && $output_type != "svn" && $output_type != "quiet" && $output_type != "log")
		{
			$output_to_file = true;  // Used later on to determine if our output type is a file.
			// Check to see if the output is a output file and if it exists.
			if(!file_exists($output_type))
			{
				echo "\n ERROR: You did not enter a valid output type or file.\n";
				print_usage();
			}
			else
			{
				$output_file_pointer = fopen($output_type, "a") or die;  //Open the output file for writing.
				if (!$output_file_pointer)
				{
					echo "FATAL ERROR:  Could not open output file, please check permissions.\n";
					exit(1);
				}
			}
		}
		else if ($output_type == "afrs")
		{
			if (in_array("-i", $call_options))
			{
				$i_index = array_search("-i", $call_options);
				$daemon_ip_port_location = $call_options[$i_index + 1];

				$daemon_socket_connector = stream_socket_client($daemon_ip_port_location, $errno, $errstr, 10);  // TODO Need to make this SSL.
				if (!$daemon_socket_connector)
				{
					echo "FATAL ERROR: (NETWORK) Could not connect to AFRS Daemon at ". $daemon_ip_port_location . ".  Is the Daemon running?\n";
					exit(1);
				}
				else
				{
					if (in_array("-j", $call_options))
					{
						$j_index = array_search("-j", $call_options);
						$watch_id = $call_options[$j_index + 1];

						// TODO:  Need to determine another way to get the sid for building the afrs messages.
						//		We should only use the daemon socket to communicate and retreive information.  I.E.  This is a cheat.
						echo "Successfully connected to AFRS Daemon at: " . $daemon_ip_port_location . "\nRetrieving SID for this machine...";
						                                                
                                                R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);
                                                $result_bean = R::findOne("registry", " name = 'sid' ");
                                                $sid = $result_bean->value;
                                                //$sid = R::getCell("select value from tbl_registry where name = 'sid'");
                                                if (sizeof($sid) > 0)
                                                {
                                                    echo "Done\n";
                                                }
                                                

						if (in_array("-v", $call_options))
						{
							if (file_exists($afrs_watcher_log_file_location) && is_writeable($afrs_watcher_log_file_location))
							{
								$afrs_watcher_log_file_handle = fopen($afrs_watcher_log_file_location, "a");
							}
							else
							{
								echo "ERROR:  " . $afrs_watcher_log_file_location . " is not writeable or does not exist.  Please fix!  Terminating now!\n";
								exit(1);
							}
						}
					}
					else
					{
						echo "ERROR: You did not specify the -j option for the watch-id to be used when sending watch messages to the daemon.\n";
						exit(1);
					}
				}
			}
			else
			{
				echo "ERROR:  You did not specify the -i option for the afrs daemon socket connection.\n";
				exit(1);
			}
		}
		else if ($output_type == "log")
		{
			if (file_exists($afrs_watcher_log_file_location) && is_writeable($afrs_watcher_log_file_location))
			{
				$afrs_watcher_log_file_handle = fopen($afrs_watcher_log_file_location, "a");
			}
			else
			{
				echo "ERROR:  " . $afrs_watcher_log_file_location . " is not writeable or does not exist.  Please fix!  Terminating now!\n";
				exit(1);
			}
		}
                else if ($output_type =="svn")
                {
                    // First lets see if Subversion is installed on this system.
                    $svn = whereis("svn");
                    $svnadmin = whereis("svnadmin");
                    
                    if ($svn !== false && $svnadmin !== false) // If both programs where found on the system.
                    {
                        // Check that our afrs subversion directory exists for storing changes.
                        $svn_afrs_home = __DIR__."/../watcher-changes/svn";
                        if (file_exists($svn_afrs_home))
                        {
                            $exec_output = null;
                            $exec_return_var = null;
                            // Make sure that a repository exists at this location.  If not create one.
                            exec("$svnadmin verify $svn_afrs_home", $exec_output, $exec_return_var);
                            
                            if ($exec_return_var == 0)
                            {
                                echo "Verifyied afrs-watcher svn repository at " . $svn_afrs_home . "\n\n";
                                exit(0);
                            }
                            else
                            {
                                echo "Failed to verify afrs-watcher svn repository. \n\n";
                                exit(1);
                            }
                        }
                        else
                        {
                            echo "FATAL ERROR:  afrs-watcher svn directory ($svn_afrs_home) does not exist.  Please fix and try again.\n\n";
                            exit(1);
                        }
                    }
                    else
                    {
                        echo "FATAL ERROR:  The 'svn' option cannot be used since subversion cannot be found on this system.\n";
                        exit(1);
                    }
                    
                }
	}
	else
	{
		echo "\nERROR: You must specify an output type\n";
		print_usage();
	}
	
	if (in_array("-u", $call_options))
	{
		$u_index = array_search("-u", $call_options); // Get the position of the -u option in array.
		if (preg_match("/-/", $call_options[$u_index + 1]) == 0 && ($u_index + 2) != $argc)  // Make sure the uid is not another option and make sure the uid is not the last argument.
		{
			$report_user = $call_options[$u_index + 1];
		}
		else
		{
			echo "ERROR:  You did not provide a username for the -u option\n";
			print_usage();
		}
	}
	
	if (in_array("-g", $call_options))
	{
		$g_index = array_search("-g", $call_options); // Get the position of the -g option in array.
		if (preg_match("/-/", $call_options[$g_index + 1]) == 0 && ($g_index + 2) != $argc )  // Make sure the uid is not another option and make sure the uid is not the last argument.
		{
			$report_user = $call_options[$g_index + 1];
		}
		else
		{
			echo "ERROR:  You did not provide a group name for the -g option\n";
			print_usage();
		}
	}
	
	// Open the proc inotify max file to see what the max watch size is.
	$inotify_max_fp = fopen($inotify_proc_dir."/".$inotify_proc_max_user_watches, "r");  // Open the file.
	$inotify_max = fgets($inotify_max_fp);  // Read the file's contents (one line).
	fclose($inotify_max_fp);  // Close the file
	
	if ($output_type == "quiet")
	{
		if (!in_array("-c", $call_options))
		{
			echo "ERROR:  Your output type is 'quiet' but you did not supply the -c option to run any command.  
Therefore this program will do nothing when events are triggered. Please either change your 
output type or supply a command to run with the -c option.\n";
			exit(1);
		}
	}
	
	if (is_dir($watch_path))
	{
                if (in_array("-v", $call_options))
                {
                    echo "\nFinding directories to watch...";
                }
		if (in_array("-r", $call_options))  // Recursively find directories is we were called with the -h option.
		{
                    $dirs = (dir_search($watch_path));  // Recursively start searching for directories to watch.
                    $dirs[] = $watch_path;  // Don't forget to append our starting directory to the list to be watched.
                }
		else
		{
			$dirs[] = $watch_path;  // If not recursively watching directories then our first path is the only directory to watch.
		}
                //var_dump($dirs);
                //exit(0);
		
		
		if (sizeof($dirs) <= $inotify_max)  // Need to get rid of this since it is irrelevant since I figured out how inotify actuall works.
		{
			// Determine if we are to be verbose in our output.  Pretty basic at this point as it only displays the number of directories to be watched.
			if (in_array("-v", $call_options))
			{
				echo "Done.  Found " . sizeof($dirs) . " directories ";
				if (!in_array("-l", $call_options))
				{
					echo "and " . $sym_link_count . " symlinks.\n\n";
				}
				else
				{
					echo ".\n\n";
				}
			}
		
			$mynotify->startWatching($dirs, $watch_events);  // Register the watched paths (directories) with inotify and watch the for changes.

			$tick = 1;  // Initialize at 1 so our algorithm to determin if a file is loud/yelling works.

			while(true) // Start watching for events and do stuff.
			{
				if ($mynotify->getWatchQueueSize() > 0)  // Are there events in the inotify queue?  This gets around our blocking problem.
				{
					$watch_answers = ($mynotify->readEvents());

					foreach($watch_answers as $this_answer)
					{
						$changed_file_array = null;  // Reset/initialize on each loop.
						$wd = (($this_answer["wd"])-1);  // Get the watch descriptor number and -1 so we can index the directory for the file.
						$watch_name = $this_answer["name"];  // Get the name of the file that changed
						
						$current_file_info = null;  
				 		$uid = null;
				 		$user_info = null;
				 		$uid_name = null;
				 		$gid = null;
				 		$group_info = null;
				 		$gid_name = null;
				 		$file_size = null;
				 		$posix_permissions = null;
				 		$file_action = null;
						$file_type = null;
						$date_time = null;
						$posix_time = null;
						
						if (in_array("-h", $call_options))  // Were we suppose to forget hidden files?
						{
							if (preg_match("/^\.|\~$/", $watch_name) > 0) // If we find a hidden file, skip to next interation in loop.
							{											  // This also accounts for vi's '~' temp files.
								continue;
							}
						}
						
						if (in_array("-e", $call_options) || sizeof($exclude_list) > 0)  // See if the current watched file is in the exlude list.  If so skip.
						{													// Check even if -e is not specified (afrsd -v option).
							$should_exclude = false;
							foreach($exclude_list as $this_exclude)
							{
								if (preg_match("/" . $this_exclude . "/", $watch_name) > 0)
								{
									$should_exclude = true;
								}
							}
							if ($should_exclude)
							{
								continue;
							}
						}
						
						$full_name = $dirs[$wd] . "/" . $watch_name;  // Build the absolute patch and filename.
						
						if (in_array("-D", $call_options))  // Check to see if we are set skip directories and the watch file is a directory.
						{
							if (is_dir($full_name))
							{
								continue;
							}
						}
						
						if (in_array("-d", $call_options))  // Check to see if we are set to watch only directories.  If the file is not a directory then skip it.
						{
							if (!is_dir($full_name))
							{
								continue;
							}
						}
						
						
						/*if ($output_type == "afrs")
						{
							// IMPORTANT:  Need to put logic here to check to see if the file we are dealing with was recorded 
							// because of a sync from another system.  If so we need to ignore the file change so we don't 
							// create possible sync loops and get loud reporting.
							// 
							// I imagine is goes something like this;  
							// 1.)	Check the database to see if the it's table about the remote end syncing status 
							//		contains the file we are looking at.
							// 2.)	If so, skip this file and go to next file.

							
						}*/
						
						$mask_number = $this_answer["mask"];
					 	$file_action = $mynotify->translateEvent($mask_number);
					 	// Need to check file action first in case it was deleted and therefore we can't get the file stats.

						// If a direcotry was created and the -r (recursive) was supplied, add this newly created directory to the list to be watched.
						if ($file_action == "dir_created" && in_array("-r", $call_options) && !in_array($full_name, $dirs))  
						{
							$mynotify->appendWatch($full_name);
							$dirs[] = $full_name;  // Now add the newly watched directory to the dirs array.
						}
						else if ($file_action == "dir_closed") // We don't care about dir_closed events.
				 		{
				 			continue;
					 	}
						else if ($file_action == "ignored") // We don't care about ignored events.
				 		{
				 			continue;
					 	}
						else if ($file_action == "delete" || $file_action == "dir_deleted")
					 	{
                                                    
                                                        
                                                    $file_type = "unknown";
                                                    if ($file_action == "delete")
                                                    {
                                                        $file_type = "file";
                                                    }
                                                    // TODO: The below logic to remove the dir from being watch is currently broken since I can't get
                                                    // to call inotify_rm_watch to work.  There may be a bug in how the php version of this
                                                    // function was implemented.
                                                    else if ($file_action == "dir_deleted")  // Remove the deleted directory from being watched.
                                                    {
                                                        $file_type = "directory";
                                                        if($mynotify->removeWatch($full_name))
                                                        {
                                                                $index_location = array_search($full_name, $dirs);
                                                                unset($dirs[$index_location]);  // Delete the array data at $index_location.
                                                                $dirs = array_merge($dirs, $temp_array = array());  // Defragmenting array.  Removed null space locations from the array.
                                                        }
                                                    }
					 	}
						if ($file_action != "delete" && $file_action != "dir_deleted") // We can't stat deleted stuff.
					 	{
							// Now we to the meat of this application.  Start doing stuff.
							clearstatcache();  // Get acurate file stat info.
							if (file_exists($full_name))
							{
								$file_stat = stat($full_name);  // Get the attributes about the file.
								$file_type = (string)filetype($full_name);  // Cast return filetype into type string.
								
								if ($file_stat != false)  // Check to see if something went wrong with the file between operations, like disapearing.
						 		{	
							 		$file_stat["type"] = $file_type;  // Append the file type to the stat array.
							 		$uid = $file_stat["uid"];
							 		$user_info = posix_getpwuid($uid);
							 		$uid_name = $user_info["name"];
							 		$gid = $file_stat["gid"];
							 		$group_info = posix_getgrgid($gid);
							 		$gid_name = $group_info["name"];
							 		$file_size = $file_stat["size"];
							 		$posix_permissions = substr(sprintf('%o', fileperms($full_name)), -4); // Formats for human readability.
						 		}
							}

							if (in_array("-z", $call_options) && ($file_size == 0 || $file_size == null || $file_size == ""))
							{
								continue;
							}

							if (in_array("-u", $call_options) && $uid_name != $report_user)
							{
								continue;
							}
					 		if (in_array("-g", $call_options) && $gid_name != $report_group)
							{
								continue;
							}
					 	}

						$changed_file_array["full_name"] = $full_name;
						$changed_file_array["file_type"] = $file_type;
						$changed_file_array["file_action"] = $file_action;
						$changed_file_array["uid"] = $uid;
						$changed_file_array["uid_name"] = $uid_name;
						$changed_file_array["gid"] = $gid;
						$changed_file_array["gid_name"] = $gid_name;
						$changed_file_array["file_size"] = $file_size;
						$changed_file_array["posix_permissions"] = $posix_permissions;
						$changed_file_array["posix_time"] = time();
						$changed_file_array["date_time"] = date("Y\-m\-d G:i:s");

						$found = false;
						$this_changed_file = $full_name . $file_type . $file_action; // . $uid . $gid;

						if (sizeof($output_buffer_array) > 0)
						{
							if (in_array($this_changed_file, $output_buffer_array))
							{
								$found = true;
								$output_buffer_array[$this_changed_file]["score"]++;  // The higher the score, the more this file has changed (yelling).
								$output_buffer_array[$this_changed_file]["tick_count"] = $tick;
								break;
							}
						}
						if (!$found)
						{
							$output_buffer_array[$this_changed_file]["score"] = 1;
							$output_buffer_array[$this_changed_file]["tick_count"] = $tick;
							$output_buffer_array[$this_changed_file]["file_info"] = $changed_file_array;
						}

					}//  End of foreach changed file.
				}

				//  Now process each of the files in the output_buffer_array to see if they should be reported as a legit changed file.
				if (sizeof($output_buffer_array) > 0)
				{
                                        //echo "BUFFER SIZE: " . sizeof($output_buffer_array) . "\n";
					$output_buffer_array_size = sizeof($output_buffer_array);
					$output_buffer_array_keys = array_keys($output_buffer_array);
					$outputed_files_array = null;  // Used to determine what files to remove from output_buffer_array after the file has been reported.
					//echo "Output buffer is: " . $output_buffer_array_size . "\n";
					for($i = 0; $i < $output_buffer_array_size; $i++)
					{	
						// Only output the changed file if the file has not changed since the last 2 loop checks.
						// 6/27/2010 Added functionality to output if the buffer is greater than  entries to get better program response.
						//echo "File tick count for check is " . $output_buffer_array[$i]["tick_count"] . "\n";
						if ($tick > ($output_buffer_array[$output_buffer_array_keys[$i]]["tick_count"]) || $output_buffer_array_size > 50)
						{

							if ($output_type == "stdout") 
							{
								echo $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"] . "|" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"] . "|TYPE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"] . "|ACTION:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"] . "|USER:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"] . "|GROUP:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"] . "|SIZE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"] . "\n";
								
							}
							else if ($output_type == "log")
							{
								fwrite($afrs_watcher_log_file_handle, $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"] . ", PATH:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"] . ", FILETYPE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"] . ", ACTION:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"] . ", USER:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"] . ", GROUP:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"] . ", SIZE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"] . "\n");
							}
							else if ($output_type == "afrs")
							{
								$full_name = addslashes($output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"]);
								$date_time = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"];
								$file_type = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"];
								$file_action = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"];
								$uid = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid"];
								$gid = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid"];
								$uid_name = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"];
								$gid_name = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"];
								$posix_permissions = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["posix_permissions"];
								$file_size = $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"];
								
								$afrs_message_file_list = $afrs_message_file_list . "		<file>
			<datetime>$date_time</datetime>
			<path>$full_name</path>
			<filetype>$file_type</filetype>
			<action>$file_action</action>
			<uid>$uid</uid>
			<gid>$gid</gid>
			<user>$uid_name</user>
			<group>$gid_name</group>
			<posixpermissions>$posix_permissions</posixpermissions>
			<size>$file_size</size>
		</file>\n";
								// Also write to the log file when -o afrs
								fwrite($afrs_watcher_log_file_handle, $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"] . ", PATH:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"] . ", FILETYPE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"] . ", ACTION:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"] . ", USER:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"] . ", GROUP:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"] . ", SIZE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"] . "\n");

							}
							else if ($output_to_file === true)  // Output type must be a file.
							{
								fwrite($output_file_pointer, $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"] . ", PATH:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"] . ", FILETYPE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"] . ", ACTION:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"] . ", USER:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"] . ", GROUP:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"] . ", SIZE:" . $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"] . "\n");
							}
							if (in_array("-c", $call_options))
							{
								$find_array = array("/%f/","/%e/", "/%t/", "/%s/", "/%u/", "/%g/", "/%T/");
								$replace_array = array($output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["full_name"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_action"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["date_time"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_size"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["uid_name"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["gid_name"], $output_buffer_array[$output_buffer_array_keys[$i]]["file_info"]["file_type"]);
								$curr_run_command = $run_command;  // Need to do this so we don't keep re-modifying the original command with the below preg_replace command.
								$curr_run_command = preg_replace($find_array, $replace_array, $curr_run_command);
								//echo "Command would be: " . $curr_run_command . "\n";
								exec($curr_run_command);
							}

							// Now that we have outputed any changed files.  Null that part of the array so we know what array elements we need to remove.
							unset($output_buffer_array[$output_buffer_array_keys[$i]]);
						}
					}
					
					// Remove the nulled/unset positions in the output_buffer array.  Cleanup the array.
					$output_buffer_array = array_merge($output_buffer_array, $temp_array = array());
					
				}
				//  Write to the daemon if the the wait timeout period has passed and another file has not changed yet.
				if ($output_type == "afrs" && $afrs_message_file_list != null && ($last_daemon_write + $output_wait_timeout < time()))
				{
					write_to_daemon();
					$afrs_message_file_list = null;  // Reset the afrs_message_file_list.
				}
				
				$tick++;  // Increment how many times we have looped.  This is used in the logic to determine if a file is yelling.
				usleep($frequency); // Sleep for an amount of time (in milliseconds) before next check.  We need this so we don't consume too much CPU resources.  
								// usleep() is better than slepp() so we can get get a better response time for 
								// fs changes from inotify.
			}
		}
		else
		{
			echo "FATAL ERROR:  Your system only supports watching " . $inotify_max . " directories.  Can't continue.\n";
			exit(1);
		}
	}
	else
	{
		echo "\nERROR:  The directory you specified does not exist.\n";
		print_usage();
	}
}
else
{
	print_usage();
}


// TODO:  Need an iterative way of getting all of the subdirectories instead of doing the search recursively.
// When called, recursively gets a list of all subdirectories.
function dir_search($path, &$dirs=array())
{
	$junk = array('.', '..');  // We don't care about these directories for obvious reasons.
	global $call_options, $exclude_list, $sym_link_count;
	
	//$path = ereg_replace("\/$", "", $path); // Check to see if they had a / at the end of the path listing.
	$scanned = array_diff(scandir($path), $junk);

	// Special check to see if we are to start at the root "/" directory.
	if ($path == "/")
	{
		$path = "";  // Get around problem of appending another "/" if the root directory "/" is supplied.  We don't want "//".
		echo "WARNING! You have chosen to watch the root path '/'.  This may take a long time to scan for directories.\n";
	}
	
	foreach($scanned as $test)
	{
		if (in_array("-h", $call_options) && preg_match("/^\..*/", $test) > 0)  // Skip if the file is hidden and we don't want to watch hidden files.
		{
			continue;  // skip current loop interation.  Need to fix.  Bad programming.
		}
		
		// This needs to be below the -h test so that we can test the dir name and not the full path.
		$test = $path."/".$test; // Must build the complete path or we will get incorrect testing on is_dir().
		
		if (in_array("-e", $call_options))  // Test each directory found against the exclude list and skip if necessary.
		{
			$skip_this = false;
			foreach($exclude_list as $exclude_this)
			{
				if (preg_match("/$exclude_this/", $test) > 0)
				{
					//echo "I'm suppose to skip this because I found: " . $exclude_this . "\n";
					$skip_this = true;  // Need to do this since we are in a nested foreach loop and we want to skip 
										// out of the parent foreach loop, not this one.
				}
			}
			if ($skip_this == true)  // Now we can skip out of the parent foreach loop if needed.
			{
				continue;
			}
		}

		// Make sure we don't watch the /dev fs or the /proc listings as they change all the time and could 
		// lead to weird results.  Maybe in the future we can handle these locations more intelligently and 
		// keep certain aspects of the OS in sync.  Maybe when this project get's ported to Java.
		if (preg_match("/^\/dev.*/", $test) == 0 && preg_match("/^\/proc.*/", $test) == 0)
		{
                    if (is_link($test))  
                    {
                        if (in_array("-l", $call_options))  //Skip symlinks if -l option was supplied.
                        {
                            continue;
                        }
                        
                        /* Check to see if the link target is a parent of our initial watch dir.   Don't create dir watch loops.
                         * For instance.  If we watch /home/user and a link in /home/user/link -> points to / then we would
                         * create a loop.  Don't do this ever.
                         */

                        else  
                        {
                            $real_path = realpath($test);  // Resolve the link's destination.
                            
                            $is_loop = false;
                            foreach($dirs as $this_dir)
                            {
                                if (strpos($this_dir, $real_path) === false)
                                {
                                    $is_loop = false;
                                }
                                else
                                {
                                    $is_loop = true;
                                    continue 2;
                                }
                            }
                            if ($is_loop === false)
                            {
                                $sym_link_count++;
                                
                            }
                        }
                    }
                    if (is_dir($test))  // is_dir now resolses sym and hard links.
                    {
                            if (is_readable($test))
                            {
                                $dirs[] = $test;  // Append the directory to our array of already found directories.
                                // Determine if we are to recursively search directories to watch.
                                if (in_array("-r", $call_options))
                                {
                                        dir_search($test, $dirs);  // Recursively call our directory function.
                                }					
                            }
                            else if (in_array("-v", $call_options))
                            {
                                    echo "ERROR: Cannot read " . $test . ".  It won't be watched.  Check permissions.";
                            }

                    }
                    /*else if (is_link($test) && !in_array("-l", $call_options))
                    {
                            //echo "Disovered a symlink: " . $test . "\n";
                            $sym_link_count++;
                            $link_target = readlink($test);
                            if (is_dir($link_target) && is_readable($link_target))
                            {
                                    $dirs[] = $link_target;
                                    if (in_array("-r", $call_options))
                                    {
                                            dir_search($link_target, $dirs);  // Recursively call our directory function.
                                    }
                            }
                    }*/
		}
	}
	return($dirs); // Return our result.
}

function write_to_daemon()
{
	global  $afrs_message_file_list,
		   $afrs_watcher_log_file_handle,
		   $last_daemon_write,
		   $daemon_socket_connector,
		   $call_options,
		   $sid,
		   $watch_id,
                   $key,
                   $public_ip,
                   $private_ip;
	
	$afrs_message_time = time();
	$transaction_id = hash('md5', microtime());  // Create a unique transaction id.
								$afrs_message_header = "<afrsmessage>
	<clientid>$sid</clientid>
	<transactionid>$transaction_id</transactionid>
        <key>$key</key>
	<sequencenumber>1</sequencenumber>
	<timestamp>$afrs_message_time</timestamp>
        <senderpublicip>$public_ip</senderpublicip>
        <senderprivateip>$private_ip</senderprivateip>
	<command>
		<cmdname>WATCHEVENT</cmdname>
		<arguments>
			<argument>
				<argname>WATCHID</argname>
				<argvalue>$watch_id</argvalue>
			</argument>
		</arguments>
	</command>
	<filelist> \n";

								$afrs_message_footer = "	</filelist>
	<reply>
		<tocommand></tocommand>
		<tosequencenumber></tosequencenumber>
		<errorcode></errorcode>
		<notes></notes>
	</reply>
</afrsmessage>";

	fwrite($daemon_socket_connector, $afrs_message_header . $afrs_message_file_list . $afrs_message_footer .  "\n\n");
	$last_daemon_write = time();  // Record this instance as the last time we wrote to the daemon.
}


function print_usage()  // If we were not called correctly then print program usage.
{
	echo "\nAdvanced File Replication System Watcher v 0.3.2 .  Copyright: Mike Lee 2008
This program watches for file changes and then either displays or writes them to a file or
submits the changes to the afrs daemon for recording/syncing/file replicating, etc.

Usage: afrs_watcher [options] [-E event(s)] (full directory path to watch)

	Events:	create, delete, modify, open, close, move, attrib, all
		None specified = default = create, delete, modify, move

	Options: (each must be separated with spaces i.e. -c -d , not -cd).
		-c	Execute command for every file change that happens. (enclosed in quotes).
		-D	Don't watch directories themselves for changes, but watch everything else.
		-d	Only watch directories, not the files that are contained in those directories.
		-E 	List of events to watch seperated by commas.  Sets to 'default' if none specified.
		-e	Exclude files & directories which match list of regular expressions (enclosed in double quotes and seperated by spaces).
		-f	Frequency in millisends you want to report file changes.  NOTE:  Actual file changes are recorded immidiately by kernel.
			WARNING!:  Setting a low frequency number will consume more cpu time.  Recommended not to go below 200000 on average CPUs.
					 Default is 500000 milliseconds (.5 seconds) if no frequency specified.
		-h	Don't watch hidden files and directories.
		-l	Don't follow symbolic links, therefore don't report what the links point to.  If the target is under the parent
			watch tree and -r is enabled, the targets of the link will be reported anyways.
		-o	Output type: [stdout|log|afrs|svn|quiet]
				 stdout:	Output to the screen. (No replication) 
				 log:           Output to specified file (No replication).
				 afrs:		This option can only be invoked by the daemon itself.  Output to AFRS daemon (logs to afrs database) 
				 		to replicate changed files to other locations running afrs daemon.
                                 svn:           Keep trac of actual files changes using svn.  Afrs-watcher will maintain its own svn database and 
                                                settings which are kept under \$AFRS_HOME/changes/svn .
				 quiet:		Don't output anything to the screen.  This output implies the -c option will be used.
		-i	IP address/host and port number to use to connect to the afrs daemon for reporting changes.  Must be used with the -o option.
			Example: -o afrs -i localhost:4746
		-j	The watch-id to use when reporting changes to the afrs daemon.  Must be used with the -o and -i options.
			Example:  -o afrs -i localhost:4746 -j 1011   The watch-id will be provided by the afrs daemon since this option should
					only be invoked by the daemon itself.  Therefore, the daemon will do a database lookup for the watch id that
					this watch instance is for.
		-p	(Not used yet)
		-r	Recursively watch all subdirectories and their files.
		-s	Scan files for viruses using ClamAV before reporting file changes. (NOT IMPLEMENTED YET)
		-u	Only report on events who's file(s) are owned by a certain user.
		-g	Only report on events who's file(s) are owned by a certain group.
		-v	Verbose mode.  Print everything that is happening.
		-z	Don't report on file changes with a zero file size.  This does NOT apply to files or dirs that have been deleted.

		You can also use these shortcuts when using the -c command and they will be replaced with their corresponding values:
			%f = Name of file that has triggered the event.
			%e = The actual event that occurred
			%t = Time the the event.
			%s = Size of the file (bytes).
			%u = User who owns the file.
			%g = Primary group on the file.
			%T = The type of file (i.e. file/directory).
		
		--help	Print usage info.\n";
	exit(1);
}

?>
