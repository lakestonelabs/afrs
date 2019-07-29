<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DispatchGetShares
 *
 * @author mlee
 */

require_once("Dispatcher.php");

class DispatchGetShares
{
    public function __construct() 
    {
        parent::__construct();
    }
    
    public function dispatch($transaction_obj)  // Dispatch method must return a Message object or false.
    {       										// Completed on:  4/25/2010
        $parent_result = parent::dispatch($transaction_obj);
        if (is_object($parent_result) || $parent_result === null)  // If parent::dispatch() was able to help.
        {
            return $parent_result;
        }
        else if ($parent_result === false)  // Keep processing if parent::dispatch() was not able to help.
        {
            $clientid = $this->message_obj->getClientID();
            
            if ($this->isClientRegistered($clientid))  // Make sure the client is registered with this server before going further.
            {
                    // Get a list of shares from this server to send to the client.
                    $this->dispatch_query->runQuery("select share_name, size, available_size, active, permission, creation_date
                                                                                     from tbl_shares 
                                                                                     where active = '1'");
                    $shares_beans_array = R::find("shares", " active = 1 ");
                    $shares_count = sizeof($shares_beans_array);
                    if ($shares_count >= 0)
                    {
                            //echo "I found " . $result_count . " shares for this server.\n";
                            $return_message = new Message($this->message_obj->getSocket());
                            $return_message->setCommand("PRESENTSHARES");
                            $return_message->setArgumentValue("SHARECOUNT", $shares_count);

                            if ($shares_count > 0)
                            {
                                // The below will create a sub associative array within the  message's argument associative array.
                                //for($i = 1; $i <= $shares_count; $i++)
                                foreach($shares_beans_array as $this_share_bean)
                                {
                                        $this_share_values =  array("SIZE" => $this_share_bean->size,
                                                   "FREESIZE" => $this_share_bean->freesize,
                                                   "ACTIVE" => $this_share_bean->active,
                                                   "GLOBALPERMISSION" => $this_share_bean->global_permission,
                                                   "CREATIONDATE" => $this_share_bean->creation_date);
                                        $return_message->setShareValues($this_share_bean->name, $this_share_values); // This will make the message's argument array a 2D associative array.
                                }
                            }
                            return $this->transaction_obj->addMessage($return_message);
                    }
                    else
                    {
                            echo "ERROR:  Can't retrieve available shares for this local server.  Returning an error to requesting client.\n";
                            $return_message = new Message($this->message_obj->getSocket());
                            $return_message->setCommand("ERROR");
                            $return_message->setReplyErrorCode("500");
                            $return_message->setReplyNotes("Internal Server Error - Failed to retrieve available shares.  Please try again later.  If the problem persist, please contact the administrator of the remote machine.n");
                            echo "ERROR:  Dispatcher::receivedGetShares() - Can't retrieve available shares for this local server.  Returning an error to requesting client.\n";
                            return $this->transaction_obj->addMessage($return_message);
                    }
            }
            else
            {	// TODO:  Need to use a better error return system.
                    echo "ERROR: Client " . $name . " is not a registered member server.  Can't give it a list of sync shares\n";
                    return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
            }            
        }
    }
}

?>