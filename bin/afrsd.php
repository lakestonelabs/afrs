#!/usr/bin/php
<?php



require_once(__DIR__."/../conf/afrs_config.php");
require_once(__DIR__."/../includes/functions.php");
require_once(__DIR__."/../includes/system/Daemon.php");
require_once(__DIR__."/../includes/network/StreamSocketServer.php");
require_once(__DIR__."/../includes/system/System.php");
require_once(__DIR__."/../includes/system/Dispatchers/DispatchFactory.php");
require_once(__DIR__."/../includes/system/Messages/Message.php");
require_once(__DIR__."/../includes/system/MessageQueue.php");
require_once(__DIR__."/../includes/system/Transaction.php");

//RedBean
require_once(__DIR__."/../includes/orm/rb.php");  // The ORM technology used by Afrs.

// These variables will get set in the below for loop that processes the results from the query of the registry table.
// We must initialize them here so that they will be in-scope (public) to the rest this file.
// IMPORTANT:  If the names in the registry table change, then they need to be changed here accordingly.
// IMPORTANT:  If the values in the registry table change, then the server must be restarted.  Will fix this in the future.
$daemon_port_number = null;
$watcher_port_number = null;
$sid = null;
$public_ip_address = null;
$ip_address = null;
$ip_address_has_changed = false;  // Used to issue an UPDATE message instead of a CHECKIN message if the local server's IP address has changed.
$session_expires = null;
$checkin_interval = null;
// End of variables that are dynamically set from the tbl_registry table.

$last_checkin = 0;
$field_name = null;
$afrs_watcher_pids = null;  // Will hold an array of process ids for each afrs-watcher.php process that is executed.

// Do our own handling of kill/term signals.  i.e. Cntrl-C.  This is used so we can terminate all of the afrs-watcher clients we will spawn.
//pcntl_signal(SIGTERM, "pctnl_signal_handler");  // Not tested yet.
//pcntl_signal(SIGHUP,  "pctnl_signal_handler");  // Not tested yet.
pcntl_signal(SIGINT, "pctnl_signal_handler");

//$db_conn = new DbConnectionWrapper($dbtype, $dbhost, $dbname, $dbuser, $dbpassword);
//$query = new QueryWrapper($db_conn);

//RedBean
R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);

// Get all of the registry settings from the db.
$registry_beans = R::findAll("registry");

$system_object = new System(); // Used to get various info about the system including network device info.

// Check to see if the stored db ip address is the same as the one currently configured on the machine.  If it's not, prompt
// to use the new IP address and broadcast the ip address updated to the sync partners. We will update our sync partners with the
// new IP info when we perform our initial "checkin" function later in this file.

$registered_inet_device_beans = R::find("inetdevices", " status = 1 and link_state = 1");

