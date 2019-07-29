#!/usr/bin/php 
<?php

require_once(__DIR__."/../conf/afrs_config.php");
require_once(__DIR__."/../conf/afrs_vars.php");
require_once(__DIR__."/../includes/functions.php");
//require_once("includes/system/System.php");
require_once(__DIR__."/../includes/network/AfrsClient.php");

//RedBean
require_once(__DIR__."/../includes/orm/rb.php");  // The ORM technology used by Afrs.


$afrs_client_obj = new AfrsClient("localhost", 4746, 10);  // Use this to send configuration info to local server.
//$dbconn = new DbConnectionWrapper($dbtype, $dbhost, $dbname, $dbuser, $dbpassword);
//$query = new QueryWrapper($dbconn);
$choice_array = null;

//RedBean
R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);

passthru("clear");

echo "Running pre-checks...\n";

$device_count = R::count("devices");

if ($device_count == 0)
{
	echo "  WARNING:  You don't have any storage devices configured.\n";
	echo "  Would you like to setup disks for AFRS now? (y/n): ";
	$input = trim(fgets(STDIN));
	
	if ($input == "y")
	{
		harddrive_setup();
	}
}

$inet_device_count = R::count("inetdevices");
if ($inet_device_count == 0)
{
	echo "  WARNING:  You don't have any network interfaces configured for AFRS.\n";
	echo "  Would you like to setup your network interfaces for AFRS now?: ";
	$input = trim(fgets(STDIN));
	
	if ($input == "y")
	{
		inet_setup();
	}
}

begin();

function begin()
{
	echo "\nAFRS Configurator:

	1.)	Network devices
	2.)	Sync partners
	3.)	Watched locations
        4.)     Shares
        5.)     Sync shares

	Selection: ";

	$input = trim(fgets(STDIN));

	if ($input == 1)
	{
		inet_setup();
                begin();
	}
	else if ($input == 2)
	{
		syncpartners_setup();
                begin();
	}
	else if ($input == 3)
	{
		watchedlocations_setup();
                begin();
	}
        else if ($input == 4)
        {
            shares_setup();
            begin();
        }
        else if ($input == 5)
        {
            synced_shares_setup();
            begin();
        }
}

