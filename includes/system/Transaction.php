<?php
/*
 * This class is simply used to keep track of an on-going conversation or to create a new one.
 * It also servers to determine if a response is valid to a previously issued command.  If not
 * return an error to the client.  
 * 
 * A Transaction holds an array of message objects.  When a message object is received, it is 
 * processed to determine if it is in sequence and is a valid command/response.  If so it returns
 * true to the AFRS Deamon so that it can dispatch the message accordingly.
 * 
 * TODO:  Actually the only thing this class needs to determine if it's valid is its transaction_id, 
 * 		  sequence number and most importantly, command.  In the future we may be able to reduce 
 * 		  processing load by only passing these values to this class.
 */

require_once(__DIR__."/../functions.php");
require_once(__DIR__."/Messages/Message.php");

Class Transaction
{
	private                 $local_sid = null,
                                $transaction_id = null,
				$is_proxied = false,	// Used to defind if this transaction is the actual proxied transaction to the remote server.
				$is_proxiable = false,	// Used to define if this transaction wants to be proxied by this server.
				$proxy_parent_transaction_id = null,  // (Child transaction will have this set) This will hold the transaction ID of the parent proxied transaction.
				$proxy_child_transaction_id = null,   // (Parent transaction will have this set) This will hold the transaction ID of the child proxy transaction.
				$transaction_message_array = null,  // An array of message objects contain the history of a conversation/transaction.
				$linked_transaction_object = null,  // This is a reference (sort of a pointer) to the proxied_response
                                                                              // variable in the parent transaction.  When a transaction is linked to another 
                                                                              // transaction, this will point to the memory location of the $proxied_response_message_obj
                                                                              // variable for the parent transaction.
                                $proxied_response_message_obj = null,
                                $trans_db_conn = null,
				$trans_query = null,
				$global_session_expires = null,
				$last_seq_number = null,
				$next_message_pointer = 0,  // Holds the position of the next message to be processed for the transaction.
				$new_message_count = 0,
				//$pending_on_transaction_id = null,  // The transaction id that we are waiting on to complete before this transaction can resume.
				$pending_messages = null;  // An array of index locations of messages that are pending responses.  For instance, REQUESTSYNC would initially be pending since it generates a GETSHARE command from the receiving side.
	                        
                
	function __construct($message_object)  // If args = 2 & 2nd arg is "proxy" create a proxied transaction.
	{
		if (is_object($message_object))
		{
			require(__DIR__."/../../conf/afrs_vars.php");  // Variables are out of scope if outside a function and outside a class declaration in Classes.
			require(__DIR__."/../../conf/afrs_config.php");
			
                        $this->global_session_expires = $session_expires;  // $session_expires variable comes from the above included afrs_vars.php file.
                        $this->sid = $sid;
			$transaction_id = $message_object->getTransactionID();
			$sequence_number = $message_object->getSequenceNumber();
                        
			if (($transaction_id == null && $sequence_number == null) || (func_num_args() == 2 && func_get_arg(1) == "proxy"))  // A new transaction started by us, not a remote client.
			{
				// Need to create a new transaciton ID.
				$this->transaction_id = hash('md5', microtime());
				
				if (func_num_args() == 2 && func_get_arg(1) == "proxy")
				{
					$this->is_proxied = true;
					echo "Created a new proxied transaction (initiated by local) with an id of: " . $this->transaction_id . "\n";
				}
				else
				{
					echo "Created a transaction (initiated by local) with an id of: " . $this->transaction_id . "\n";
				}

				$message_object->setSequenceNumber(1);
				$this->last_seq_number = 1;
				$message_object->setTransactionID($this->transaction_id);
                                
                                if ($message_object->getRawMessage() == null)
                                {
                                    $message_object->buildTransmitMessage();
                                }
                                
				$this->transaction_message_array[0] = array("message" => $message_object, "locality" => "local", "process" => "yes");
				$this->new_message_count++;
				$this->is_paused = false;  // This transaction is not in a paused state.
			}
			else if ($transaction_id !=null && $sequence_number == 1)  // A new transaction started by remote end or a message from local that is to be proxied.
			{
				$this->transaction_id = $transaction_id;  // Get the transaction id created by the remote client.

				if ($message_object->getClientID() == $sid && $message_object->getCommand() != "WATCHEVENT") // This entire transaction is to be proxied. Since the message was created locally.
				{																		 // Don't proxy watch events though.
					$this->is_proxiable = true;
					echo "Created a new proxiable transaction (initiated by watcher/config) with an id of: " . $this->transaction_id . "\n";
				}
				else
				{
					echo "Created a new transaction (initiated by remote) with an id of: " . $this->transaction_id . "\n";
				}
				
				$this->transaction_message_array[] = array("message" => $message_object, "locality" => "remote", "process" => "yes");
				$this->last_seq_number = $sequence_number;
				$this->new_message_count++;
			}
			else
			{
				echo "ERROR:  Transaction::__construct().  Invalid call to constructor.\n";
			}
		}
		else
		{
			// TODO: Report error.
			echo "// TODO: Report error.\n";
		}
	}
	
	function __destruct()
	{
		// TODO:	Need to do more check, such as destroy any processes associated with this
		//			transaction, etc. i.e.  Destroy any rsync processes so they don't become zombies.
		echo "Transaction::__desctruct() called.\n";
                echo "## Transaction " . $this->transaction_id . " destroyed. ##\n";
	}
	
        /*
         * addMessage()
         * @param (object) (message_object)  Contains the message object.
         * @return (mixed) 
         */
	function addMessage($message_object)
	{
            $num_args = func_num_args();  /* If 1, then build the reply message in lockstop.
					  *  If 2, build the reply message in accordance to the second arg (message_object).
					  *  2 usually applies to replying to messages that have been queued.
                                          *  or if the 2nd argument is "proxied_response" then it a proxied response message to the parent transaction. 
                                          */ 
            // First determine if this message was from remote and built internally and meant to be sent to the remote end.
            if ($message_object->getTransactionID() == null)  // Message was built internally.
            {  
		if ($num_args == 1)
		{
                    $message_object->setTransactionID($this->transaction_id); // Set the transcation id of the message obj equal to this current transaction.
                    $message_command = $message_object->getCommand();

                    if ($this->isProxied() && $this->getLastSequenceNumber() == 1)
                    {
                        // Since this transaction is proxied, i.e. we are sending this communcation to a remote end on behalf of
                        // a local client, we have to essentially rewind the sequence number to 1 since the message we just
                        // received is acutally the first message to be communicated to the remote end.
                        $this->transaction_message_array[0]["message"] = $message_object;
                        $message_object->setSequenceNumber(1);
                    }
                    else
                    {
                        $message_object->setReplyToCommand($this->getNewestMessage()->getCommand());
                        $message_object->setReplyToSequenceNumber($this->last_seq_number);
                        $message_object->setSequenceNumber($this->last_seq_number + 1);
                    }

                    $message_object->buildTransmitMessage();  // This is an important step here.
                    $seq_num = $message_object->getSequenceNumber();

                    if ($message_command == "QUEUED")
                    {
                        $this->pending_messages[] = $this->getIndex($last_message_object);  // i.e. This would be the REQUESTSNC's index if our QUEUED message was a reply to the remote's REQUESTSYNC.
                    }

                    // If trans is proxied and this message is the first message to be sent to the remote end then
                    // simply set the last sequence number var for this trans and return the message.
                    if ($this->isProxied() && $this->getSize() == 1)
                    { 
                        $this->last_seq_number = $seq_num;
                        return ($message_object);
                    }
                    else
                    {
                        if ($seq_num == ($this->last_seq_number + 1)) // Make sure that the received message is in sequence.
                        {                                                                                      // message object as the first operation in this method.
                            $this->transaction_message_array[] = array("message" => $message_object, "locality" => "local", "process" => "no");  // Store the message for this transaction.
                            $this->last_seq_number = $seq_num;
                            return ($message_object);
                        }
                        else
                        {
                            return (false);
                        }
                    }
		}
		else if ($num_args == 2)  // Usually if we are replying to a command that was previously queued.
		{
                    $message_object = func_get_arg(1);
                    $queued_message_object = func_get_arg(2);

                    $message_object->setTransactionID($this->transaction_id);
                    $message_command = $message_object->getCommand();
                    //$last_message_object = $this->getNewestMessage();
                    $queued_message_command = $queued_message_object->getCommand();
                    $message_object->setReplyToCommand($queued_message_command);
                    $message_object->setReplyToSequenceNumber($this->last_seq_number);
                    $message_object->setSequenceNumber($this->last_seq_number + 1);  // Set the message object seq_num 
                                                                                                                                                     // based off of the last message's
                                                                                                                                                     // seq_number.
                    $message_object->buildTransmitMessage();  // This is an important step here.
                    $seq_num = $message_object->getSequenceNumber();
                    return $message_object;
		}
            }
            else if ($message_object->getTransactionID() != null)  // Message was built remotely or a proxied repsonse.
            {
                if ($message_object->getSequenceNumber() == ($this->last_seq_number + 1)) 
		{
                    if ($this->isLinked() && $this->getLinkedRelationship() == "parent" && $num_args == 2 && func_get_arg(1) == "proxied_response")
                    {
                        $this->transaction_message_array[] = array("message" => $message_object, "locality" => "proxied_response", "process" => "yes");
                    }
                    else
                    {
                        $this->transaction_message_array[] = array("message" => $message_object, "locality" => "remote", "process" => "yes");  // Store the message and the type for this transaction.
                    }
                    
                    $this->last_seq_number = $message_object->getSequenceNumber();
                    $this->new_message_count++;
                    return true;
		}
		else // Return the error in the form of a Message object so we can send the error back to the client.
		{
			$message_command = $message_object->getCommand();
			$this->transaction_message_array[] = array("message" => $message_object, "locality" => "remote", "process" => "no");  // Store the offending message in the transaction but don't increment the new_message_count for processing.
			$return_message = new Message($message_object->getSocket());
			$return_message->setTransactionID($this->transaction_id);
			$return_message->setSequenceNumber($message_object->getSequenceNumber() + 1);  // Even though this message is out of sequence, use the remote ends sequence numbering so it can process the message as an error.
			$return_message->setCommand("ERROR");
			$return_message->setReplyToCommand($message_command);
			$return_message->setReplyToSequenceNumber($message_object->getSequenceNumber());
			$return_message->setReplyErrorCode("412");
			$return_message->setReplyNotes("Out of Sequence.  Expecting " . ($this->last_seq_number + 1) . ", got " . $message_object->getSequenceNumber() . "\n");
			$return_message->buildTransmitMessage();
			$this->transaction_message_array[] = array("message" => $return_message, "locality" => "local", "process" => "no");
			echo "ERROR: Transaction::addMessage().  Message received is out of sequence for transaction: " . $this->transaction_id . ".  Expecting " . ($this->last_seq_number + 1) . ", got " . $message_object->getSequenceNumber() . "\n";
			return ($return_message);
		}
            }
	}
	
	function getIndex($message_object)  // NOTE:  The index location of the message is not the same number as the message's sequence number.  Just an FYI.
	{
		$index = null;
		$seq_num = $message_object->getSequenceNumber();
		$transaction_size = sizeof($this->transaction_message_array);
		for ($i = 0; $i < sizeof($transaction_size); $i++)
		{
			$loop_message_obj = $this->transaction_message_array[$i];
			// Maybe in the future we should also check to see if the message commands match.
			if ($loop_message_obj["message"]->getSequenceNumber() == $seq_num)
			{
				$index = $i;
				break;
			}
		}
		return $index;
	}
	
	function getLastSequenceNumber()
	{
		return $this->last_seq_number;
	}

	function getLinkedRelationship()  // Returns if this transaction is a parent or child of it's related transaction.
	{
		if ($this->isLinked())
		{
			if ($this->proxy_child_transaction_id != null)
			{
				return ("parent");
			}
			else if ($this->proxy_parent_transaction_id != null)
			{
				return ("child");
			}
		}
		else
		{
			return false;
		}
	}

	function getLinkedTransactionID()
	{
		if ($this->proxy_child_transaction_id != null)
		{
			return $this->proxy_child_transaction_id;
		}
		else if ($this->proxy_parent_transaction_id != null)
		{
			return $this->proxy_parent_transaction_id;
		}
		else
		{
			return false;
		}
	}
	
	function getMessageLocality($message_object)  // Returns if this message is of type "remote" or "local".
	{
		foreach($this->transaction_message_array as $this_message)
		{
			if ($message_object === $this_messsage["message"])
			{
				return $this_message["locality"];
			}
		}
		return false;	// Return false if the message was not found in this transaction.
	}
        
        private function getMessageLocation($messabe_object)  // Returns the location of the message in the transaction array.
	{
                $sequence_number = $messabe_object->getSequenceNumber();
                for($i = 0; $i < sizeof($this->transaction_message_array); $i++)
                {
                    if ($this->transaction_message_array[$i]["message"]->getSequenceNumber() == $sequence_number)
                    {
                            return $i;
                    }
                }
                // If we have gotten this far, then we did not find any message with sequence number of $sequence_number.
                return false;
        }
        
	function getNewestMessage()
	{
		return $this->transaction_message_array[(sizeof($this->transaction_message_array)-1)]["message"];
	}
	
	function getNewestMessageCommand()
	{
		$newest_message_object = $this->getNewestMessage();
		return $newest_message_object->getCommand();
	}
	
	function getPendingMessageCount()
	{
		return sizeof($this->pending_messages);
	}
	
	function getPreviousMessage()
	{
		if (sizeof($this->transaction_message_array > 1))
		{
			// The below -2 is to compensate for the array index starting at 0 and then rewinding 1, therefore -2.
			return $this->transaction_message_array[(sizeof($this->transaction_message_array)-2)]["message"];
		}
		else if (sizeof($this->transaction_message_array) === 0)
		{
			return null;  // If we only have one message in the transaction, then there is no previous message.
		}
		else
		{
			return false;
		}
	}
	
	function getPreviousMessageCommand()
	{
		$newest_message_object = $this->getPreviousMessage();
		if (is_object($newest_message_object))  // This can return either null if transaction size is 0 or false if error.
		{
			return $newest_message_object->getCommand();
		}
		else
		{
			return false;
		}
	}
	
	function getPreviousMessageErrorCode()
	{
		$newest_message_object = $this->getPreviousMessage();
		return $newest_message_object->getErrorCode();
	}
	
	function getMessageAtLocation($location_index)
	{
		return $this->transaction_message_array[$location_index]["message"];
	}
	
	function getMessageOfSequenceNumber($sequence_number)
	{
		foreach ($this->transaction_message_array as $this_message_object)
		{
			//echo "COMPARING: " . $this_message_object["message"]->getSequenceNumber() . "\n";
			//echo "FOR COMMAND: " . $this_message_object["message"]->getCommand() . "\n";
			if ($this_message_object["message"]->getSequenceNumber() == $sequence_number)
			{
				return $this_message_object["message"];
			}
		}
		// If we have gotten this far, then we did not find any message with sequence number of $sequence_number.
		return false;
	}
	
	public function getNewestMessageSeqNumber()  // Returns the sequence number of the newest message for this transaction.
	{
		return $this->getNewestMessage()->getSequenceNumber();
	}
	
	function getSize()
	{
		return (sizeof($this->transaction_message_array));
	}
	
	function getTransactionID()
	{
		return $this->transaction_id;
	}
	
	function hasNewMessages()  // Used to determine if this transaction has any new messages to process.
	{
		if ($this->new_message_count > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function hasPendingMessages()
	{
		if (sizeof($this->pending_messages) > 0)
		{
			return (true);
		}
		else
		{
			return (false);
		}
	}

	function isLinked()
	{
		if ($this->proxy_parent_transaction_id != null || $this->proxy_child_transaction_id != null)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/*function unLink()
	{
		$this->proxy_parent_transaction_id = null;
		$this->proxy_child_transaction_id = null;
	}*/

	// Used to check if the transaction was requested to become a proxied message.  Not the actual proxied transaction.
	function isProxiable()
	{
		return $this->is_proxiable;
	}

	// Used to check if this transaction is to be proxied by this server and communicated to the remote server.
	function isProxied()
	{
		return $this->is_proxied;
	}

	// This is mainly used for proxied transactions so we can give treatment to the parent transaction after the child
	// communication with the remote side has finished.
	//function linkTransaction($transaction_id, $its_relationship)
        function linkTransaction(&$transaction_obj, $its_relationship)
	{
                $this->linked_transaction_object = $transaction_obj;  // THis is an object reference, not a copy of the link transaction object.
		if ($its_relationship == "parent")
		{
			$this->proxy_parent_transaction_id = $transaction_obj->getTransactionID();
		}
		else if ($its_relationship == "child")
		{
			$this->proxy_child_transaction_id = $transaction_obj->getTransactionID();
		}
		else
		{
			return false;
		}
	}
	
	function markMessagePending($message_object)
	{
		$this->pending_messages[] = $this->getIndex($message_object);
		return true;
	}
	
	function popNewMessage()  // Returns new unprocessed messages that we received from the remote end.
	{
		if ($this->hasNewMessages())  // Double check to see if we are beeing call in a valid way.
		{
			
			// Search for the next message in the transaction that is from remote.  This will become the next message for us to process.
			// Remember, we do this so we don't process our own messages in the transaction queue that were generated locally by us 
			// as a response to the remote end.
			for($i = $this->next_message_pointer; $i <= sizeof($this->transaction_message_array); $i++)
			{
				if ($this->transaction_message_array[$i]["process"] == "yes")
				{
					//$return_message = $this->transaction_message_array[$i]["message"];  // Get the message passed to us by remote.
					$this->next_message_pointer = $i + 1; // Increment so we don't get stuck in one place, even though the message at this new location may be a local message.
					$this->new_message_count--;  // Decrement the number of new messages to process.
					return $this->transaction_message_array[$i];  // Return the array containing the message object, locality setting and processing directive..
				}
			}
		}
		else
		{
			return false;
		}
	}
        
        function unpopNewMessage()
        {
            // Undo what popNewMessage() did.
            $this->next_message_pointer--;
            $this->new_message_count++;
            return true;
        }
	
	function popPendingMessage() // Gets the latest pending message for this transaction.
	{
		$return_message = $this->transaction_message_array[$this->pending_messages[(sizeof($this->pending_messages) - 1)]]["message"];
		unset($this->pending_messages[(sizeof($this->pending_messages) - 1)]);
		return $return_message;
	}
	
	function hasExpired()  // IMPORTANT!  Checking to see if a session expired is usually called by the Dispatcher class.
	{
		// TODO:  Hardcoded this to always return false for testing purposes.  Fix when used in production.
		
		//echo "Transaction message array size is: " . (sizeof($this->transaction_message_array) - 1) . "\n";
		//var_dump($this->transaction_message_array);
		$last_message_object = $this->transaction_message_array[(sizeof($this->transaction_message_array) - 1)]["message"];
		$last_message_command = $last_message_object->getCommand();
		
		/*if (($last_message_object->getTimeStamp() + $this->global_session_expires) < time())  // Max time has passed from the last received message to this method call.
		{
			return (true);
		}
		else
		{
			return (false);
		}*/
		return (false);
	}
	
	function searchForCommand($search_command)  // Returns the message object that contains the searched command for false if not found..
	{
		foreach ($this->transaction_message_array as $this_message_object)
		{
			if ($this_message_object["message"]->getCommand() == $search_command)
			{
				return $this_message_object["message"];  // Return the message object.
			}
		}
		return false;  // Return false if the command was not found in the transaction.
	}

	public function setProxiedResponse($message_obj)
	{
            /* TODO:   Need to copy the message objects parameters from the passed message object. 
             *         Right now the only message this method will handle is ACK messages. 
             */
            // This is the linked transaction object we are setting not ours.  See the linkTransaction() for details.
            $this->linked_transaction_object->proxied_response_message_obj = $message_obj;  
            
            // Go ahead and add it to the parent transaction to save some time so we don't have to wait another 
            // iteration for the daemon to add it.
            
            $new_message = new Message($this->linked_transaction_object->getMessageOfSequenceNumber(1)->getSocket());
            $new_message->setTransactionID($this->linked_transaction_object->getTransactionID());
            $new_message->setClientID($this->linked_transaction_object->local_sid);

            if ($message_obj->getSequenceNumber() != $this->linked_transaction_object->last_seq_number)
            {
                $new_message->setSequenceNumber($this->linked_transaction_object->last_seq_number + 1);
            }
            
            $new_message->setCommand($message_obj->getCommand());
            $new_message->setReplyToCommand($message_obj->getReplyToCommand());
            $new_message->setReplyErrorCode($message_obj->getErrorCode());
            $new_message->setReplyNotes($message_obj->getReplyNotes());
            $new_message->buildTransmitMessage();
            
            $this->linked_transaction_object->addMessage($new_message, "proxied_response");
	}

	/*public function getProxiedResponse()  
	{   
            if ($this->proxied_response_message_obj != null)
            {
                $return_message = $this->proxied_response_message_obj;
                $this->proxied_response_message_obj = null;
                return $return_message;
            }
            else
            {
                return false;
            }
	}*/
	
}
?>