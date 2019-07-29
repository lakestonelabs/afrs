<?php

//RedBean
require_once(__DIR__."/../orm/rb.php");  // The ORM technology used by Afrs.
require_once(__DIR__."/InetDevice.php");

Class System
{
    
    private $inet_device_objects = null;
			
	function __construct()
	{
            
            require(__DIR__."/../../conf/afrs_vars.php");  // Variables are out of scope if outside a function and outside a class declaration in Classes.
            require(__DIR__."/../../conf/afrs_config.php");  // Variables are out of scope if outside a function and outside a class declaration in Classes.
            
            
            // Create a db connection since we will be querying the db a lot.
            //RedBean
            R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);
            
            // Retreive the information for all devices on the system.
            $this->processInetDevices(false);
	}
	
	// Returns true if all network interfaces are up, false if any are down and displays those downed interfaces.
	function checkNetwork()
	{
		// First get a list of all network interfaces.
		$this->query->runQuery("select * from tbl_inet_devices");
		
		if ($this->query->getResultSize() > 0)
		{
			echo "Precheck:(System): All defined network devices found.  Good!\n";
		}
		else
		{
			echo "ERROR: No inet devices defined.  Please run afrs-config\n";
			exit(1);
		}
	}
	
	function checkHardDrives()
	{
		echo "Checking hard drives...\n";
		$scsi_count = exec("cat /proc/scsi/scsi | grep -c Host:");
		//$ata_count = exec("cat /proc/hdd/hdd | grep -c Host:");
		echo "   Found " . $scsi_count . " SCSI devices.\n";
		echo "   Found " . $ata_count . " ATA/SATA devices.\n";
		
		
		// location of UUID for drives is: ls -al /dev/disk/by-uuid/
		$fstab_location = "/etc/fstab";
		
		// Get the list of supported filesystems by this host.
		$kernel_version = exec("uname -r");
		$fs_list_path = "/lib/modules/" . $kernel_version . "/kernel/fs";
		$files_and_dirs = array_diff(scandir($fs_list_path), array('.', '..'));
		$num_supported_fs = sizeof($files_and_dirs);
		$mount_output = `mount`;
		
		echo "   This system supports " . $num_supported_fs . " filesystems\n";
		
		$this->query->runQuery("select * from tbl_filesystems where is_supported = 1");
		$supported_array = $this->query->getResultsArray();
		//var_dump($supported_array);
		foreach($supported_array as $this_supported_fs)
		{
			echo "   Checking for " . $this_supported_fs["fs_type"] . " support....";
			if (in_array($this_supported_fs["fs_type"], $files_and_dirs))
			{
				echo " OK\n";
			}
			else
			{
				echo " NO.  Please fix.\n";
			}
		}
		
		echo "Done.\n";
	}
        
        /*
         * Returns a InetDevice object based on the device's name.
         */
        public function getInetDevice($device_name)
        {
            if ($this->inet_device_objects === null)
            {
                $this->processInetDevices(false);
            }
            foreach($this->inet_device_objects as $this_inet_device_object)
            {
                if ($this_inet_device_object->getDeviceName() == trim($device_name))
                {
                    return $this_inet_device_object;
                }
            }
            return false;
        }
        
        
        /*
         * Returns an array of InetDevice objects with integers as indexes.
         */
        public function getInetDevices()
        {
            if ($this->inet_device_objects == null)
            {
                $this->processInetDevices();
            }
            return $this->inet_device_objects;
        }
        
        public function requeryInetDevices($quick)
        {
            return $this->processInetDevices($quick);
        }
        
        
        /*
         * Should only be called by the constructor or requeryInetDevices().
         * The $quick parameter is used to tell the InetDevice class to not
         * query things like public ip address or gateway info.  $quick is 
         * usually used to get quick up/down status.
         * 
         * $quick should be either true/false.
         * 
         */
        private function processInetDevices($quick)
        {
            $this->inet_device_objects = null;
            
            if ($quick === true || $quick === false)
            {
            
                $inet_device_names = preg_split("/\n/", shell_exec("ifconfig -s | cut -d ' ' -f 1 | tail -n +2"));
                unset($inet_device_names[(sizeof($inet_device_names) - 1)]);  // Get rid of the extra trailing backspace.
                unset($inet_device_names[array_search("lo", $inet_device_names)]);  // Get rid of the loopback device "lo".
                $inet_device_names = array_merge(array(), $inet_device_names);  // Fix the array indexes after we deleted some elements.

                foreach($inet_device_names as $this_inet_device_name)
                {
                    $this->inet_device_objects[] = new InetDevice($this_inet_device_name, $quick);
                }

                if (sizeof($this->inet_device_objects > 0))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
}
?>