function harddrive_setup()
{
	//global $choice_array, $query;
	echo "  Setting up hard drives for afrs...\n";
	$scsi_count = exec("cat /proc/scsi/scsi | grep -c Host:");
	//$ata_count = exec("cat /proc/hdd/hdd | grep -c Host:");
	//echo "    Found " . $scsi_count . " SCSI devices and " . $ata_count . " ATA/SATA devices.\n";
	
	
	// location of UUID for drives is: ls -al /dev/disk/by-uuid/
	$fstab_location = "/etc/fstab";
	
	// Get the list of supported filesystems by this host.
	$kernel_version = exec("uname -r");
	$fs_list_path = "/lib/modules/" . $kernel_version . "/kernel/fs";
	$files_and_dirs = array_diff(scandir($fs_list_path), array('.', '..'));
	$num_supported_fs = sizeof($files_and_dirs);

	// Input the supported filesystems into the tbl_filesystems tables if they don't already exist.
	foreach($files_and_dirs as $this_fs_type)
	{ 
                $result_size = sizeof(R::find("filesystems", " fs_type = ? ", array($this_fs_type)));

		if ($result_size == 0)
		{
                    $fs_bean = R::dispense("filsystems");
                    $fs_bean->fs_type = $this_fs_type;
                    $fs_bean->is_supported = 1;
                    R::store($fs_bean);
		}
	}


	$mount_output = `mount`;
	$mount_array = array_values(preg_grep("/^\/dev/",preg_split("/\n/", $mount_output)));
	echo "\n    Currently mounted partitions that are supported by AFRS are:\n";
	
        $supported_array = R::find("filesystems", " is_supported = 1 ");
	
	// Search for the mounted partitions that are supported.
	$count = 1;
	foreach($supported_array as $this_supported_fs)
	{
		for($i = 0; $i < sizeof($mount_array); $i++)//$mount_array as $this_mount)
		{
                        $fs_type = $this_supported_fs->fs_type;
			if (preg_match("/$fs_type/", $mount_array[$i]) > 0)
			{
				$choice_array[$count] = $mount_array[$i];
				echo "      " . ($count) . "). " . $mount_array[$i] . " \n";
				$count++;
			}
		}
	}
	echo "\n--> Which device would you like to setup?: ";
	$input = trim(fgets(STDIN));
	echo "--> Is this device removable? (y/n): ";
	$is_removable = trim(fgets(STDIN));
	$dev_array = split(" ", $choice_array[$input]);  // Split so we can get the devices name e.g. /dev/xxx.
	
	$udev_output = `udevinfo --query=all  --name $dev_array[0]`;
	
	$dev_id = array_values(preg_grep("/ID_SERIAL=/",preg_split("/\n/", $udev_output)));  // Need to use array_values so we can index the return array at [0].
	$dev_id = split("=", $dev_id[0]);
	$dev_id = trim($dev_id[1]);
	
	$vendor = array_values(preg_grep("/ID_VENDOR=/",preg_split("/\n/", $udev_output)));  // Need to use array_values so we can index the return array at [0].
	$vendor = split("=", $vendor[0]);
	$vendor = trim($vendor[1]);
	
	$model = array_values(preg_grep("/ID_MODEL=/",preg_split("/\n/", $udev_output)));  // Need to use array_values so we can index the return array at [0].
	$model = split("=", $model[0]);
	$model = trim($model[1]);
	$manufacturer = $vendor . "-" . $model;
	
	$dev_size = disk_total_space($dev_array[2]);
	$dev_free_space = disk_free_space($dev_array[2]);
	
	if ($is_removable == "y")
	{
		$is_removable = 1;
	}
	else if ($is_removable == "n")
	{
		$is_removable = 0;
	}
	else
	{
		$is_removable = 0;
	}
	
	$query->runQuery("insert into tbl_devices
						(device_id, manufacturer, is_removable, size, free_space, date_added)
						values('$dev_id', '$manufacturer', $is_removable, $dev_size, $dev_free_space, NOW())");
						
	// Now we create entries for the tbl_mounts table.
	$last_dev_insert_id = $query->getInsertID();
	$mount_point = $dev_array[2];
	
	$query->runQuery("insert into tbl_mounts
						(fk_device_id, mount_path, is_mounted)
						values($last_dev_insert_id, '$mount_point', 1)");
	
	exit(0);
	echo "Done.\n";
}

function inet_setup()
{
    // TODO:  use the device IDs from the /sys/class/net directory and look up the device names from the PCI device online database.
    
        $fqdn = null;
        $choice = null;
        $inet_devices_array = null;  // An associative array that holds the device info.
        
        // First let's query the db and see what device we currently have configured for afrs.
        $current_inet_devices_array = R::findAll("inetdevices");
        
        $user_input = null;
    
        while($user_input != 1 && $user_input != 2 && $user_input != 3 && $user_input != 4)
        {
            echo "\n    1.) List AFRS-configured inet devices.
    2.) Add a device.
    3.) Edit a device.
    4.) Remove a device from AFRS.
    5.) Back

    Selection: ";

            $user_input = trim(fgets(STDIN));
        }
        if ($user_input == 1)
        {
            if (sizeof($current_inet_devices_array) > 0)
            {
                menu("display", $current_inet_devices_array, "hide", array("id"));
                echo "\n\n\n";
                begin();
            }
        }
        
        else if ($user_input == 2)
        {
            $available_inet_devices = null;

            // TODO:  Need to support multiple network interfaces.
            //echo "DEVS:  FIX ME.  I NEED MULTI INTERFACE SUPPORT!!!!\n";

            // Get devices from the system that don't have the same mac address as thos already configured 
            // in the afrs db.  This will give us a list for the user to add.
            $inet_macs_array = get_inet_macs();
            $inet_mac_keys = array_keys($inet_macs_array); // Get the ethernet names which are the keys.
            $current_inet_devices_array_keys = array_keys($current_inet_devices_array);
            $count = 0;

            for($i = 0; $i < sizeof($inet_mac_keys); $i++)
            {
                $found = false;
                for($j = 0; $j < sizeof($current_inet_devices_array); $j++)
                {
                    if ($inet_mac_keys[$i] == $current_inet_devices_array[$current_inet_devices_array_keys[$j]]->device_name)
                    {
                        $found = true;
                    }
                }
                if (!$found)
                {
                    $this_available_inet_device = get_inet_info_for_device($inet_mac_keys[$i]);

                    // Only list the device as available to be added to afrs if it's up and has a link on it.
                    //var_dump($this_available_inet_device);
                    if ($this_available_inet_device["state"] == "up" && $this_available_inet_device["link_detected"] == 1)
                    {
                        $available_inet_devices[] = $this_available_inet_device;
                        echo "\n" . ($i +1) . ".)  Dev: " . $this_available_inet_device["name"] . "  Mac: " . $this_available_inet_device["mac"] . "  Ip: " . $this_available_inet_device["ip_address"] . "\n";
                        $count++;
                    }
                    else
                    {
                        echo "\n  * NOTICE: Can't use device \"" . $this_available_inet_device["name"] . "\" since it's system state is \"" . $this_available_inet_device["state"] . "\"."; 
                    }
                }
            }
            
            echo "\n\nAvailable network devices to be added to AFRS:...";
            
            if ($count > 0)
            {
                echo "\n\n";
            }
            else
            {
                echo "None.\n\n";
                begin();
            }
        }
                
        else if ($user_input == 3)
        {
            $inet_index = menu("display", $current_inet_devices_array, "hide", array("id"));
            echo "\nEnter number of network device to edit: ";
            $edit_number = trim(fgets(STDIN));
            
            menu("edit", $current_inet_devices_array[$inet_index[$edit_number]], "hide", array("id", "mac"));
            begin();
        }
        
        else if ($user_input == 4)
        {
            $inet_index = menu("display", $current_inet_devices_array, "hide", array("id"));
            echo "\nEnter number of network device to remove: ";
            $remove_number = trim(fgets(STDIN));
            $inet_device_bean = $current_inet_devices_array[$inet_index[$edit_number]];
            R::trash($inet_device_bean);
            begin();
        }
            
            /*$i = 0;
            foreach($current_inet_devices_array as $this_inet_device)
            {
                echo ($i +1) . ".)  Dev: " . $this_inet_device->device_name . "  Mac: " . $this_inet_device->mac . "  Ip: " . $this_inet_device->ip . "\n";
                $i++;
            }
            
            echo "\nOperation list: a = add new device to afrs.\n                x = delete existing device from afrs\nChoice: ";
            $choice = trim(fgets(STDIN));
            
            if ($choice == "a" or $choice == "A")
            {
                $available_inet_devices = null;
                
                echo "\nAvailable network devices to be added to AFRS:...";
                
                // TODO:  Need to support multiple network interfaces.
                echo "DEVS:  FIX ME.  I NEED MULTI INTERFACE SUPPORT!!!!\n";
                
                // Get devices from the system that don't have the same mac address as thos already configured 
                // in the afrs db.  This will give us a list for the user to add.
                $inet_macs_array = get_inet_macs();
                $inet_mac_keys = array_keys($inet_macs_array); // Get the ethernet names which are the keys.
                $count = 0;
                
                for($i = 0; $i < sizeof($inet_mac_keys); $i++)
                {
                    $found = false;
                    for($j = 0; $j < sizeof($current_inet_devices_array); $j++)
                    {
                        if ($inet_mac_keys[$i] == $current_inet_devices_array[$j]["device_name"])
                        {
                            $found = true;
                        }
                    }
                    if (!$found)
                    {
                        $this_available_inet_device = get_inet_info_for_device($inet_mac_keys[$i]);
                        
                        // Only list the device as available to be added to afrs if it's up and has a link on it.
                        if ($this_available_inet_device["state"] == "up" && $this_available_inet_device["link_detected"] == 1)
                        {
                            $available_inet_devices[] = $this_available_inet_device;
                            echo "\n" . ($i +1) . ".)  Dev: " . $this_available_inet_device["name"] . "  Mac: " . $this_available_inet_device["mac"] . "  Ip: " . $this_available_inet_device["ip_address"] . "\n";
                            $count++;
                        }
                    }
                }
                if ($count == 0)
                {
                    echo "None.\n";
                }
            }
            exit(0); 
        }*/
        
	
	/*echo "  \nSearching for network devices... ";
        $inet_devices_array = get_inet_info();  // Get a 3-d associative array of network devices and their attributes.
        
        $inet_device_names = preg_split("/\n/", shell_exec("ifconfig -s | cut -d ' ' -f 1 | tail -n +2"));
        unset($inet_device_names[(sizeof($inet_device_names) - 1)]);  // Get rid of the extra trailing backspace.
        unset($inet_device_names[array_search("lo", $inet_device_names)]);  // Get rid of the loopback device "lo".
        $inet_device_names = array_merge(array(), $inet_device_names);  // Fix the array indexes after we deleted some elements.
	$inet_device_count = sizeof($inet_device_names);
	if ($inet_device_count > 0)  // Make sure we have devices to work with.
	{
            echo "  Found " . $inet_device_count . ".\n";

            for($i = 0; $i < sizeof($inet_devices_array); $i++)
            {
                $usable = null;
                $link_detected_desc = null;
                
                if ($inet_devices_array[$i]["link_detected"] == "1")
                {
                    $link_detected_desc = "link detected";
                }
                else
                {
                    $link_detected_desc = "no link detected";
                }
                if ($inet_devices_array[$i]["state"] == "down" || $inet_devices_array[$i]["link_detected"] == 0)
                {
                    $usable = " (Unusable - since state is down and/or no link detected.)";
                }
                echo "\n    " . ($i + 1) . ".)" . $usable . "\n        Dev:     " . $inet_devices_array[$i]["name"] . "\n        Mac:     " . $inet_devices_array[$i]["mac"] .  "\n        Addr:    " . $inet_devices_array[$i]["ip_address"] . "\n        Gateway: " . $inet_devices_array[$i]["gateway"] . "\n        Subnet:  " . $inet_devices_array[$i]["mask"] . "\n        Bcast:   " . $inet_devices_array[$i]["broadcast"] . "\n        Speed:   " . $inet_devices_array[$i]["speed"] . " Mbps" . "\n        Dns:     " . $inet_devices_array[$i]["nameserver"] . "\n" . "        Status:  " . $inet_devices_array[$i]["state"] .  "\n" . "        Link status: " . $link_detected_desc . "\n";
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
                    $fqdn = $inet_devices_array[($dev_choice - 1)]["ip_address"];
                }
                else
                {
                    echo "  --> Can't go any further then.  Please fix and try again.\n";
                }
            }
            else
            {
                // Got a valid dns A record response for this name.
                if ($dns_query_output_array[0]["ip"] = $inet_devices_array[($dev_choice - 1)]["ip_address"])
                {
                    echo "DNS query succeeded\n";
                    $fqdn = $fqdn_input;
                }
                else
                {
                    echo "ERROR:  DNS answer for " . $fqdn_input . " has a different IP address than the one used for interface " . $inet_devices_array[($dev_choice - 1)]["name"] . "\n";
                    exit(1);
                }
            }

            // Now insert the data into the afrsd database.
            $new_inet_device_bean = R::dispense("inetdevices");
            $new_inet_device_bean->device_name = $inet_devices_array[($dev_choice - 1)]["name"];
            $new_inet_device_bean->mac = $inet_devices_array[($dev_choice - 1)]["mac"];
            $new_inet_device_bean->ip = $inet_devices_array[($dev_choice - 1)]["ip_address"];
            $new_inet_device_bean->broadcast = $inet_devices_array[($dev_choice - 1)]["broadcast"];
            $new_inet_device_bean->subnet_mask = $inet_devices_array[($dev_choice - 1)]["mask"];
            $new_inet_device_bean->gateway = $inet_devices_array[($dev_choice - 1)]["gateway"];
            $new_inet_device_bean->dns1 = $inet_devices_array[($dev_choice - 1)]["nameserver"];
            $new_inet_device_bean->fqdn = $fqdn;
            $new_inet_device_bean->speed = $inet_devices_array[($dev_choice - 1)]["speed"];
            $new_inet_device_bean->link_state = $inet_devices_array[($dev_choice - 1)]["link_detected"];
            
            if ($inet_devices_array[($dev_choice - 1)]["state"] == "up")
            {
                $status = 1;
            }
            else if ($inet_devices_array[($dev_choice - 1)]["state"] == "down")
            {
                $status = 0;
            }
                    
            $new_inet_device_bean->status = $status;
            
            if (R::store($new_inet_device_bean) > 0)
            {
                echo "Successfully added device to the database.\n";
            }
            else
            {
                echo "ERROR:  Failed to add interface to database.  Please see previous errors and correct.\n";
                exit(1);
            }


	}
	else
	{
		echo "No ethernet devices found.  Please configure an ethernet device on your system and rerun this setup script.\n";
		return (false);
	}*/
}