if (sizeof($registered_inet_device_beans) == 0)
{
    /* Must be the first time we have been ran since we don't have any inet devices to use.  Without inet devices, we 
     * useless.  We must do some boot-strapping here to find a useable device in order to continue.
     * 
     * The below code was taken from the inet_setup function in afrs-config.php
     */
    
    echo "  \nNo network devices have been configured/enabled for AFRS.  Searching for new network devices... \n";
    
    $inet_device_objects = $system_object->getInetDevices();
    $inet_device_count = sizeof($inet_device_objects);
    
    if ($inet_device_count > 0)  // Make sure we have devices to work with.
    {
        $i = 0;
        foreach($inet_device_objects as $key => $this_inet_device_object)
        {
            $usable = null;
            $link_detected_desc = null;

            if ($this_inet_device_object->getLinkDetected())
            {
                $link_detected_desc = "link detected";
            }
            else
            {
                $link_detected_desc = "no link detected";
            }
            if ($this_inet_device_object->getState() == "up" || $this_inet_device_object->getLinkDetected())
            {
                echo "\n    " . ($i + 1) . ".)" . $usable 
                        . "\n        Dev:       " 
                        . $this_inet_device_object->getDeviceName() 
                        . "\n        Mac:       " 
                        . $this_inet_device_object->getMac() 
                        .  "\n        Addr:      " 
                        . $this_inet_device_object->getIpAddress() 
                        . "\n        Public Ip: " 
                        . $this_inet_device_object->getPublicIpAddress() 
                        . "\n        Gateway:   "
                        . $this_inet_device_object->getGateway() 
                        . "\n        Subnet:    " 
                        . $this_inet_device_object->getSubnetMask() 
                        . "\n        Bcast:     " 
                        . $this_inet_device_object->getBroadcast() 
                        . "\n        Speed:     " 
                        . $this_inet_device_object->getSpeed() 
                        . " Mbps" . "\n        Dns:       " 
                        . $this_inet_device_object->getNameserver() 
                        . "\n" . "        Status:    " 
                        . $this_inet_device_object->getState() 
                        .  "\n" . "        Link status:   " . $link_detected_desc . "\n";
                $i++;
            }
            else
            {
                unset($inet_device_objects[$i]);
                $inet_device_objects = array_merge($inet_device_objects, array());
            }
        }

        echo "\n  --> Please choose the device number to configure for AFRS: ";
        $dev_choice = trim(fgets(STDIN));
        echo "  --> What is the Fully Qualified Domain Name for this address?: ";
        $fqdn_input = trim(fgets(STDIN));

        $dns_query_output_array = dns_get_record("$fqdn_input", DNS_A);  // We only care about the "A" record dns answer.

        if (!$dns_query_output_array)
        {
            // No DNS entry for the name.  Using it's IP address for the fqdn.
            echo "  --> Could not validate the name you entered.  Do you want use the IP as the fqdn? (y/n): ";
            $dns_error_response = strim(fgets(STDIN));
            if ($dns_error_response == "y" or $dns_error_response == "Y")
            {
                $fqdn = $this_inet_device_object->getIpAddress();
            }
            else
            {
                echo "  --> Can't go any further then.  Please fix and try again.\n";
            }
        }
        else
        {
            // Got a valid dns A record response for this name.
            if ($dns_query_output_array[0]["ip"] == $inet_device_objects[($dev_choice - 1)]->getIpAddress())
            {
                //echo "DNS query succeeded\n";
                $fqdn = $fqdn_input;
            }
            else
            {
                echo "ERROR:  DNS answer for " . $fqdn_input . " has a different IP address than the one used for interface " . $inet_device_objects[($dev_choice - 1)]->getDeviceName() . "\n";
                exit(1);
            }
        }

        // Now insert the data into the afrsd database.
        $new_inet_device_bean = R::dispense("inetdevices");
        $new_inet_device_bean->device_name = $inet_device_objects[($dev_choice - 1)]->getDeviceName();
        $new_inet_device_bean->mac = $inet_device_objects[($dev_choice - 1)]->getMac();
        $new_inet_device_bean->ip = $inet_device_objects[($dev_choice - 1)]->getIpAddress();
        $new_inet_device_bean->public_ip = $inet_device_objects[($dev_choice - 1)]->getPublicIpAddress();
        $new_inet_device_bean->broadcast = $inet_device_objects[($dev_choice - 1)]->getBroadcast();
        $new_inet_device_bean->subnet_mask = $inet_device_objects[($dev_choice - 1)]->getSubnetMask();
        $new_inet_device_bean->gateway = $inet_device_objects[($dev_choice - 1)]->getGateway();
        $new_inet_device_bean->dns1 = $inet_device_objects[($dev_choice - 1)]->getNameServer();
        $new_inet_device_bean->fqdn = $fqdn;
        $new_inet_device_bean->speed = $inet_device_objects[($dev_choice - 1)]->getSpeed();
        $new_inet_device_bean->link_state = $inet_device_objects[($dev_choice - 1)]->getLinkDetected();
        $new_inet_device_bean->enabled = 1;
        $new_inet_device_bean->date_added = R::$f->now();
        $new_inet_device_bean->date_modified = R::$f->now();

        if ($inet_device_objects[($dev_choice - 1)]->getState() == "up")
        {
            $status = 1;
        }
        else if ($inet_device_objects[($dev_choice - 1)]->getState() == "down")
        {
            $status = 0;
        }

        $new_inet_device_bean->status = $status;

        if (R::store($new_inet_device_bean) > 0)
        {
            echo "Successfully added device to be used for AFRS Daemon.\n";
        }
        else
        {
            echo "ERROR:  Failed to add interface to database.  Please see previous errors and correct.\n";
            exit(1);
        }
    }
}

$enabled_inet_device_beans = R::find("inetdevices", " status = 1 and link_state = 1 and enabled = 1 ");
if (sizeof($enabled_inet_device_beans) == 0)
{
    echo "  \n*** No network devices have been enabled for AFRS.  Please enable one now. ***\n\n";
    $inet_device_number_index = menu("display", $registered_inet_device_beans, "hide", array("id"));
    
    echo "\nEnter network device to enable for AFRS:";
    $device_number_selection = trim(fgets(STDIN));
    $inet_device_bean = $registered_inet_device_beans[$inet_device_number_index[$device_number_selection]];
    $inet_device_bean->enabled = 1;
    R::store($inet_device_bean);
    
    echo "\n\n*** Now using " . $inet_device_bean->device_name . " for AFRS ***\n\n";
}


