<?php
// Licensed under GPLv3.  Copyrighted Mike Lee 2008-2009
// Implements a object of type FSNotify which uses
// the php-inotify tools to make linux filesytem 
// inotify watches for files and directory.  This class
// simply abstracts or makes it easier to use the 
// php-inotify functions.
//
// I mainly created this since the iwatch program was 
// broken and never correctly executed external functions 
// like its documentation said it would.

Class FSNotify
{
	protected $fd = null, 
			  $watch_dirs_array = null,  // An array with dirs as the indexes and the values are the inotify watch descriptors.
			  $change_defs = array("prog_executed" => "1",
			  						"modify" => "IN_MODIFY",
								   "attrib" => "IN_ATTRIB",
								   "close_write" => "IN_CLOSE_WRITE",
								   "close_nowrite" => "IN_CLOSE_NOWRITE",
								   "close" => "IN_CLOSE", 
								   "open" => "IN_OPEN",
			  					   "moved_from" => "IN_MOVED_FROM",
			  						"moved_to" => "IN_MOVED_TO",
			  						"move" => "IN_MOVE", 
			  						"create" => "IN_CREATE",
			  						"delete" => "IN_DELETE",
			  						"delete_self" =>"IN_DELETE_SELF",
			  						"move_self" => "IN_MOVE_SELF",
			  						"all" => "IN_ALL_EVENTS",
			  						"unmount" => "IN_UNMOUNT",
			  						"queue_overflow" => "IN_Q_OVERFLOW",
			  						"ignored" => "IN_IGNORE",
			  						"isdir" => "IN_ISDIR",
			  						"dir_closed" => "1073741840",
			  						"dir_opened" => "1073741856",
			  						"dir_created" => "1073742080",
			  						"dir_deleted" => "1073742336"),
			  $watch_event_values = null;
			  
	protected	  $event_map = array("1" => "prog_executed",
			  					"2" => "modify",
			  					 "4" => "attrib",
			  					"8" => "close_write",
			  					"16" => "close_nowrite",
			  					"24" => "close",
			  					"32" => "open",
			  					"64" => "moved_from",
			  					"128" => "moved_to",
			  					"192" => "move",
			  					"256" => "create",
			  					"512" => "delete",
			  					"1024" => "delete_self",
			  					"2048" => "move_self",
			  					"4095" => "all",
			  					"8192" => "unmount",
			  					"16384" => "queue_overflow",
			  					"32768" => "ignored",
			  					"1073741824" => "is_dir",
			  					"1073741840" => "dir_closed",
			  					"1073741856" => "dir_opened",
			  					"1073742080" => "dir_created",
			  					"1073742336" => "dir_deleted",
			  					"1073741828" => "dir_touched",
			  					"prog_executed" => "1",
			  					"modify" => "2",
			  					"attrib" => "4",
			  					"close_write" => "8",
			  					"close_nowrite" => "16",
			  					"close" => "24",
			  					"open" => "32",
			  					"moved_from" => "64",
			  					"moved_to" => "128",
			  					"move" => "192",
			  					"create" => "256",
			  					"delete" => "512",
			  					"delete_self" => "1024",
			  					"move_self" => "2048",
			  					"all" => "4095",
			  					"unmount" => "8192",
			  					"queue_overflow" => "16384",
			  					"ignored" => "32768",
			  					"is_dir" => "1073741824",
			  					"dir_closed" =>	"1073741840",
			  					"dir_opened" => "1073741856",
			  					"dir_created" => "1073742080",
			  					"dir_deleted" => "1073742336",
			  					"dir_touched" => "1073741828");
			  
	
	function __construct() // Accepts an array of directories to watch for events.
	{
		$this->fd = inotify_init();  // Open an inotify instance.  Only create one inotify instance since one inotify_init 
									 // file descriptor (fd) can point to multiple paths to watch.  If you try and run 
									 // inotify_init() for every watch you will exceed the kernel watch limit which is 
									 // provided in /proc/sys/fs/inotify/*.
		
	}
	
	function readEvents() // Returns an 2d array of file change information for all file changes since last read.
	{
		return(inotify_read($this->fd));
	}
	
	function getPossibleEvents()  // Returns and array of events this class can watch for.
	{
		return($this->event_map);
	}
	
	function translateEvent($trans_from)
	{
		return($this->event_map[$trans_from]);
	}
	
	function getWatchQueueSize()
	{
		return (inotify_queue_len($this->fd));
	}
	
	function getChangeMappings()  // Returns the actual inotify values for our given short name.
	{
		
	}
	
	function startWatching($dir_array, $events) // Actually executes the watching of files.  Returns a 2d array.
	{
		$events_size = sizeof($events);
		$event_count = 1;
		foreach($events as $this_event)  /// Build the valued string that represents the string.
		{
			$this->watch_event_values = $this->watch_event_values + constant($this->change_defs[$this_event]);  // Add up all the events to watch for.
		}
		
		$this->watch_events = $events;
		foreach($dir_array as $this_dir)
		{
			$this->watch_dirs_array[$this_dir] = inotify_add_watch($this->fd, $this_dir, $this->watch_event_values);  // Watch __FILE__ for metadata changes (e.g. mtime)
			
		}
	}

	function appendWatch($dir)  // Accepts a directory to append to the current inotify_watch instance.
	{
		$this->watch_dirs_array[$dir] = inotify_add_watch($this->fd, $dir, $this->watch_event_values);
	}

	// TODO:  Don't use this yet because I can't figure out how the inotify_rm_watch works.  I followed the directions but it errors
	// out stating that it's an invalid file or watch descriptor.
	function removeWatch($dir) // Stops the watching of files.
	{
		echo "Watch descriptor is: " . $this->watch_dirs_array[$dir] . "\n";
                var_dump($this->watch_dirs_array);
                if (is_long($this->watch_dirs_array[$dir]))
                {
                    // Stop watching __FILE__ for metadata changes
                    $bool = inotify_rm_watch($this->fd, $this->watch_dirs_array[$dir]);  // Remove the inotify watch for the directory based on the unique watch descriptor.
                    return $bool;
                }
	}
}
?>