function syncpartners_setup()
{
	global $afrs_client_obj;
	
        $sync_partners_beans_array = R::findAll("syncpartners");
        //echo "\nYou currently have the following sync partners:\n\n";
        //menu("display", $sync_partners_beans_array, "hide", array("id", "clientid", "partner_public_key"));
        
        $sync_partner_register_requests_array = R::find("syncpartnerregisterrequests", " initiator = 2 ");
        if (sizeof($sync_partner_register_requests_array) > 0)
        {
            echo "\nYou have " . sizeof($sync_partner_register_requests_array) . " register request(s) pending from potential sync partners:\n\n";
            $menu_ids = menu("display", $sync_partner_register_requests_array, "hide", array("id", "clientid", "partner_public_key", "transaction_id"));
                       
            echo "\nWould you like to authorized any of the above requests? [all][n][1,2,3,...]: ";
            $input = trim(fgets(STDIN));
            
            if ($input != "n" or $input != "N")
            {
                if ($input == "all")
                {
                    

                }
                else
                {
                    if (sizeof($input) == 1)
                    {
                        // These will be proxied and values change in the Dispatcher class.
                        $sync_add_ip = $sync_partner_register_requests_array[$bean_index_ids[$input]]->ip_address;
                        $sync_add_port = $sync_partner_register_requests_array[$bean_index_ids[$input]]->port_number;
                        $sync_add_fqdn = $sync_partner_register_requests_array[$bean_index_ids[$input]]->fqdn;
                        $sync_add_transaction_id = $sync_partner_register_requests_array[$bean_index_ids[$input]]->transaction_id;
                        $partner_parameters = array('ADDRESS' => $sync_add_ip,
                                                                    'PORT' => $sync_add_port,
                                                                    'NAME' => $sync_add_fqdn,
                                                                    'TIMEZONE' => 0,
                                                                    'BANDWIDTHUP' => 0,
                                                                    'BANDWIDTHDOWN' => 0,
                                                                    'AFRSVERSION' => 0,
                                                                    'PRIORITY' => 'medium');

                        $register_answer = $afrs_client_obj->registerNewSyncPartner($partner_parameters);
                        if ($register_answer === true)
                        {
                                echo "Successfully added " . $sync_add_fqdn . " to list of sync partners\n";
                        }
                        else if (is_object($register_answer))  //  If an object is returned, more than likely an error occured while communicating.
                        {
                                echo $register_answer->getCommand() . " -> " . $register_answer->getReplyNotes() . "\n";
                                begin();
                        }
                        else
                        {
                                echo "afrs-config::registerNewSyncParter() - ERROR: An uknown error occured\n";  // Display the error returned.
                                begin();  // Return the to begining of the config script.
                        }
                    }
                    elseif (sizeof($input) > 1)  // TODO:  Finish this.
                    {
                        $selection_array = explode(",", $input);
                    }
                    
                }
            }
        }
	
	echo "\n1.) Configure a new sync partner.
2.) List current sync partners.
3.) Edit a sync_partner.
4.) Remove a sync partner.
5.) Back