// Re-query for inet devices in the event this was the first time we were ran and the system added a new inet device.
$registered_inet_device_beans = R::find("inetdevices", " status = 1 and link_state = 1 and enabled = 1 ");
$registered_inet_device_bean = array_pop($registered_inet_device_beans);
$device_name = trim($registered_inet_device_bean->device_name);
$fqdn = trim($registered_inet_device_bean->fqdn);

$current_system_inet_device = $system_object->getInetDevice($device_name);  // TODO:  Need to change this for mutli net device systems.
$ip_address = $current_system_inet_device->getIpAddress();  // TODO: Need to support multiple interfaces.  Hard-coding for now.
$public_ip_address = $current_system_inet_device->getPublicIpAddress();

$registered_inet_device_bean->public_ip = $public_ip_address;
R::store($registered_inet_device_bean); // Update the public IP for this inet devices.


// Now we take the variables from the registry table and write them to a file.  This file will be included in 
// other scripts/classes that can then use the variables without having to requery the database each time 
// the script is run or a new object from a class is created.  Also helps on performance and database deadlocking.
//
// TODO:  Need to set strict permissions on the afrs_vars.php file since it contains sensitive info. ie, passwords etc...
// TODO:  Need a system user call afrs and only have the afrs user have access to this file.  chmod 600.
// TODO:  IMPORTANT!!!  Writing these to a file should be re-thought.  Getting them from the DB each time is better
//		for security considerations.
if ($vars_file_handle = fopen("../conf/afrs_vars.php", "w"))  // Using "w" for the permission to truncate the file to 0 bytes and start with a clean slate.
{														   // If the file does not exists, it will create it on the fly.
	// Now we dynamically create the variables from the registry table and set their values.
	fwrite($vars_file_handle, "<?php\n//This file is dynamically created by afrsd.  DO NOT MANUALLY EDIT for you will lose all of your changes.\n");
	/*foreach ($query_results as $this_query_result)
	{
		$field_name = $this_query_result["name"];
		$$field_name = $this_query_result["value"];
		fwrite($vars_file_handle, "$" . $field_name . " = \"" . $$field_name . "\";\n");
	}*/
        foreach($registry_beans as $this_registry_bean)
        {
            $field_name = $this_registry_bean->name;
            $$field_name = $this_registry_bean->value;
            fwrite($vars_file_handle, "$" . $field_name . " = \"" . $$field_name . "\";\n");
        }
        fwrite($vars_file_handle, "$" . "fqdn" . " = \"" . $fqdn . "\";\n");
        fwrite($vars_file_handle, "$" . "private_ip" . " = \"" . $ip_address . "\";\n");
        fwrite($vars_file_handle, "$" . "public_ip" . " = \"" . $public_ip_address . "\";\n");
	fwrite($vars_file_handle, "?>\n");
	fclose($vars_file_handle);
}
else
{
	echo "FATAL ERROR:  frsd.php - Could not open the afrs_vars file for writing.  Aborting now!\n";
	exit(1);
}


// Initialize the variables that will be used by the server and other classes/scripts in afrsd.
//init_server_variables();
//exit(0);

// Go into daemon mode.  Don't create a new object since makeDaemon is a static method, just run the function to fork the process.
//echo "Starting daemon... ";
//Daemon::start();  // Don't use this since it screws with mysql db connections going away.  TODO: Need to fix this.
                    // See Mysql's help page http://dev.mysql.com/doc/refman/5.1/en/gone-away.html and read the section
                    // talking about forking child processes.

echo "\n";

// First before we do anything, we need to know that all prerequisites are ok.
$myconnmanager = null;

//$systemOb->checkNetwork();
//$systemOb->checkHardDrives();

