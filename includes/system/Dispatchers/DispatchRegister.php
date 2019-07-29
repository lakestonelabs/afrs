<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once("Dispatcher.php");

Class DispatchRegister extends Dispatcher
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
            /* Since we are extending the Dispatch class, we need to remember to pop the message.
             * Remember that Dispatcher::dispatch checks for new messages, pops the new message and then
             * if it determines that it is of no use to us, unpops the new message.  Therefore, we need to 
             * check for new messages and then call the Transaction::popNewMessage() method again.
             */
            
            if ($this->transaction_obj->hasNewMessages())
            {
                echo "At DispatchRegister::dispatch and I have new messages.\n";
                // Let's get to work.
                $message_array = $this->transaction_obj->popNewMessage();
                $this->message_obj = $message_array["message"];
                $this->message_locality = $message_array["locality"];
                $nated = $this->message_obj->isNated();
                if ($nated === true)
                {
                    $nated = 1;  // Need to set numerical values for db insertion.
                }
                else
                {
                    $nated = 0;  // Need to set numerical values for db insertion.
                }

                $register_request_respond_mode = false;  // Used to determine if we are in respond mode to previously received register requests.
                $transaction_id = $this->transaction_obj->getTransactionID();
                $client_key = $this->message_obj->getClientKey();
                $clientid = $this->message_obj->getClientID();
                $last_checkin = date("Y\-m\-d G:i:s");
                $sender_public_ip_address = $this->message_obj->getSenderPublicIP();  // Public IP from XML message from client.
                $sender_private_ip_address = $this->message_obj->getSenderPrivateIP();  // Machine IP from XML message from client.
                $sender_ip_address_from_socket = $this->message_obj->getClientIP();  // IP that the message was received from.

                if ($this->message_obj->getCommand() == "REGISTER")
                {
                    $name = $this->message_obj->getArgumentValue("NAME");
                    $ip_address = $this->message_obj->getArgumentValue("ADDRESS");
                    $port = $this->message_obj->getArgumentValue("PORT");
                    $timezone_offset = $this->message_obj->getArgumentValue("TIMEZONE");
                    $bandwidthup = $this->message_obj->getArgumentValue("BANDWIDTHUP");
                    $bandwidthdown = $this->message_obj->getArgumentValue("BANDWIDTHDOWN");
                    $afrs_version = $this->message_obj->getArgumentValue("AFRSVERSION");
                    $priority = $this->message_obj->getArgumentValue("PRIORITY");
                }

                if ($this->transaction_obj->isProxied())
                {
                    echo "This message is proxied.\n";
                    if ($this->message_obj->getCommand() == "REGISTER")  
                    {
                        $result_bean = R::find("syncpartnerregisterrequests", " fqdn = ? " , array($name));
                        if (sizeof($result_bean) == 1)
                        {
                            // We need to respond to a pending register request to finalize it.
                            $remote_address = $this->message_obj->getArgumentValue("ADDRESS");
                            $remote_port = $this->message_obj->getArgumentValue("PORT");

                            $response_message_obj = new Message($remote_address . ":" . $remote_port);
                            $response_message_obj->setCommand("REGISTER");
                            $response_message_obj->setArgumentValue("ADDRESS", $this->local_ip_address);
                            $response_message_obj->setArgumentValue("PORT", $this->daemon_port_number);
                            $response_message_obj->setArgumentValue("NAME", $this->local_fqdn);
                            $response_message_obj->setArgumentValue("TIMEZONE", -7);
                            $response_message_obj->setArgumentValue("BANDWIDTHUP", 0);
                            $response_message_obj->setArgumentValue("BANDWIDTHDOWN", 0);
                            $response_message_obj->setArgumentValue("AFRSVERSION", 0);
                            $response_message_obj->setArgumentValue("PRIORITY", "medium");

                            return $this->transaction_obj->addMessage($response_message_obj);

                            //  Do the remaining shit. I.E. enter the sync partners info into the sync partners table, etc.
                        }
                        else if (sizeof($result_bean) > 1)
                        {
                            throw new Exception("DispatchRegister::dispatch() - Duplicate client db entries exist for registering system.  Does db have a unique key constraint?");
                        }
                        else
                        {
                            // Insert the proxiable's remote side information into the db before we transport it for proxying.
                            // Note that we are not including many of the tables fields here since we don't know these values 
                            // until we get a response back from the remote part and then we can get such things as the clientid, 
                            // key, etc. from it's message headers.
                            //$this->dispatch_query->runQuery("insert into tbl_sync_partner_register_requests (
                            //    date_requested, ip_address, public_ip_address, fqdn, initiator, transaction_id)
                            //                           values('$last_checkin', '$ip_address', '$ip_address', '$name', 1, '$transaction_id')");

                            $result_bean = R::dispense("syncpartnerregisterrequests");
                            $result_bean->date_requested = $last_checkin;
                            $result_bean->ip_address = $ip_address;
                            $result_bean->public_ip_address = $ip_address;
                            $result_bean->fqdn = $name;
                            $result_bean->initiator = 1;
                            $result_bean->transaction_id = $transaction_id;
                            R::store($result_bean);
                            
                            // Since this is a proxied message, we need to change them.
                            // For example, the ADDRESS argument passed to us for this
                            // proxied message is the address of the remote server to which we want to register.  Therefore, we
                            // will extract this so that we know what server to send the message to (i.e. create a new socket)
                            // and then we will create a new REGISTER message with the ADDRESS parameter set to our local IP so
                            // the the remote end can process our REGISTER request.

                            $proxied_message_obj = new Message($this->message_obj->getArgumentValue("ADDRESS").":".$this->message_obj->getArgumentValue("PORT"));
                            $proxied_message_obj->setCommand("REGISTER");
                            $proxied_message_obj->setArgumentValue("ADDRESS", $this->local_ip_address);
                            $proxied_message_obj->setArgumentValue("PORT", $this->daemon_port_number);
                            $proxied_message_obj->setArgumentValue("NAME", $this->local_fqdn);
                            $proxied_message_obj->setArgumentValue("TIMEZONE", $this->timezone);
                            $proxied_message_obj->setArgumentValue("BANDWIDTHUP", $bandwidthup);
                            $proxied_message_obj->setArgumentValue("BANDWIDTHDOWN", $bandwidthdown);
                            $proxied_message_obj->setArgumentValue("AFRSVERSION", $this->afrs_version);
                            $proxied_message_obj->setArgumentValue("PRIORITY", $priority);

                            // NOTE:  We don't call Transaction::buildAndAddTransmitMessage() since we don't want add a message that is already
                            //		present for this transaction.  Also we don't call the addMessage() method because doing so would
                            //		prematurely increment the sequence number which would kill the transaction since the remote side would
                            //		percieve this as a spoofed message.
                            return $this->transaction_obj->addMessage($proxied_message_obj);
                        }  
                    }
                    else if ($this->message_obj->getCommand() == "ACK" && $this->transaction_obj->getPreviousMessageCommand() == "REGISTER")
                    {
                        /* 
                         * First determine which side we are, the initiator or the receiver (client/server).
                         */
                        $result_bean = R::find("syncpartnerregisterrequests", " transaction_id = ? and initiator = ? ", array($transaction_id, 1));
                        if (sizeof($result_bean) == 1)  // We were the initiator of the initial REGISTER message.
                        {
                            echo "Updating db with client ID...\n";
                            $result_bean = array_pop($result_bean);  // When using R::find the result is an array of objects.
                            $result_bean->clientid = $clientid;
                            
                            echo $result_bean->fqdn . " queued for potential registration (" . $sender_public_ip_address . ") as a member sync server.\n";
                            R::store($result_bean);
                            
                            $return_message = new Message($this->message_obj->getSocket());
                            $return_message->setCommand($this->calculateResponseCommand());  // Should be a BYE.
                            return $this->transaction_obj->addMessage($return_message);
                            
                        }
                        else if (sizeof($result_bean) == 0)
                        {
                            $result_bean = R::find("syncpartnerregisterrequests", " clientid = ? and initiator = ? ", array($clientid, 2));
                            if (sizeof($result_bean) == 1)  // We are completing the handshake of a remotely initiated REGISTER request.
                            {
                                // The register process is complete for this remote host.  Therefore, remove the entry from the
                                // sync_parter_register_requests table and enter the appropriate information into the sync_partners table.
                                $new_sync_partner_bean = R::dispense("syncpartners");
                                
                                $result_bean = array_pop($result_bean);
                                /*
                                 * Below we create a new entry in the syncpartners table with values from the syncpartnerregisterrequests 
                                 * table using an sql nested select statement.  However, since we ar using RedBean and we already have the 
                                 * data in the $result_bean, we can just transfer the variables to the $new_sync_partner_bean.
                                 */
                                $new_sync_partner_bean->clientid = $result_bean->clientid;
                                $new_sync_partner_bean->date_added = $last_checkin;
                                $new_sync_partner_bean->ip_address = $result_bean->ip_address;
                                $new_sync_partner_bean->public_ip_address = $result_bean->public_ip_address;
                                $new_sync_partner_bean->port_number = $result_bean->port_number;
                                $new_sync_partner_bean->fqdn = $result_bean->fqdn;
                                $new_sync_partner_bean->is_nated = $result_bean->is_nated;
                                $new_sync_partner_bean->partner_public_key = $result_bean->partner_public_key;
                                $new_sync_partner_bean->timezone_offset = $result_bean->timezone_offset;
                                $new_sync_partner_bean->status = 1;
                                $new_sync_partner_bean->last_checkin = $last_checkin;
                                $new_sync_partner_bean->failed_checkin_count = 0;
                                $new_sync_partner_bean->afrs_version = $result_bean->afrs_version;
                                $new_sync_partner_bean->bandwidth_up = $result_bean->bandwidth_up;
                                $new_sync_partner_bean->bandwidth_down = $result_bean->bandwidth_down;
                                $new_sync_partner_bean->sync_bandwidth = $result_bean->sync_bandwidth;
                                $new_sync_partner_bean->priority = $result_bean->priority;
                                $bean_id = R::store($new_sync_partner_bean);
                                
                                if (is_int($bean_id))  // Only proceed if the bean saved successfully to the db.
                                {
                                    // Remove the db entry from the syncpartnerregisterrequests table.
                                    R::trash($result_bean);  // trash always returns null so no way to test if the delete sql operation completed.
                                    $return_message = new Message($this->message_obj->getSocket());
                                    $return_message->setCommand($this->calculateResponseCommand());
                                    return $this->transaction_obj->addMessage($return_message);
                                }
                            }
                        }
                    }
                } 
                else  // Message is not to be proxied.  Process it since this message came from the remote end.
                {
                        if (!$this->isClientRegistered($clientid))  // Only proceed if the clientid does not already exist in the syn_partners table.
                        {
                            echo "DispatchRegister::dispatch() - Client is not registered.\n";
                            if ($this->command == "REGISTER")
                            {
                                $result_bean = R::find("syncpartners", " public_ip_address = ? and ip_address = ? and is_nated = ? ", array($sender_public_ip_address, $sender_private_ip_address, $nated));
                                
                                if (sizeof($result_bean) == 0)  // We can add the new sync partner since it does not conflict with any others.
                                {
                                    echo "Client is not currently one of our sync partners,\n";
                                    // First check to see if the remotely generated register command is actually an answer to one 
                                    // of our register requests.

                                    $result_bean = R::find("syncpartnerregisterrequests", " clientid = ? ", array($clientid));

                                    if (sizeof($result_bean) == 1)  // There should only be one result.  // TODO.  Make clientid for this table unique or primary key.
                                    {
                                        echo "This is a response to one of our pervious REGISTER requests.\n";
                                        // The register process is complete for this remote host.  Therefore, remove the entry from the
                                        // sync_parter_register_requests table and enter the appropriate information into the sync_partners table.
                                        $new_sync_partner_bean = R::dispense("syncpartners");
                                        $new_sync_partner_bean->clientid = $clientid;
                                        $new_sync_partner_bean->date_added  = $last_checkin;
                                        $new_sync_partner_bean->ip_address = $sender_private_ip_address;
                                        $new_sync_partner_bean->public_ip_address = $sender_public_ip_address;
                                        $new_sync_partner_bean->port_number = $port;
                                        $new_sync_partner_bean->fqdn = $name;
                                        $new_sync_partner_bean->is_nated = $nated;
                                        $new_sync_partner_bean->partner_public_key = $client_key;
                                        $new_sync_partner_bean->timezone_offset = $timezone_offset;
                                        $new_sync_partner_bean->status = 1;
                                        $new_sync_partner_bean->last_checkin = $last_checkin;
                                        $new_sync_partner_bean->failed_checkin_count = 0;
                                        $new_sync_partner_bean->afrs_version = $afrs_version;
                                        $new_sync_partner_bean->bandwidth_up = $bandwidthup;
                                        $new_sync_partner_bean->bandwidth_down = $bandwidthdown;
                                        $new_sync_partner_bean->priority = $priority;
                                        $bean_id = R::store($new_sync_partner_bean);  
                                        
                                        if (is_int($bean_id))
                                        {
                                            R::trash(array_pop($result_bean));
                                            $return_message = new Message($this->message_obj->getSocket());
                                            return $this->transaction_obj->addMessage($this->buildReturnAck());
                                        }
                                    }
                                    else if (sizeof($result_bean) == 0)
                                    {
                                        echo "Creating an new bean.\n";
                                        $new_sync_request_bean = R::dispense("syncpartnerregisterrequests");
                                        $new_sync_request_bean->clientid = $clientid;
                                        $new_sync_request_bean->date_requested = $last_checkin;
                                        $new_sync_request_bean->ip_address = $sender_private_ip_address;
                                        $new_sync_request_bean->public_ip_address = $sender_public_ip_address;
                                        $new_sync_request_bean->fqdn = $name;
                                        $new_sync_request_bean->is_nated = $nated;
                                        $new_sync_request_bean->partner_public_key = $client_key;
                                        $new_sync_request_bean->timezone_offset = $timezone_offset;
                                        $new_sync_request_bean->afrs_version = $afrs_version;
                                        $new_sync_request_bean->bandwidth_up = $bandwidthup;
                                        $new_sync_request_bean->bandwidth_down = $bandwidthdown;
                                        $new_sync_request_bean->priority = $priority;
                                        $new_sync_request_bean->initiator = 2;
                                        $new_sync_request_bean->transaction_id = $transaction_id;
                                        $bean_id = R::store($new_sync_request_bean);
                                        
                                        if(is_int($bean_id))
                                        {
                                            echo $name . " queued for potential registration (" . $sender_public_ip_address . ") as a member sync server.\n";
                                            return $this->transaction_obj->addMessage($this->buildReturnAck());
                                        }
                                        else
                                        {
                                            echo "Bean return is not an int, it's " . $bean_id . "\n";
                                        }
                                    }
                                }
                                else if ($this->dispatch_query->getResultSize() == 1)
                                {
                                    // Send the offending remote sync partner info for troubleshooting purposes back to the user.
                                    echo "ERROR:  Dispatcher::receivedRegisterMemberServer() - Remote client private & public IP address conflict.  Maybe an old sync partner " 
                                    . $result_bean->fqdn . " needs to be removed from your sync partners list.  Please fix before trying again.\n";
                                    $return_message = new Message($this->message_obj->getSocket());
                                    $return_message->setCommand("ERROR");
                                    $return_message->setReplyErrorCode("506");
                                    $return_message->setReplyNotes("A current sync partner client with the name of " . $result_bean->fqdn
                                    . " is already using private/public ip address combo of " . $result_bean->ip_address . $result_bean->public_ip_address
                                    . ".  Remote end needs to resolve this before you can continue.\n");
                                }
                                else
                                {
                                        echo "ERROR:  (Dispatcher) Failure registering client " . $sender_public_ip_address . "\n";
                                        $return_message = new Message($this->message_obj->getSocket());
                                        $return_message->setCommand("ERROR");
                                        $return_message->setReplyErrorCode("506");
                                        $return_message->setReplyNotes("Registration Failed.");
                                        return $this->transaction_obj->addMessage($return_message);
                                }
                            }
                            else if ($this->command == "ACK" && $this->transaction_obj->getPreviousMessageCommand() == "REGISTER")
                            {
                                if ($this->transaction_obj->isProxiable())  // ACK was a proxied response from the local server.
                                {
                                    $return_message = new Message($this->message_obj->getSocket());
                                    $return_message->setCommand($this->calculateResponseCommand());  // Should be a BYE.
                                    return $this->transaction_obj->addMessage($return_message);
                                }
                                else  // ACK was from remote end.
                                {
                                    $result_bean = R::find("syncpartnerregisterrequests", " transaction_id = ? and initiator = 2 ", array($transaction_id));
                                    
                                    if (sizeof($result_bean) == 1)  // Remote initiated the initial REGISTER command.
                                    {
                                        $new_sync_partner_bean = R::dispense("syncpartners");
                                        $new_sync_partner_bean->clientid = $clientid;
                                        $new_sync_partner_bean->date_added = $last_checkin;
                                        $new_sync_partner_bean->ip_address = $sender_private_ip_address;
                                        $new_sync_partner_bean->public_ip_address = $sender_public_ip_address;
                                        $new_sync_partner_bean->port_number = $port;
                                        $new_sync_partner_bean->fqdn = $name;
                                        $new_sync_partner_bean->is_nated = $nated;
                                        $new_sync_partner_bean->partner_public_key = $client_key;
                                        $new_sync_partner_bean->timezone_offset = $timezone_offset;
                                        $new_sync_partner_bean->status = 1;
                                        $new_sync_partner_bean->last_checkin = $last_checkin;
                                        $new_sync_partner_bean->failed_checkin_count = 0;
                                        $new_sync_partner_bean->afrs_version = $afrs_version;
                                        $new_sync_partner_bean->bandwidth_up = $bandwidthup;
                                        $new_sync_partner_bean->bandwidth_down = $bandwidthdown;
                                        $new_sync_partner_bean->priority = $priority;
                                        $bean_id = R::store($new_sync_partner_bean);    
                                        
                                        if (is_int($bean_id))
                                        {
                                            R::trash(R::findOne("syncpartnerregisterrequests", " clientid = ? ", array($clientid)));
                                            $return_message = new Message($this->message_obj->getSocket());
                                            $return_message->setCommand($this->calculateResponseCommand());  // Should be a BYE.
                                            return $this->transaction_obj->addMessage($return_message);
                                        }
                                    }
                                }       
                            }                            
                        }
                        else
                        {
                                echo "ERROR: (Dispatcher) Failure registering client.  Client ID: " . $clientid . " already exists in sync_partners table.\n";
                                $return_message = new Message($this->message_obj->getSocket());
                                $return_message->setCommand("ERROR");
                                $return_message->setReplyErrorCode("506");
                                $return_message->setReplyNotes("Registration Failed - You are already registered with this server.");
                                return $this->transaction_obj->addMessage($return_message);
                        }
                }
            }
        }
    }
}
?>