Selection: ";
	
	$sync_input = trim(fgets(STDIN));
	
	if ($sync_input == 1)
	{
		echo "-- Configure a new sync partner --\n";
		echo "--> FQDN or short name of sync partner: ";
		$sync_add_fqdn = trim(fgets(STDIN));
		
		echo "--> IP address of sync partner (public IP if remote end is NATed behind a firewall): ";
		$sync_add_ip = trim(fgets(STDIN));
		
		echo "--> Port number of sync partner [4747]: ";
		$sync_add_port = trim(fgets(STDIN));
		if (strlen($sync_add_port) == 0)
		{
			$sync_add_port = 4747;
		}

		$partner_parameters = array('ADDRESS' => $sync_add_ip,
								'PORT' => $sync_add_port,
								'NAME' => $sync_add_fqdn,
								'TIMEZONE' => 0,
								'BANDWIDTHUP' => 0,
								'BANDWIDTHDOWN' => 0,
								'AFRSVERSION' => 0,
								'PRIORITY' => 'medium');

		$register_answer = $afrs_client_obj->registerNewSyncPartner($partner_parameters);
		if ($register_answer === true)
		{
			echo "Successfully added " . $sync_add_fqdn . " to list of sync partners\n";
		}
		else if (is_object($register_answer))  //  If an object is returned, more than likely an error occured while communicating.
		{
			echo $register_answer->getCommand() . " -> " . $register_answer->getReplyNotes() . "\n";
			begin();
		}
		else
		{
			echo "afrs-config::registerNewSyncParter() - ERROR: An uknown error occured\n";  // Display the error returned.
			begin();  // Return the to begining of the config script.
		}

	}
        else if ($sync_input == 2)
        {
            echo "-- Currently configured sync partners: --\n\n";
            menu("display", $sync_partners_beans_array, "hide", array("id", "partner_public_key"));
            begin();
        }
        
        else if ($sync_input == 3)
        {
            echo "-- Currently configured sync partners: --\n\n";
            $menu_ids_index = menu("display", $sync_partners_beans_array, "hide", array("id", "partner_public_key"));
            echo "Enter the sync partner number you want to edit: ";
            $sync_edit_number = trim(fgets(STDIN));
            
            // Some values should not be editable.
            menu("edit", $sync_partners_beans_array[$menu_ids_index[$sync_edit_number]], "hide", array("id", "date_added", "nated", "last_checkin", "failed_checkin_count", "partner_public_key"));
            begin();
        }
        
        //End of configure new sync partner.
}