// TODO: Need to move this into the afrs_vars file generation in case the ip changes then the file can be updated also.
if (sizeof($registered_inet_device_beans) == 1)  // We are only supporting one interface at this time.
{
    if ($current_system_inet_device != false)  // Only continue if this device exists on the system.
    {
        $current_ip_address = $current_system_inet_device["ip_address"];  // Get the ip address on currently being used on the system.
        if (strlen($current_ip_address != 0))  // We have some IP address data to work with.
        {
            // Now compare the IP on the system to the IP we have stored in the db for this device.
            if ($current_ip_address != trim($registered_inet_device_bean->ip)) // The IP address of the local machine has changed.
            {
                    $ip_address_has_changed = true;
                    // Get the new ip address of the machine.
                    echo "WARNING: The ip address of this afrs server has changed since afrsd was last ran.\n   --> Would you like to run afrsd with the new ip address (" . $current_ip_address . ") ?: (y/n)";
                    $choice = trim(fgets(STDIN));

                    if ($choice == "y" or $choice == "Y")
                    {
                        echo "   --> Setting the afrs daemon to run with an ip address of: " . $current_ip_address . "\n";
                        echo "   --> Updating the afrs database to reflect the changes";
                        $registered_inet_device_bean->ip = $current_ip_address;
                        if (R::store($registered_inet_device_bean) !== false)
                        {
                            echo "Done\n";
                            $ip_address = $current_ip_address;
                        }
                        else
                        {
                            echo "\n   -->FATAL ERROR:  Could not update database with new ip address for local afrs daemon.  Aborting!\n";
                            exit(1);
                        }
                    }
                    else if ($choice == "n" or $choice == "N")
                    {
                            echo "   --> Aborting due to no usable ip address.\n";
                            exit(1);
                    }
                    else
                    {
                            echo "   -->  FATAL ERROR:  Enter 'y' or 'n' for your choice.  Aborting!\n";
                            exit(1);
                    }
            }
        }
        else
        {
            echo "afrsd::FATAL ERROR: Interface " . $device_name . " is not configured on this system.  Please fix and try again later.";
            exit(1);
        }
    }
    else
    {
        echo "afrsd::FATAL ERROR:  Network device " . $device_name . " no longer exists on this system.  Please fix and try again.\n";
        exit(1);
    }
}

//$address = trim(shell_exec("ifconfig | grep -m 1 'inet addr' | cut -d ':' -f 2 | cut -d ' ' -f 1"));  // Need a cleaner solution for finding the ip addresss.

// TODO:  Get the max number of connections (StreamSocketServer's 3rd parameter) from the database registry table.
$client_server = new StreamSocketServer($ip_address, $daemon_port_number, 50);
$local_server = new StreamSocketServer("localhost", $watcher_port_number, 20);  // Used to accept watch data from the afrs-watcher and configuration agents.
//$dispatcher = new DispatchWrapper();
$messages_queue = new MessageQueue();
$transaction_array = null;
$ended_transaction_locations = null;  // This is an array that holds the transaction_array locations of transactions to destroy.

echo "Server started...\n";

//  Now that the StreamSocketServers are running, lets spawn the afrs-watcher clients to start watching for file changes.
//  NOTE:  The StreamSocketServers must be created and started first so that the afrs-watcher clients have some to connect to.
//$query->runQuery("select * from tbl_watches where active = 1");
//$query_results = $query->getResultsAssoc();
$active_watch_beans = R::find("watches", " active = 1 ");

if (sizeof($active_watch_beans) > 0)
{
	echo "Spawning " . sizeof($active_watch_beans) . " afrs-watcher client(s) (this may take some time)...\n";

	$loop_count = 0;
	foreach($active_watch_beans as $this_active_watch_bean)
	{
		$afrs_watcher_options_string = "-o afrs -i localhost:4746 -v ";

		$watch_id = $this_active_watch_bean->id;
		$watch_path = $this_active_watch_bean->watch_path;
		$recursive = $this_active_watch_bean->recursive;
		$watch_hidden_directories = $this_active_watch_bean->watch_hidden_directories;
		$watch_hidden_files = $this_active_watch_bean->watch_hidden_files;
		$exclusions_patterns = $this_active_watch_bean->exclusions_patterns;
		$follow_symbolic_links = $this_active_watch_bean->follow_symbolic_links;
		$filter_by_group_owner = $this_active_watch_bean->filter_by_group_owner;
		$filter_by_user_owner = $this_active_watch_bean->filter_by_user_owner;
		$verbose = $this_active_watch_bean->verbose;
		//$allow_yelling = $this_query_result->allow_yelling;
		$ignore_zero_files = $this_active_watch_bean->ignore_zero_files;
		$date_added = $this_active_watch_bean->date_added;
		$sync = $this_active_watch_bean->sync;
		$wait_amount = $this_active_watch_bean->wait_amount;

		$afrs_watcher_options_string = $afrs_watcher_options_string . "-j " . $watch_id . " ";

		if ($recursive == 1)
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-r ";
		}
		if ($watch_hidden_directories == 0)
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-h ";
		}
		if ($ignore_zero_files == 1)
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-z ";
		}
		if ($filter_by_user_owner != null && $filter_by_user_owner != "NULL")
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-u ". trim($filter_by_user_owner) . " ";
		}
		if ($filter_by_group_owner != null && $filter_by_user_owner != "NULL")
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-g ". trim($filter_by_group_owner) . " ";
		}
		if ($verbose == 1)
		{
			$afrs_watcher_options_string = $afrs_watcher_options_string . "-v ";
		}
		
		$afrs_watcher_options_string = $afrs_watcher_options_string . (trim($watch_path));  // Lastly, add the watched direcotry path.

		//  NOTE:  Need to use the ">" in conjunction with "&" in order to successfully send the command to the backgroup.
		//         if we don't then PHP will wait until afrs-watcher is done in order to continue withthe rest of afrsd.
		//  Also, the 2>&1 & echo $! jargon makes it so we can get the PID of afrs-watcher process that we executed.
		
                exec(__DIR__."/afrs-watcher.php " . $afrs_watcher_options_string . " > /dev/null 2>&1 & echo $!", $afrs_watcher_pids);  // Execute the afrs-watcher program with specified options.
		echo "   --> Successfully spawned watch id: " . $watch_id . " for path: " . $watch_path . " with process id: " . $afrs_watcher_pids[$loop_count] . "\n";
		$loop_count++;
	}
	echo "Done spawning afrs-watcher clients.\nListening for client connections...\n\n";

}


