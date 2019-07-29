<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DispatchCheckin
 *
 * @author mlee
 */


require_once("Dispatcher.php");

Class DispatchCheckin extends Dispatcher
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
        else if ($parent_result === false)
        {
            if ($this->transaction_obj->hasNewMessages())
            {
                // Let's get to work.
                $message_array = $this->transaction_obj->popNewMessage();
                $this->message_obj = $message_array["message"];
                $this->message_locality = $message_array["locality"];
                
                if ($this->message_locality == "local") // Checking message was generated locally, so just send it to the remote end.
                {
                    //  Message variables have been set but the actual message XML has not been built yet.  
                    //  However, this is done via the Transaction->addMessage() method.
                    return $this->message_obj;                    
                }
                else
                {
                    $clientid = $this->message_obj->getClientID();

                    if ($this->isClientRegistered())
                    {
                            $result = R::findOne("syncpartners", " clientid = ? ", array($clientid));
                            $name = $result->fqdn;
                            $db_sync_partner_private_ip_address = $result->ip_address;
                            $db_sync_partner_public_ip_address = $result->public_ip_address;
                            $sync_partner_public_ip_address = $this->message_obj->getSenderPublicIP();
                            $sync_partner_private_ip_address = $this->message_obj->getSenderPrivateIP();

                            if ($db_sync_partner_private_ip_address != $sync_partner_private_ip_address || $db_sync_partner_public_ip_address != $sync_partner_public_ip_address)  // Update IP address on file since the client's IP has changed from what we currently have in our db.
                            {
                                    echo "Client " . $name . "'s IP address has changed.  Updating our db to reflect the change...";
                                    $result->ip_address = $sync_partner_private_ip_address;
                                    $result->public_ip_address = $sync_partner_public_ip_address;
                                    
                                    try 
                                    {
                                        R::store($result);
                                        echo "Done.\n";
                                    }
                                    catch(Exception $e)
                                    {
                                        echo "ERROR:  Dispatcher::receivedUpdateMemberServerCheckin() - " . $e->getMessage();
                                    }
                            }

                            $result->last_checkin = R::$f->now();
                            $result->status = 1;
                            $result->failed_checkin_count = 0;
                            
                            try
                            {
                                R::store($result);
                                echo "Checkin attempt was successfull for " . $name . "\n";
                                return $this->transaction_obj->addMessage($this->buildReturnAck());
                            }                               
                            catch(Exception $e)
                            {
                                    $return_message = new Message($this->message_obj->getSocket());
                                    $return_message->setCommand("ERROR");
                                    $return_message->setReplyErrorCode("500");
                                    $return_message->setReplyNotes("Internal Server Error - Failed to check you in.  Please try again.");
                                    echo "ERROR:  Dispatcher::receivedUpdateMemberServerCheckin() - Failed to checkin client: " . $clientid .  " (". $name . ").\n Caught exception " . $e->getMessage();
                                    return $this->transaction_obj->addMessage($return_message);
                            }
                    }
                    else
                    {
                            return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
                    }
                }
            }
        }
    }
}

?>