function watchedlocations_setup()
{
	//global $query, $afrs_client_obj, $yesno_map;
    
        // R::dependencies(array("events"=>array("watches")));
        
        $tbl_watches_array = R::findAll("watches");

	//print_watch_locations($tbl_watches_array);
        echo "\nCurrently defined watch locations:\n";
        menu("display", $tbl_watches_array, "show", array("name", "watch_path", "date_added"));

	echo "\n1.) Add a new watch location.
2.) Edit a watch location.
3.) Remove a watch location.
4.) Back

Selection: ";

	$user_input = trim(fgets(STDIN));

	if ($user_input == 1)
	{
                $exclusion_patterns = "NULL";
                $filter_by_group_owner = "NULL";
                $filter_by_user_owner = "NULL";
                $ignore_zero_files = 0;
                $verbose = 0;
                $allow_yelling = 0;
                $wait_amount = 2;
            
		echo "\nWatch location (full local path): ";
		$watch_path = trim(fgets(STDIN));
                while (is_dir($watch_path) === false)
                {
                    echo "ERROR:  Watch location does not exist.  Try again.\n";
                    echo "Watch location (full local path): ";
                    $watch_path = trim(fgets(STDIN));
                }
                
                echo "\nWatch name: ";
                $watch_name = trim(fgets(STDIN));
                while($watch_name == "" or $watch_name === null)
                {
                    echo "ERROR:  You did not specify a watch name.  Try again.\n";
                    echo "\nWatch name: ";
                    $watch_name = trim(fgets(STDIN));
                }
		
                echo "\nEvents (seperated by commas (no spaces).  Type \"help\" for a list of supported events).: ";
		$events = trim(fgets(STDIN));
                $events = explode(",", $events);
                $allowed_events = array("create", "delete", "modify", "open", "close", "move", "attribute", "all_events");
                $invalid_events = null;
                foreach($events as $this_event)
                {
                    if (!in_array($this_event, $allowed_events))
                    {
                        $invalid_events[] = $this_event;
                    }
                }
                while ($invalid_events != null)
                {
                    foreach($invalid_events as $this_invalid_event)
                    {
                        if ($this_invalid_event == "help")
                        {
                            echo "\nWatch events:  create,delete,modify,open,close,move,attribute,all_events";
                        }
                        else
                        {
                            echo "\nERROR: " . $this_invalid_event . " is not a recognized event!";
                        }
                    }
                    $invalid_events = null;
                    echo "\nEvents (seperated by commas (no spaces). Type \"help\" for a list of supported events).: ";
                    $events = trim(fgets(STDIN));
                    $events = explode(",", $events);
                    foreach($events as $this_event)
                    {
                        if (!in_array($this_event, $allowed_events))
                        {
                            $invalid_events[] = $this_event;
                        }
                    }
                }
                
                echo "\nWatch all subdirectories (recursively)? (yes/no): ";
                $recursive = trim(fgets(STDIN));
                while($recursive != "yes" && $recursive != "no")
                {
                    echo "\nError: Invalid input!\nWatch all subdirectories (recursively)? (yes/no): ";
                    $recursive = trim(fgets(STDIN));
                }
                $recursive = yesno_map($recursive);
                
                echo "\nWatch hidden files and directories? (yes/no): ";
                $hidden = trim(fgets(STDIN));
                while($hidden != "yes" && $hidden != "no")
                {
                    echo "\nError: Invalid input!\nWatch hidden files and directories? (yes/no): ";
                    $hidden = trim(fgets(STDIN));
                }
                $hidden = yesno_map($hidden);
                
                echo "\nFollow sym links? (yes/no): ";
                $symlinks = trim(fgets(STDIN));
                while($symlinks != "yes" && $symlinks != "no")
                {
                    echo "\nError: Invalid input!\nFollow sym links? (yes/no): ";
                    $symlinks = trim(fgets(STDIN));
                }
                $symlinks = yesno_map($symlinks);
                
                echo "\nShow advanced options? (yes/no): ";
                $adv_options = trim(fgets(STDIN));
                while($adv_options != "yes" && $adv_options != "no")
                {
                    echo "\nError: Invalid input!\nShow advanced options? (yes/no): ";
                    $adv_options = trim(fgets(STDIN));
                }
                if ($adv_options == "yes") //TODO:  Need to complete the advanced options section.
                {
                    echo "Developers, Fixme!!\n";
                    exit (1);
                }
                
                $date_added = date("Y\-m\-d G:i:s");
                
                $new_watch = R::dispense("watches");
                $new_watch->name = $watch_name;
                $new_watch->watch_path = $watch_path;
                
                $new_watch->recursive = $recursive;
                $new_watch->watch_hidden_directories = $hidden;
                $new_watch->watch_hidden_files = $hidden;
                $new_watch->exclusion_patterns = $exclusion_patterns;
                $new_watch->symbolic_links = $symlinks;
                $new_watch->filter_by_group_owner = $filter_by_group_owner;
                $new_watch->filter_by_user_owner = $filter_by_user_owner;
                $new_watch->ignore_zero_files = $ignore_zero_files;
                $new_watch->verbose = $verbose;
                $new_watch->allow_yelling = $allow_yelling;
                $new_watch->date_added = $date_added;
                $new_watch->sync = 0;
                $new_watch->active = 1;
                $new_watch->wait_amount = $wait_amount;
                
                $events_lookup = R::findAll("events");
                $event_ids = null;
                
                foreach($events as $this_event)
                {
                    foreach($events_lookup as $this_event_lookup)
                    {
                        if ($this_event == $this_event_lookup->short_event_name)
                        {
                            /* Store the event ids along with the newly-created watch id in a 
                             * separate lookup table called Events_Watches.  Events_Watches table
                             * will be auto-created by RedBean.
                            */
                            $new_watch->sharedEvents[] = $this_event_lookup;
                        }
                    }
                }   
                
                $new_watch_id = R::store($new_watch);
                if (is_int($new_watch_id))
                {
                    echo "\nSuccessfully added watch location.\n";
                }
	}
	else if ($user_input == 2)
	{ 
            echo "\nCurrently defined watch locations:\n";
            $menu_ids = menu("display", $tbl_watches_array, "hide", array("id", "fk_event_ids"));
            echo "\nEnter watch number to edit:";
            $watch_number = trim(fgets(STDIN));
            
            if ($watch_number != null || $watch_number != "")
            {                
                $watch_details = R::findOne("watches", " id = ? ", array($menu_ids[$watch_number]));
            }
            
            
            echo "\nWatch details:";
                       
            menu("edit", $watch_details, "hide", array("id","fk_event_ids"));
            
	}
	else if ($user_input == 3)
	{
            echo "\nCurrently defined watch locations:\n";
            $menu_ids = menu("display", $tbl_watches_array, "show", array("name", "watch_path", "date_added"));
            
            $watch_number = null;
            echo "\nEnter watch number to remove:";
            $watch_number = trim(fgets(STDIN));
            
            // Make sure this watch is not part of a share.  If it is.  Tell the users to unshare and try again.
            //$result = $query->runQuewyGetResults("select * from tbl_shares where watch_id = $watch_id", "assoc");
            $watch_bean = R::findOne("watches", " id = ? ", array($menu_ids[$watch_number]));
            
            if ($watch_bean !== null)
            {
                // First delete any entries in the events_watches table.
                $watch_events_beans = $watch_bean->sharedEvents;
                foreach($watch_events_beans as $this_event_bean)
                {
                    array_shift($watch_bean->sharedEvents);
                }
                R::store($watch_bean);  // Need to do this to delete the entries in the events_watches table.
                R::trash($watch_bean);  // Now we can delete the watch bean and it's corresponding db entry.
            }
	}
	else if ($user_input == 4)
	{
            begin();
	}
}

