<?php
require(__DIR__."/../conf/afrs_config.php");
require_once(__DIR__."/orm/rb.php");

//RedBean
require_once(__DIR__."/orm/rb.php");  // The ORM technology used by Afrs.


function get_local_ip_address()
{
        R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);
        $inet_device_bean = R::findOne("inetdevices", " status = 1 ");
	return $inet_device_bean->ip;        
}


function createRandomPassword($length) 
{
    $chars = "abcdef023456789";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;

    while ($i < $length) 
    {
        $num = rand() % 33;
        $tmp = substr($chars, $num, 1);
        $pass = $pass . $tmp;
        $i++;
    }
    return $pass;
}


function get_dir_size($dir) 
{ 
    if (strlen($dir) > 0)
    {
        $size = exec("du -s $dir | awk '{print $1}'"); // Had to use system command since integer is 32-bit and causes problems.
        return $size;
    }
    else
    {
        return false;
    }
    
}

function getSID()  // Gets the SID for this server.  The SID is used as the client_id in to remote partners.
{
	$db_conn = new DbConnectionWrapper("mysql", "localhost", "afrs", "afrs", "afrspassword");  // TODO  Need to figure out why I can't use the vars from the config include.
	$query = new QueryWrapper($db_conn);
	
	if ($query->runQuery("select value from tbl_registry where name = 'sid'"))
	{
		if ($query->getResultSize() > 0)
		{
			$result = $query->getResultAssoc();
			return $result["value"];
		}
		else
		{
			return (false);
		}
	}
	else
	{
		return (false);
	}
	
}

function createhash()
{
	return(hash('md5', microtime()));
}

function whereis($file_name)
{
    $exec_output = null;
    $exec_return_var = null;

    // Let's find where svn is.
    exec("whereis $file_name", $exec_output, $exec_return_var);

    if ($exec_return_var == 0) // This means that the whereis program is installed on this system.
    {
        if (sizeof($exec_output[0]) > 0) // Only proceed if whereis found a path.
        {
            $tokenized_string_array = explode(":", $exec_output[0]);
            $tokenized_string_array = explode(" ", $tokenized_string_array[1]);
            return $tokenized_string_array[1];  // Usuall the first listing 'whereis' returns is the right on.  [1] is to get rid of the first space..
        }
        else
        {
            echo "FATAL ERROR:  Can't find the " . $file_name . " program.  You may have to create a symlink to it in /bin or /usr/bin.\n";
            return false;
        }
    }
    else
    {
        echo "FATAL ERROR:  The utility 'whereis' is not installed on this system.\n";
        return false;
    }
}

function yesno_map($variable)
{
    // RedBean returns everything as a string so we must compare it as such.
    if ($variable == "0")
    {
        return "no";
    }
    else if ($variable == "1")
    {
        return "yes";
    }
    else if ($variable == "no")
    {
        return 0;
    }  
    else if ($variable == "yes")
    {
        return 1;
    }
    else if ($variable == "n")
    {
        return 0;
    }  
    else if ($variable == "y")
    {
        return 1;
    }
    else
    {
        return $variable;
    }
}

/*
 * If $dipslay_object_or_array is an object, then the $mode should be "edit" since 
 * we are going to edit the contents of one row or a single RedBean object.  If
 * it is an array, then the mode should be "display" and we are going to output each 
 * array entry which should be of type RedBean .
 */
