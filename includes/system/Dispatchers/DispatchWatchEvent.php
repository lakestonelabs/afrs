<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DispatchWatchEvent
 *
 * @author mlee
 */

require_once("Dispatcher.php");

class DispatchWatchEvent extends Dispatcher
{
    public function __construct() 
    {
        parent::__construct();
    }
    
    public function dispatch($transaction_obj)  // Dispatch method must return a Message object or false.
    {
        $parent_result = parent::dispatch($transaction_obj);
        
        if (is_object($parent_result) || $parent_result === null)  // If parent::dispatch() was able to help.
        {
            return $parent_result;
        }
        else if ($parent_result === false)  // Keep processing if parent::dispatch() was not able to help.
        { 
            if ($this->transaction_obj->hasNewMessages())
            {
                // Let's get to work.
                $message_array = $this->transaction_obj->popNewMessage();
                $this->message_obj = $message_array["message"];
                $this->message_locality = $message_array["locality"];
                
                $watch_id = $this->message_obj->getArgumentValue("WATCHID");
                $changed_files_array = $this->message_obj->getChangedFiles();
                $result = false;
                echo "There were " . sizeof($changed_files_array) . " in this watch event.\n";
                foreach ($changed_files_array as $this_changed_file)
                {  
                    $watch_bean = R::dispense("watchesjournal");
                    $watch_bean->watches_id = $watch_id;
                    $watch_bean->date = $this_changed_file["DATETIME"];
                    $watch_bean->file = $this_changed_file["PATH"];
                    $watch_bean->type = $this_changed_file["TYPE"];
                    $watch_bean->event = $this_changed_file["ACTION"];
                    $watch_bean->uid = $this_changed_file["UID"];
                    $watch_bean->gid = $this_changed_file["GID"];
                    $watch_bean->uid_name = $this_changed_file["USER"];
                    $watch_bean->gid_name = $this_changed_file["GROUP"];
                    $watch_bean->posix_permissions = $this_changed_file["POSIXPERMISSIONS"];
                    $watch_bean->size = $size = $this_changed_file["SIZE"];
                    
                    try
                    {
                        $bean_id = R::store($watch_bean);
                        
                        if (!is_int($bean_id))
                        {
                            return false;
                        }
                    }
                    catch(Exception $e)
                    {
                        return false;
                    }
                }
                return true;
            }       
        }
        
    }
}

?>