function shares_setup()
{
    global $afrs_client_obj;
    
    $shares_beans_array = R::findAll("shares");

    $share_lookup = null;
    echo "\n\nExisting shares:\n";
    
    menu("display", $shares_beans_array, "hide", array("id", "watches_id"));
    
    $user_input = null;
    
    while($user_input != 1 && $user_input != 2 && $user_input != 3 && $user_input != 4)
    {
        echo "\n1.) Add a new share.
2.) Edit a share.
3.) Remove a share.
4.) Back

Selection: ";
    
        $user_input = trim(fgets(STDIN));
    }
    if ($user_input == 1)
    {
        echo "\nBelow are a list of Watches that can be shared.\n\n";
        $query_string = "select * from watches where id not in (select watches_id from shares)";  // 2nd param on menu() needs to be passed by reference so we need a variable to store the query string.
        $menu_ids = menu("display", $query_string, "show", array("watch_name", "watch_path"));
        
        if (sizeof($menu_ids) > 0)
        {
            $watch_number = null;
            echo "\nEnter watch number to share:";
            $watch_number = trim(fgets(STDIN));
            
            $watch_bean = R::load("watches", $menu_ids[$watch_number]);
            
            echo "\nWhat name would you like to use for this share? (" . $watch_bean->watch_name . ") : ";
            $new_share_name = trim(fgets(STDIN));
            if (strlen($new_share_name) == 0)
            {
                $new_share_name = $watch_bean->watch_name;
            }
            
            echo "\nWould you like to activate this share? (y/n): ";
            $new_share_active = yesno_map(trim(fgets(STDIN)));
            echo "\n** Calculating share size information.  Please wait... ";
            $new_share_size = get_dir_size($watch_bean->watch_path);
            echo "Done".
            
            $new_share_bean = R::dispense("shares");
            $new_share_bean->watches = $watch_bean;
            $new_share_bean->name = $new_share_name;
            $new_share_bean->unique_identifier = createhash();
            $new_share_bean->size = $new_share_size;
            $new_share_bean->active = $new_share_active;
            $new_share_bean->creation_date = R::$f->now();
            $new_share_bean->ownWatches = array($watch_bean);
            R::store($new_share_bean);            
        }
        else
        {
            echo "\n** All watch locations are currently being shared or no watch locations exist!\n";
            shares_setup();
        }
        
    }
    else if ($user_input == 2)
    {
        echo "\nCurrent shares:.\n\n";
        $share_beans_array = R::findAll("shares");
        $menu_ids = menu("display", $share_beans_array, "show", array("name", "active", "creation_date"));
        
        if (sizeof($menu_ids) > 0)
        {
            $share_number = null;
            echo "\nEnter share number to edit:";
            $share_number = trim(fgets(STDIN));
            
            if (strlen($share_number) > 0 && $share_number > 0)
            {
                menu("edit", $share_beans_array[$menu_ids[$share_number]], "show", array("name", "active"));
            }
        }                
    }
    else if ($user_input == 3)
    {
        echo "\nCurrent shares:.\n\n";
        $share_beans_array = R::findAll("shares");
        $menu_ids = menu("display", $share_beans_array, "show", array("name", "active", "creation_date"));
        
        if (sizeof($menu_ids) > 0)
        {
            $share_number = null;
            echo "\nEnter share number to remove:";
            $share_number = trim(fgets(STDIN));
            
            if (strlen($share_number) > 0 && $share_number > 0)
            {
                R::trash($share_beans_array[$menu_ids[$share_number]]);
            }
        }  
    }
    else if ($user_input == 4)
    {
        begin();
    }
}