function menu($mode, &$content, $show_or_hide, $show_or_hide_collums_array)
{
    $redbean_object = null;
    
    if (is_object($content) || is_array($content) || is_string($content))  // Simple error checking.
    {
        if (is_object($content))  // This is "edit" mode.
        {
            
            $redbean_object = $content;
            
            if ($mode == "edit")
            {
                $properties = $redbean_object->getProperties();
                $menu_lookup_array = null;
                $i = 1;
                foreach($properties as $key => $value)
                {
                    if ($show_or_hide == "show")
                    {
                        if (in_array($key, $show_or_hide_collums_array)) // These columns are not to be dispalyed or edited.
                        { 
                            echo "\n " . $i . "). " . $key . " = " . yesno_map($value);
                            $menu_lookup_array[$i] = $key;
                            $i++;
                        }
                    }
                    else if ($show_or_hide == "hide")
                    {
                        if (!in_array($key, $show_or_hide_collums_array)) // These columns are not to be dispalyed or edited.
                        { 
                            echo "\n " . $i . "). " . $key . " = " . yesno_map($value);
                            $menu_lookup_array[$i] = $key;
                            $i++;
                        }
                    }
                }
                
                // Print events if they exist.
                // Get all watch events in the many-many lookup table for this watch object.
                $related_events_objects = $redbean_object->sharedEvents;
                
                if (sizeof($related_events_objects) > 0)
                {
                    $menu_lookup_array[$i] = $related_events_objects;
                    echo "\n " . $i . "). events = ";
                    foreach($related_events_objects as $this_event_object)
                    {
                        echo $this_event_object->short_event_name . ", ";
                    }
                }
                
                echo "\n";
                echo "\nEnter number to edit: ";
                $edit_setting = trim(fgets(STDIN));

                while(!key_exists($edit_setting, $menu_lookup_array))
                {
                    echo "Invalid entry.  Try again.";
                    echo "\nEnter number to edit: ";
                    $edit_setting = trim(fgets(STDIN));
                }

                $edit_column = $menu_lookup_array[$edit_setting];
                
                if ((is_array($edit_column))) // Such as an array of Events beans objects.
                {
                    foreach($edit_column as $this_event_bean)
                    {
                        $this_event_bean = array_pop($edit_column);
                        if (is_object($this_event_bean))
                        {
                            array_shift($redbean_object->sharedEvents);  // Remove each of the N:M relations from lookup table events_watches.
                        }
                    }
                    R::store($redbean_object);
                    
                    
                    echo "\nSuccessfully removed watch events.\nEnter new events (seperated by commas (no spaces).  Type \"help\" for a list of supported events).: ";
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
                                $redbean_object->sharedEvents[] = $this_event_lookup;
                            }
                        }
                    }   

                    $edit_watch_id = R::store($redbean_object);
                    
                }
                else  // No objects, simply edit the collumn heading with new value.
                {
                    echo "\nEnter new value for ($edit_column): ";
                    $new_setting = yesno_map(trim(fgets(STDIN)));
                    $date_modified = date("Y\-m\-d G:i:s");
                    $redbean_object->$edit_column = $new_setting;
                    $redbean_object->date_modified = $date_modified;
                    R::store($redbean_object);
                }
                
                return $menu_lookup_array;
            }
            else
            {
                echo "afrs-config::menu() - DEVELOPER ERROR!: Invalid mode supplied.\n";
                return false;
            }
        }
        else if (is_array($content) || is_string($content))  // This is display mode.
        {
            //var_dump($content);
            if ($show_or_hide_collums_array === null)
            {
                $show_or_hide_collums_array = array();
            }
            
            if ($mode == "display")
            {
                $menu_lookup_array = null;
                
                if (is_string($content))  // Must be a custom SQL string.
                {
                    $content = R::getAll($content);
                }
                
                if (is_array($content) && sizeof($content) > 0)  // Array could be what was passed or the result of R::getAll() from passed custom SQL query string.
                {
                    $i = 1;
                    foreach ($content as $this_content)
                    {
                        $output_array = null;
                        echo $i . "). => ";
                        
                        //$menu_lookup_array[$i] = $this_content["id"];

                        foreach($this_content as $key => $value)
                        {
                            if ($key == "id")
                            {
                                $menu_lookup_array[$i] = $value;
                            }
                            if ($show_or_hide == "show")
                            {
                                $position = array_search($key, $show_or_hide_collums_array);  // Print values in the order that was passwd to this function.
                                if (is_int($position))
                                {
                                    if (!is_array($value))  // Why does ->sharedEvents[] show up even when it's called after the loop?  WTF??  This is a hack.
                                    {
                                        $output_array[$position][$key] = $value;
                                    }
                                }
                            }
                            else if ($show_or_hide == "hide")
                            {
                                if (!in_array($key, $show_or_hide_collums_array))
                                {
                                    $output_array[][$key] = $value;
                                }

                            }
                        }

                        foreach($output_array as $this_output)
                        { 
                            $key_name = array_keys($this_output);
                            if (strlen($key_name[0]) <= 5)  // Used to line-up the collumn values when printing to screen.
                            {
                                $tab = "\t\t";
                            }
                            else 
                            {
                                $tab = "\t";
                            }


                            echo "\t" . $key_name[0] . ":" . $tab . yesno_map($this_output[$key_name[0]]) . "\n";
                        }

                        // Print events if they exist.
                        // Get all watch events in the many-many lookup table for this watch object.
                        $related_events_objects = $this_content->sharedEvents;
                        if (sizeof($related_events_objects) > 0)
                        {
                            echo "\tevents = ";
                            foreach($related_events_objects as $this_event_object)
                            {
                                echo $this_event_object->short_event_name . ", ";
                            }
                        }
                        echo "\n";
                        
                        $i++;
                    }
                }
                return $menu_lookup_array;
            }
            else
            {
                echo "afrs-config::menu() - DEVELOPER ERROR!: Invalid mode supplied.\n";
                return false;
            }
        }
    }
    else
    {
        echo "afrs-config::menu() - DEVELOPER ERROR!: Incorrect call to function.  No RedBean object or array of RedBean objects passed.\n";
        return false;
    }
}
?>