$server_loop_count = 0;
while(true)  // Start the server to accept connections.
{
        $server_loop_count++;
	pcntl_signal_dispatch();  // Dispatch/process any signals that were received.  i.e. Ctrl-C.
        
        // Keep checking the state of our configured network devices.  If they go down, then start tearing everything down.
        if ($server_loop_count == 21)  // Only run the check when the server while(true) has looped 20 times. 
        {
            if ($system_object->requeryInetDevices(true))
            {
                $current_inet_object = $system_object->getInetDevice($registered_inet_device_bean->device_name);
                if ($current_inet_object->getState() != "up")
                {
                    // Our inet device we use to function went down.  We can't function withouth this.
                    echo "*** CRITICAL!  Inet device " . $registered_inet_device_bean->device_name . " went down.  Server operations paused...\n";
                    
                    // Don't leave this section of code until device comes backup up since it's pointless.
                    $retrys = 6;  // How many times to check if the network came back up.
                    $retry_count = 1;
                    while($current_inet_object->getState() != "up")
                    {
                        $system_object->requeryInetDevices(true);
                        $current_inet_object = $system_object->getInetDevice($registered_inet_device_bean->device_name);
                        echo "*** CRITICAL! " . $registered_inet_device_bean->device_name . " is still down!...  Retry " . $retry_count . " of " . $retrys . " .\n";
                        sleep(10); // Sleep for 10 seconds between check intervals.
                        
                        // Tried everything.  Shutdown everything!
                        if ($retry_count == $retrys)
                        {
                            echo "\n*** System network state has permanetly failed!  Shuting down...\n";
                            shutdown();
                        }
                        $retry_count++;
                    }
                    
                    echo "*** INFO: " . $registered_inet_device_bean->device_name . " came backup up.  Resuming server operations...\n";
                }
                else if ($current_inet_object->getState() == "up")
                {
                    // Update various inet device stats such as speed, etc.
                    $registered_inet_device_bean->speed = $current_inet_object->getSpeed();
                    $registered_inet_device_bean->duplex = $current_inet_object->getDuplex();
                    R::store($registered_inet_device_bean);
                }
            }
            $server_loop_count = 0;  // Reset our check counter. 
        }

	// Determine first if we need to checkin with our sync partners.
	if (($last_checkin + $checkin_interval) <= time())
	{
		echo "Initiating checkin with remote sync partners...\n";
		// Before we can checkin we need to get a list of our partners/peers from the db.
		$active_sync_partner_beans = R::find("syncpartners", " enabled = 1 ");
                
                if (sizeof($active_sync_partner_beans) > 0)
                {
                    foreach ($active_sync_partner_beans as $this_active_sync_partner_bean)
                    {
                            $partner_public_ip_address = $this_active_sync_partner_bean->public_ip_address;
                            $partner_ip_address = $this_active_sync_partner_bean->ip_address;
                            $partner_port_number = $this_active_sync_partner_bean->port_number;
                            $partner_id = $this_active_sync_partner_bean->clientid;

                            $message = new Message($partner_ip_address.":".$partner_port_number);

                            if($message->setCommand("CHECKIN"))
                            {
                                    $message->setArgumentValue("REMOTECLIENTID", $partner_id);
                                    $messages_queue->push($message);
                            }
                            else
                            {
                                    echo "ERROR:  afrsd::main() - Cannot checkin with sync partners.  Error setting CHECKING command for message.\n";
                            }
                    }
                    
                }
                $last_checkin = time();  // Last checkin is now (in seconds).
	}
	
	if ($client_server->listen())  // First check to see if any clients are connected and process them.
	{
		$clients_messages = $client_server->getClientData();  // Get an array of sockets and raw data from the clients.
		if (sizeof($clients_messages) > 0)
		{
			foreach($clients_messages as $this_client_message)
			{
				$new_client_message_object = new Message($this_client_message[0], $this_client_message[1]);  // Create a new message from the client's socket and raw data.
				$messages_queue->push($new_client_message_object);  // Add each client message to the message queue for processing.
			}
		}
	}
	
	if ($local_server->listen())  // Second check to see if we need to process any watch events.
	{
		$local_messages = $local_server->getClientData();  // Get client data for a complete conversation.
		if (sizeof($local_messages) > 0)
		{
			foreach($local_messages as $this_local_message)
			{
				$new_local_message_object = new Message($this_local_message[0], $this_local_message[1]);
				$messages_queue->push($new_local_message_object);  // Add each client message to the message queue for processing.
			}
		}
	}
	
	/*
	 * Get each message received and place it into its corresponding Transaction
	 */ 
	$queue_size = $messages_queue->getSize();  // Set size and then loop through.  Don't use getSize() in the for loop since the queue size always changes and can have adverse affects.
	for ($i = 0; $i < $queue_size; $i++)
	{
		$transaction_exists = false;
		$transaction_pointer = null; // This stores the index location of the found transaction found in the transaction array.
		//$transaction_owner = null;
		$dispatch_response = null;
		$ended_transaction_locations = null;
		$this_message_object = $messages_queue->pop();  // Get the nex message at the top of the queue.
		
		// TODO:  Need to add support for ACLs.
		$transaction_array_size = sizeof($transaction_array);
		if ($transaction_array_size > 0)
		{
			// Reset the locations of ended transaction to remove.
			$ended_transaction_locations = null;

			echo "Search for messaged transaction ID of: " . $this_message_object->getTransactionID() . "\n";
			$transaction_location = null;
			for($j = 0; $j < sizeof($transaction_array); $j++)
			{
				if ($this_message_object->getTransactionID() == $transaction_array[$j]->getTransactionID())
				{
					$transaction_location = $j;
					break;
				}
			}
			if ($transaction_location >= 0)  // We found the transaction.
			{
				$transaction_exists = true;
				// Determine if we received any ACKs to our BYE messages to end a transaction.
				// The transaction was ended by us by sending a BYE to the remote client and they ACKed it.
				if ($this_message_object->getCommand() == "ACK" && $transaction_array[$transaction_location]->getNewestMessageCommand() == "BYE" && $this_message_object->getReplyToCommand() == "BYE")
				{
					//echo "!!!! Found a transaction to kill.\n";
					$ended_transaction_locations[] = $transaction_location;  // Store the transaction_array locations of the transaction to destroy.
				}
				else
				{
					$add_message_response = $transaction_array[$transaction_location]->addMessage($this_message_object); // Append message to it's transaction.
					if (is_object($add_message_response))
					{
						// An error occurred.  Send the error message back to the client.
						echo "ERROR: afrsd.php: Transaction error.  Could not add message to it's transaction.\n";

						// TODO:  Need to do some checks (possibly by IP address/localhost) to determine.
						//		  if the error was to the localhost or remote client and send the message
						// 		  appropriately.  I.E. we will not respond to the $local_server with errors.
						$client_server->sendToClient($add_message_response->getSocket(), $add_message_response->getRawMessage());  // Send the message to the client.
					}
					else if ($add_message_response === true) // The message was successfully added to the transaction and ready to dispatch.
					{
						echo "Message with command of: " . $this_message_object->getCommand() . " and sequence # of: " . $this_message_object->getSequenceNumber() . " successfully added to its corresponding Transaction.\n";
					}
				}
			}
		}
		if (!$transaction_exists)  // Transaction not found.  Create a new one and proceed.
		{
			if ($this_message_object->getTransactionID() != null)
			{
				echo "afrsd - Creating new transaction for id: " . $this_message_object->getTransactionID() . "\n";
			}
			$new_proxy_transaction = null;	// In case we need to proxy transactions.
			$new_transaction = new Transaction($this_message_object);  // Create a new transaction.
			
			if ($new_transaction->isProxiable())
			{
                                //  Link the two transactions.
				$new_proxy_transaction = new Transaction($this_message_object, "proxy");
				if (is_object($new_proxy_transaction) && $new_proxy_transaction->isProxied())
				{
					$new_transaction->linkTransaction($new_proxy_transaction, "child");
					$new_proxy_transaction->linkTransaction($new_transaction, "parent");
				}
			}

			$transaction_array[] = $new_transaction;  // Append the new transaction for book keeping.
			if ($new_proxy_transaction != null)  // Append the proxy transaction if it exists.
			{
				$transaction_array[] = $new_proxy_transaction;
			}
		}
		/*
		 * End of adding received messages to their corresponding Transaction objects.
		 */
		
		// Before we dispatch each of the transactions, determine if we have any transactions that have ended and 
		// destroy them.
		if (sizeof($ended_transaction_locations) > 0)
		{
			foreach ($ended_transaction_locations as $this_transaction_location)
			{
				// Tear-down the transaction and remove it from the transaction array.
				$transaction_array[$this_transaction_location]->__destruct();
				unset($transaction_array[$this_transaction_location]);
				$temp_array = array();
				$transaction_array = array_merge($temp_array, $transaction_array);
			}
		}
		$ended_transaction_locations = null;  // Reset so we can process transactions that have ended after we dispatch them below.
	}  // End of for loop to add messages to their corresponding transactions.
	
	/*
	 * Now we need to go through each transaction and dispatch it.
	 * NOTE:  Oldest transaction is at the top of the array (Queue) FIFO.
	 */
	
	// Process each transaction.
	for ($i = 0; $i < sizeof($transaction_array); $i++)  // Look at each transaction in our list.
	{ 
            // Only process transactions that have new messages or transaction that don't have new messages but have pending (QUEUED) messages.
            if ($transaction_array[$i]->hasNewMessages() || (!$transaction_array[$i]->hasNewMessages() && $transaction_array[$i]->hasPendingMessages()))
            {
                    $dispatch_response = DispatchWrapper::dispatch($transaction_array[$i]);
                    if ($dispatch_response === true)  // The response is for a watch event.
                    {
                            echo "Watched event recorded\n";
                            $ended_transaction_locations[] = $i;  // Watch event transactions don't need a full dialogue.  Therefore, after they are recorded, destroy their transaction.
                    }
                    else if ($dispatch_response === false)
                    {
                            echo "Error while tring to record watched event.\n";
                            $ended_transaction_locations[] = $i;
                    }
                    else if (is_object($dispatch_response))  // The response is a message object and is for a remote client or local cofig client program (usually).
                    {
                            //echo "Client IP is: " . $dispatch_response->getClientIP() . "\n";
                            //echo "---Dispatch response command is " . $dispatch_response->getCommand() . "\n";
                            //echo "---GetPreviousMessageCommand is " . $transaction_array[$i]->getPreviousMessageCommand() . "\n";
                            //echo "---GetReplyToCommand is " . $dispatch_response->getReplyToCommand() . "\n";
                            //var_dump($dispatch_response);
                            if ($dispatch_response->getCommand() == "ACK" && $transaction_array[$i]->getPreviousMessageCommand() == "BYE" && $dispatch_response->getReplyToCommand() == "BYE")
                            {
                                    $ended_transaction_locations[] = $i;  // Store the transaction_array locations of the transaction to destroy.
                            }

                            if (false/*$dispatch_response->getClientIP() == "10.0.2.16"*/)
                            {
                                    echo "Response to local\n";
                            }
                            else
                            {

                                    if ($client_server->sendToClient($dispatch_response->getSocket(), $dispatch_response->getRawMessage()))  // Send the message to the client.
                                    {
                                            echo "Successfully sent message response to remote end.\n";
                                    }
                                    else
                                    {
                                            echo "Error communicating with remote end.  Marking transaction " . $transaction_array[$i]->getTransactionID() . " to be killed.\n";
                                            // We can mark this transaction to be kill and not send a response to the remote end since we cannot
                                            // communicate with the remote end.  No point in send am message to a remote end that we can talk to.
                                            $ended_transaction_locations[] = $i;

                                            // Also, we need to check to see if any ended transaction have link transactions and send an error message back to them..
                                            if ($transaction_array[$i]->isLinked() && $transaction_array[$i]->getLinkedRelationship() == "child")
                                            {
                                                    $parent_transaction_location = array_search($transaction_array[$i]->getLinkedTransactionID(), $transaction_array);
                                                    $parent_transaction = $transaction_array[$parent_transaction_location];

                                                    // Make sure the parent transaction was pending on a response from the child transaction that was just marked to be killed.
                                                    if ($parent_transaction->getLinkedTransactionID() == $transaction_array[$i]->getTransactionID())
                                                    {
                                                            // Since we are the child we need to send a message back to our parent to end the conversation.
                                                            // TODO:  Need to determine if we need to respond to $client_server or $local_server and act appropriatly.
                                                            $client_ip_address_and_port = stream_socket_get_name($parent_transaction->getMessageAtLocation(0)->getSocket(), true);
                                                            $client_ip_address = rtrim($client_ip_address_and_port, strrchr($client_ip_address_and_port, ":"));

                                                            $error_message = new Message($parent_transaction->getMessageAtLocation(0)->getSocket());  // Create a new message to send back to parent.
                                                            $error_message->setCommand("ERROR");
                                                            $error_message->setReplyNotes("Error communicating with remote end.");
                                                            $error_message = $parent_transaction->addMessage($error_message);

                                                            // The parent transaction is no longer waiting on it's to-be-killed child (proxied) transaction.
                                                            $parent_transaction->unLink();  // Unlink the parent transaction from it's child

                                                            // TODO:  We should use the php filter_var() function to validate that it's an ip_address.
                                                            // Determine which one of our server streams we need to use to respond with.
                                                            if ($client_ip_address == "::1" || $client_ip_address == "localhost" || $client_ip_address == "127.0.0.1")
                                                            {
                                                                    // Return error message to locally connected clients.
                                                                    $local_server->sendToClient($parent_transaction->getMessageAtLocation(0)->getSocket(), $error_message->getRawMessage());
                                                            }
                                                            else
                                                            {
                                                                    // Return error message to remotely connected clients.
                                                                    $client_server->sendToClient($parent_transaction->getMessageAtLocation(0)->getSocket(), $error_message->getRawMessage());
                                                            }
                                                    }
                                            }

                                            // A checkin attempt with the remote end failed.  Update the db record to reflect the failed attempt.
                                            if ($dispatch_response->getCommand() == "CHECKIN")
                                            {  
                                                $sync_partner_client_id = $dispatch_response->getArgumentValue("REMOTECLIENTID");
                                                $sync_partner_bean = R::findOne("syncpartners", " clientid = ? ", array($sync_partner_client_id));
                                                $sync_partner_bean->failed_checkin_count++;
                                                $sync_partner_bean->status = 0;  // Mark the remote sync partner as being offline.
                                                R::store($sync_partner_bean);
                                            }
                                    }
                            }
                    }
            }
	}
	
	// Now destroy any transactions on the local side that have ended.
	// Yes!, this is ran before and after the dispatching of the transactions. This side of it is to remove 
	// the transactions when we have sent the remove client side an ACK to a received BYE command.
	// YES YOU NEED THIS HERE.  JUST LEAVE IT.
	if (sizeof($ended_transaction_locations) > 0)
	{
		foreach ($ended_transaction_locations as $this_transaction_location)
		{
			// Tear-down the transaction and remove it from the transaction array.
			echo "Performing late killing of transaction\n";
			$transaction_array[$this_transaction_location]->__destruct();
			unset($transaction_array[$this_transaction_location]);
			$temp_array = array();
			$transaction_array = array_merge($temp_array, $transaction_array);
		}
	}
	$ended_transaction_locations = null;  // Reset so we can process transactions that have ended after we dispatch them below
	
	// Second check to see if we (now the client) need to initiate any new connections to other endpoints.
}

function pctnl_signal_handler($signal)
{
    if ($signal == SIGINT)
    {
        shutdown();
    }
}

function shutdown()
{
    global $afrs_watcher_pids,
		  $client_server,
		  $local_server;
    
    echo "\n\n*** Killing spawned afrs-watcher processes... ";
    foreach($afrs_watcher_pids as $this_afrs_watcher_pid)
    {
            if(!posix_kill($this_afrs_watcher_pid, SIGKILL))
            {
                   echo "          Error killing PID: " . $this_afrs_watcher_pid . "\n";
            }
    }
    echo " Done.\n";
    echo "Stopping client server...";
    $client_server->stop();
    echo "Done\n";
    echo "Stopping local server...";
    $local_server->stop();
    echo "Done\n";

    // Now kill ourself.
    posix_kill(posix_getpid(), SIGKILL);
}
?>