function synced_shares_setup()
{
    global $afrs_client_obj;
    
    echo "\n1.) Request new sync for existing syncpartner.
2.) 
3.) Back

Selection: ";
    
    $user_input = trim(fgets(STDIN));
    if ($user_input == 1)
    {
        echo "\n";
        $syncpartners_beans_array = R::find("syncpartners", " enabled = 1 ");
        $menu_ids = menu("display", $syncpartners_beans_array, "show", array("fqdn"));
        echo "Select existing partner for syncing:";
        $syncpartner_input = trim(fgets(STDIN));
        
        echo "Sync a share from local server or from remote syncpartner? (local/remote): ";
        $sync_source = trim(fgets(STDIN));
        if ($sync_source == "local")
        {
            $available_shares_beans = R::convertToBeans("shares", R::getAll("select * from shares where id not in 
                                                                               (select shares_id from sharessyncpartners)
                                                                             and active = 1"));
            echo "\nBelow is a list of shares that you can sync with " . $syncpartners_beans_array[$menu_ids[$syncpartner_input]]->fqdn . ":\n";
            menu("display", $available_shares_beans, "show", array("name", "size", "creation_date"));
            echo "Enter the share number you would like to sync: ";
            $new_share_to_sync_input = trim(fgets(STDIN));
            
            echo "Do you want to send updates to, or receive updates from remote partner, or both? (send/receive/both): ";
            $push_or_pull_input = trim(fgets(STDIN));
            
            if ($push_or_pull_input == "send")
            {
                echo "Sync to an existing share on remote partner or create a new remote share on partner? (exist/new): ";
                $sync_existing_or_new_input = trim(fgets(STDIN));
                
                if ($sync_existing_or_new_input == "exist")
                {
                    // Now we need to get a list shares from remote end and display them.
                    $register_answer = $afrs_client_obj->getRemoteShares();
                    if ($register_answer === true)
                    {
                            echo "Successfully added " . $sync_add_fqdn . " to list of sync partners\n";
                    }
                    else if (is_object($register_answer))  //  If an object is returned, more than likely an error occured while communicating.
                    {
                            echo $register_answer->getCommand() . " -> " . $register_answer->getReplyNotes() . "\n";
                            begin();
                    }
                    else
                    {
                            echo "afrs-config::registerNewSyncParter() - ERROR: An uknown error occured\n";  // Display the error returned.
                            begin();  // Return the to begining of the config script.
                    }
                    
                    
                    
                }
                else if ($sync_existing_or_new_input == "new")
                {
                    
                }
            }
            else if ($push_or_pull_input == "pull")
            {
                
            }
        }
        else if ($sync_source == "remote")
        {
            
        }
    }
        
}
?>