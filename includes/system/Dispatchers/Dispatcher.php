<?php

//RedBean
require_once(__DIR__."/../../orm/rb.php");  // The ORM technology used by Afrs.

require_once(__DIR__."/../../network/StreamSocketServer.php");  // Needed to reply to the client.  No object of the type is ever instantiated in the Dispatcher.
require_once(__DIR__."/../../system/Messages/Message.php");
require_once(__DIR__."/../../system/Transaction.php");
require_once(__DIR__."/../../functions.php");

Class Dispatcher
{
	protected           $transaction_obj = null,
                            $message_obj = null,
                            $message_locality = null,
                            $command = null,
                            $dispatch_db_conn = null,
                            $dispatch_query = null,  
                            $local_key = null,
                            $local_sid = null,
                            $local_fqdn = null,
			    $local_ip_address = null,
			    $local_daemon_port_number = null,
			    //$local_fqdn = null,
			    $timezone = null;  

	function __construct()
	{
		require(__DIR__."/../../../conf/afrs_vars.php");
		require(__DIR__."/../../../conf/afrs_config.php");
		
		// $this->dispatch_db_conn = new DbConnectionWrapper($dbtype, $dbhost, $dbname, $dbuser, $dbpassword);
		// $this->dispatch_query = new QueryWrapper($this->dispatch_db_conn);
                
                //RedBean
                R::setup("mysql:host=" . $dbhost . ";dbname=" . $dbname, $dbuser, $dbpassword);
		
		$this->local_key = $key;  // Taken from the afrs_vars.php included (above).
		
		// Get the sid (clientid) of the this server.  Used for such things if we need to proxy messages on behalf of 
		// the afrs-configure client or the local afrs-watcher processes the will be sent to remote servers.
		$this->local_sid = $sid;	
		
                $this->local_fqdn = $fqdn;
                
		// Get the IP address of the local server.  Used for various things such as proxying messages.
		$this->local_ip_address = $private_ip;	
		
		// Get the port number the daemon runs on.  Used for various things such as proxying messages.
		$this->daemon_port_number = $daemon_port_number;
		
		// Get the timezone for the local server.  Used for various things such as proxying messages.
		$this->timezone = $timezone;
		
		// Get the afrs version for the local server.  Used for various things such as proxying messages.
		$this->afrs_version = $afrs_version;

		// Get the fqdn of this server.  Used for various things such as proxying messages.
		//$this->dispatch_query->runQuery("select fqdn from tbl_inet_devices where ip = '$this->local_ip_address'");
                $result = R::findOne("inetdevices", " ip = ? ", array($this->local_ip_address));
		// $result = $this->dispatch_query->getResultAssoc();
                
                if (sizeof($result) > 0)
                {
                    $this->local_fqdn = $result->fqdn;
                }
	}

	function dispatch(&$transaction_obj)   // Return type is true/false on watch events, and Message objects for remote client messages.
	{
		$this->transaction_obj = $transaction_obj;
		echo "Processing transaction: " . $this->transaction_obj->getTransactionID() . "\n";
		if ($this->transaction_obj->hasNewMessages())
		{
                    echo "Transaction has new messages.\n";
                    $message_array = $this->transaction_obj->popNewMessage();
                    $this->message_obj = $message_array["message"];
                    $this->message_locality = $message_array["locality"];
		}
		
		else if (!$this->transaction_obj->hasNewMessages() && $this->transaction_obj->hasPendingMessages())
		{
			echo "-- Transaction " . $this->transaction_obj->getTransactionID() . " has pending messages.  Processing them now...\n";
			$this->message_obj = $this->transaction_obj->popPendingMessage();
			$pending_message_obj = $this->message_obj;
			$response_command = $this->calculateResponseCommand();

			if ($response_command == "ACK")
			{
				$response_message = new Message($this->message_obj->getSocket());
				$response_message->setCommand($response_command);
				return $this->transaction_obj->addMessage($response_message, $pending_message_obj); // addMessage() takes two params for building a response to a queued command.
			}
		}
                
                if ($this->transaction_obj->isProxiable() && $this->transaction_obj->getNewestMessageSeqNumber() == 1)
                {
                    echo "Suspending transaction: " . $this->transaction_obj->getTransactionID() . " until response from proxied message.\n";
                    return;  // TODO:  Need to fix this since we should return something other than null or place this somewhere else.
                }
                else if ($this->transaction_obj->isProxiable() && $this->message_locality == "proxied_response") // Send the proxied response to the remote client.
                {
                    return $this->message_obj;
                }
                
		$this->command = $this->message_obj->getCommand();
                $clientid = $this->message_obj->getClientID();

		
		// NOTE:  PLACEMENT LOCATION CRITICAL.  THINK BEFORE YOU MOVE THIS "IF" CHECK AROUND!
		// Need to check to see if we received an ACK to our 411 Session Expired Error message.
		if ($this->command == "ACK" && $this->transaction_obj->getPreviousMessageErrorCode() == "411" && ($this->message_obj->getReplyToCommand() == $this->transaction_obj->getPreviousMessageCommand()))
		{
			$return_message = new Message($this->message_obj->getSocket());
			$return_message->setCommand("BYE");
			return $this->transaction_obj->addMessage($return_message);  // addMessage() returns a message object.
		}

		// Check to see if the transaction has expired
		if ($this->transaction_obj->hasExpired())
		{
			$return_message = new Message($this->message_obj->getSocket());
			$return_message->setCommand("ERROR");
			$return_message->setReplyErrorCode("411");
			$return_message->setReplyNotes("Session Expired");
			echo "ERROR:  Dispatcher::dispatch() - Session Expired.\n";
			return $this->transaction_obj->addMessage($return_message);
		}
                
                /*
                 * Some work is done in the below code but further processing should be done by the extended class.
                 */
		if ($this->command == "ACK" || $this->command == "BYE" || $this->command == "ERROR")
		{
			// First check to see if the remote side's response is part of a linked and pending transaction.
			if ($this->transaction_obj->isLinked())
			{
                            if ($this->transaction_obj->getLinkedRelationship() == "child")
                            {
                                echo "Adding message to our parent (suspended) transaction: " . $this->transaction_obj->getLinkedTransactionID() . "\n";
                                $this->transaction_obj->setProxiedResponse($this->message_obj);
                            }
			}
                        
                        //  Need the extending Register dispatch class to process the ACK to update the clientid in the db.
                        //  DON'T FUCKING REMOVE THIS!!!!!
                        if ($this->command == "ACK" && $transaction_obj->getPreviousMessageCommand() == "REGISTER")
                        {
                            $this->transaction_obj->unpopNewMessage();
                            return false;
                        }
                        
                        $return_message = new Message($this->message_obj->getSocket());
                        $return_message->setCommand($this->calculateResponseCommand());
                        return $this->transaction_obj->addMessage($return_message);
		}
                else if ($this->command == "UNREGISTER")
                {
                    if ($this->isClientRegistered($this->message_obj->getClientID()))
                    { 
                        try
                        {
                            R::trash(R::find("syncpartners", " clientid = ? and partner_public_key = ? ", array($this->message_obj->getClientID(), $this->message_obj->getClientKey())));
                            return $this->transaction_obj->addMessage($this->buildReturnAck());
                        }
                        catch (Exception $e)
                        {
                            return false;
                        }
                    }
                    else
                    {
                            echo "ERROR: Dispatcher::receivedUnregister() - 400 - Member server with client ID of: " . $clientid . " does not exist.\n";
                            return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
                    }           
                }
		else
		{
                    // If we are here then we were of no help.  Return to the extended Dispatcher class for further processing. 
                    // Undo what Transaction::popNewMessage() did.
                    $this->transaction_obj->unpopNewMessage();
                    return false;  
		}		
	}

	protected function recordWatchEvent()  // Watch events should only be generated from the local machine.
	{
		$watch_id = $this->message_obj->getArgumentValue("WATCHID");
		$changed_files_array = $this->message_obj->getChangedFiles();
		$result = false;
		echo "There were " . sizeof($changed_files_array) . " in this watch event.\n";
		foreach ($changed_files_array as $this_changed_file)
		{
			$date = $this_changed_file["DATETIME"];
			$file = $this_changed_file["PATH"];
			$type = $this_changed_file["TYPE"];
			$event = $this_changed_file["ACTION"];
			$uid = $this_changed_file["UID"];
			$gid = $this_changed_file["GID"];
			$uid_name = $this_changed_file["USER"];
			$gid_name = $this_changed_file["GROUP"];
			$posix_permissions = $this_changed_file["POSIXPERMISSIONS"];
			$size = $this_changed_file["SIZE"];
                        
                        $new_journal_bean = R::dispense("journal");
                        $new_journal_bean->watch_id = $watch_id;
                        $new_journal_bean->date = $date;
                        $new_journal_bean->file = $file;
                        $new_journal_bean->event = $event;
                        $new_journal_bean->type = $type;
                        $new_journal_bean->uid = $uid;
                        $new_journal_bean->uid_name = $uid_name;
                        $new_journal_bean->gid = $gid;
                        $new_journal_bean->gid_name = $gid_name;
                        $new_journal_bean->posix_permissions = $posix_permissions;
                        $new_journal_bean->size = $size;

                        if (is_int(R::store($new_journal_bean)))  // Save new journal entry to db.
			{
				$result = true;
			}
			else
			{
				$result = false;
			}
		}
		return ($result);
	}

	protected function receivedUnregisterMemberServer()  // TODO:  Need to finish after all of the other commands have been completed.
	{
		if ($this->checkPassword())
		{
			$clientid = $this->message_obj->getClientID();

			if ($this->isClientRegistered($clientid))
			{
				// TODO Run all kinds of query shit to remove db records associated with the client's ID.

			}
			else
			{
				echo "ERROR: Dispatcher::receivedUnregister() - 400 - Member server with client ID of: " . $clientid . " does not exist.\n";
				return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
			}
		}
		else
		{
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}
	}

	protected function receivedMemberServerCheckin()
	{
		if ($this->message_obj->wasCreatedLocally())  // If the socket is not really a socket then we know this message was newly created internally.
		{
			$this->message_obj->buildTransmitMessage();
			return $this->message_obj;  //Just return so the message can be sent to the client via afrsd.
		}

		$clientid = $this->message_obj->getClientID();

		if ($this->checkPassword())
		{
			if ($this->isClientRegistered($clientid))
			{
                                $result = R::findOne("syncpartners", " clientid = ? ", array($clientid));
				// $this->dispatch_query->runQuery("select fqdn as name, ip_address, public_ip_address from tbl_sync_partners where clientid = '$clientid'");
                                $name = $result->fqdn;
				//$db_sync_partner_private_ip_address = $result["ip_address"];
                                $db_sync_partner_private_ip_address = $result->ip_address;
                                //$db_sync_partner_public_ip_address = $result["public_ip_address"];
                                $db_sync_partner_public_ip_address = $result->public_ip_address;
				$sync_partner_public_ip_address = $this->message_obj->getSenderPublicIP();
                                $sync_partner_private_ip_address = $this->message_obj->getSenderPrivateIP();

                                $bean_id = null;
				if ($db_sync_partner_private_ip_address != $sync_partner_private_ip_address || $db_sync_partner_public_ip_address != $sync_partner_public_ip_address)  // Update IP address on file since the client's IP has changed from what we currently have in our db.
				{
					echo "Client " . $name . "'s IP address has changed.  Updating our db to reflect the change...";
                                        $result->public_ip_address = $sync_partner_public_ip_address;
                                        $result->ip_address = $sync_partner_private_ip_address;
                                        $bean_id = R::store($result);
					if (is_int($bean_id))
					{
						echo "Done.\n";
					}
				}

				// if ($this->dispatch_query->runQuery("update tbl_sync_partners set last_checkin = NOW(), failed_checkin_count = 0 where clientid = '$clientid'"))
				$result = R::load('syncpartners', $bean_id);
                                $result->last_checkin = R::$f->now(); // Get the time by using the SQL NOW() function via ReadBean ORM.
                                $result->failed_checkin_count = 0;
                                
                                if(is_int(R::store($result)))
                                {
					echo "Checkin attempt was successfull for " . $name . "\n";
					return $this->transaction_obj->addMessage($this->buildReturnAck());
				}
				else
				{
					$return_message = new Message($this->message_obj->getSocket());
					$return_message->setCommand("ERROR");
					$return_message->setReplyErrorCode("500");
					$return_message->setReplyNotes("Internal Server Error - Failed to check you in.  Please try again.");
					echo "ERROR:  Dispatcher::receivedUpdateMemberServerCheckin() - Failed to checkin client: " . $clientid .  " (". $name . ").\n";
					return $this->transaction_obj->addMessage($return_message);
				}
			}
			else
			{
				return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
			}
		}
		else
		{
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}
	}

	protected function updateMemberServerValues()  // Completed 12/14/2009
	{
		$clientid = $this->message_obj->getClientID();
		if ($this->checkPassword($this->message_obj))
		{
			if ($this->isClientRegistered($clientid))
			{
				$this->dispatch_query->runQuery("select fqdn as name from tbl_sync_partners where clientid = '$clientid");
				$result = $this->dispatch_query->getResultAssoc();
				$name = $result["name"];

				$argument = $this->message_obj->getArgumentValue("PARAM");
				$argument_value = $this->message_obj->getArgumentValue("VALUE");
				if ($this->dispatch_query->runQuery("update tbl_sync_partners set $argument = '$argument_value' where clientid = '$clientid'"))
				{
					echo "Successfully updated parameter: " . $argument . " for " . $name . ".  New value is: " . $argument_value . "\n";
					return($this->buildReturnAck());
				}
				else
				{
					echo "ERROR: (Dispatcher) updateMemberServerValues()\n";
					return ("REJECTED, ERROR 15");
				}
			}
			else
			{
				echo "ERROR: (Dispatcher) updateMemberServerValues()\n";
				return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
			}
		}
		else
		{
			echo "Dispatcher::updateMemberServerValues - Wrong password specified.\n";
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}
	}

	protected function receivedGetShare()   // This method can be called by a remote server in response to a REQUESTSYNC command.
	{								// to determine the atrributes of the local share chosen to participate in syncs.
		// Completed on:  4/25/2010
		$clientid = $this->message_obj->getClientID();
		$requested_share_name = $this->message_obj->getArgumentValue("SHARENAME");
		if ($this->checkPassword())  // Only proceed if the client provided the correct password.
		{
			if ($this->isClientRegistered($clientid))  // Make sure the client is registered with this server before going further.
			{
				// Now we need to retrieve the password we have on-file for the requesting side so that we can
				// use it in our reponse (PRESENTSHARES) back to the requesting client.
				$this->dispatch_query->runQuery("select membership_password
												 from tbl_sync_partners
												 where clientid = '$clientid'");
				$result = $this->dispatch_query->getResultAssoc();
				$remote_password = $result["membership_password"];

				// Get a list of shares (active) from this server to send to the client.
				$this->dispatch_query->runQuery("select share_name, size, available_size, active, permission, creation_date
												 from tbl_shares 
												 where active = '1' and share_name = '$requested_share_name'");
				$result_array = $this->dispatch_query->getResultAssoc();
				$result_count = $this->dispatch_query->getResultSize();

				if ($result_count == 1 || $result_count == 0)
				{
					//echo "I found " . $result_count . " shares for this server.\n";
					$return_message = new Message($this->message_obj->getSocket());
					$return_message->setCommand("PRESENTSHARES");
					$return_message->setArgumentValue("MEMBERSHIPPASSWORD", $remote_password);
					$return_message->setArgumentValue("SHARECOUNT", $result_count);

					if ($result_count == 1)  // We only proceed if there is one share since getShare, not getShares was called.
					{
						// The below will create a sub associative array within the  message's argument associative array.
						for($i = 1; $i <= $result_count; $i++)
						{
							$this_share =  array("SHARENAME" => $result_array[$i]["share_name"],
								   "SIZE" => $result_array[$i]["size"],
								   "AVAILABLESTORAGE" => $result_array[$i]["size"],
								   "ACTIVE" => $result_array[$i]["active"],
								   "PERMISSIONS" => $result_array[$i]["permission"],
								   "CREATIONDATE" => $result_array[$i]["creation_date"]);
							$return_message->setShareValues($result_array[$i]["share_name"], $this_share_values); // This will make the message's argument array a 2D associative array.
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
					echo "ERROR:  Dispatcher::receivedGetShare() - Can't retrieve available share for this local server.  Returning an error to requesting client.\n";
					return $this->transaction_obj->addMessage($return_message);
				}
			}
			else
			{	// TODO:  Need to use a better error return system.
				echo "ERROR: Client " . $name . " is not a registered member server.  Can't give it a list of sync shares\n";
				return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
			}
		}
		else
		{
			echo "ERROR:  Dispatcher::receivedGetShares() - Wrong password specified.\n";
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}
	}

	protected function receivedGetShares()  // This method should be called by the client before initiating a REQUESTSYNC.
	{										// Completed on:  4/25/2010
		$clientid = $this->message_obj->getClientID();
		if ($this->checkPassword())  // Only proceed if the client provided the correct password.
		{
			if ($this->isClientRegistered($clientid))  // Make sure the client is registered with this server before going further.

			{
				// Now we need to retrieve the password we have on-file for the requesting side so that we can
				// use it in our reponse (PRESENTSHARES) back to the requesting client.
				$this->dispatch_query->runQuery("select membership_password
												 from tbl_sync_partners
												 where clientid = '$clientid'");
				$result = $this->dispatch_query->getResultAssoc();
				$remote_password = $result["membership_password"];

				// Get a list of shares from this server to send to the client.
				$this->dispatch_query->runQuery("select share_name, size, available_size, active, permission, creation_date
												 from tbl_shares 
												 where active = '1'");
				$result_array = $this->dispatch_query->getResultsAssoc();
				$result_count = $this->dispatch_query->getResultSize();

				if ($result_count >= 0)
				{
					//echo "I found " . $result_count . " shares for this server.\n";
					$return_message = new Message($this->message_obj->getSocket());
					$return_message->setCommand("PRESENTSHARES");
					$return_message->setArgumentValue("MEMBERSHIPPASSWORD", $remote_password);
					$return_message->setArgumentValue("SHARECOUNT", $result_count);

					if ($result_count > 0)
					{
						// The below will create a sub associative array within the  message's argument associative array.
						for($i = 1; $i <= $result_count; $i++)
						{
							$this_share_values =  array("SIZE" => $result_array[$i]["size"],
								   "AVAILABLESTORAGE" => $result_array[$i]["available_size"],
								   "ACTIVE" => $result_array[$i]["active"],
								   "PERMISSIONS" => $result_array[$i]["permission"],
								   "CREATIONDATE" => $result_array[$i]["creation_date"]);
							$return_message->setShareValues($result_array[$i]["share_name"], $this_share_values); // This will make the message's argument array a 2D associative array.
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
		else
		{
			echo "ERROR:  Dispatcher::receivedGetShares() - Wrong password specified.\n";
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}
	}

	protected function receivedPresentShares()  // Completed 7/12/2010.  Needs testing.
	{
		$clientid = $this->message_obj->getClientID();
		if ($this->isClientRegistered($clientid))  // Make sure the client is registered with this server before going further.
		{
			if ($this->checkPassword())
			{
				if ($this->message_obj->getSequenceNumber() > 0)  // Sequence number has to be greater than zero or else this message was spoofed.
				{
					// Get the shares that the remote side sent to us and store them in the db.
					$shares_and_their_values = $this->message_obj->getSharesValues();
					$last_updated = date("Y\-m\-d G:i:s"); // Now.

					$shares_keys = array_keys($shares_and_their_values);
					for($i = 0; $i < sizeof($shares_and_their_values); $i++)
					{
						$share_name = $shares_and_their_values[$shares_keys[$i]];
						$size = $shares_and_their_values[$shares_keys[$i]]["SIZE"];
						$available_size = $shares_and_their_values[$shares_keys[$i]]["AVAILABLESTORAGE"];
						$active = $shares_and_their_values[$shares_keys[$i]]["ACTIVE"];
						$permission = $shares_and_their_values[$shares_keys[$i]]["PERMISSIONS"];
						$creation_date = $shares_and_their_values[$shares_keys[$i]]["CREATIONDATE"];

						// First we need to check to see if the shares already exist in our db listing for the client.  If they
						// do, then we perform a db update query.  If they do not exist in our db then we do a db insert.
						$this->dispatch_query->runQuery("select count(*) as record_count from tbl_partner_shares where share_name = '$share_name' and clientid = '$clientid'");
						$result = $this->dispatch_query->getResultAssoc();
						$record_count = $result["record_count"];

						if ($record_count == 1) // Perform an update query since the client shares already exist in our db or this is a reply to an initial REQUESTSYNC command.
						{
							// Check to see if this reply is part of a pending REQUESTSYNC command.
							if ($this->transaction_obj->hasPendingMessages())
							{
								$pending_message_object = $this->transaction_obj->popPendingMessage();
								if ($pending_message_object->getCommand() == "REQUESTSYNC")
								{
									return $this->transaction_obj->addMessage($this->buildReturnAck());
								}
							}
							else
							{
								$this->dispatch_query->runQuery("update tbl_partner_shares set
																 share_name = '$share_name', 
																 size = $size, 
																 available_size = $available_size,
																 active = $active,
																 permission = '$permission',
																 creation_date = '$creation_date',
																 last_updated = '$last_updated'
																 where clientid = '$clientid'");
							}
						}
						else if ($record_count == 0)  // Perform an insert query since the client shares do not already exist in our db.
						{
							$this->dispatch_query->runQuery("insert into tbl_partner_shares
														 (clientid, share_name, size, available_size, active, permission, creation_date, last_updated)
													 	values('$clientid', 
																'$share_name',
															     $size,
															     $available_size,
															     $active,
																'$permission',
																'$creation_date',
																'$last_updated')");

							/*
							 * If the PRESENTSHARES command was part of an initial REQUESTSYNC transaction, then create
							 * a syncpoint on this local server for the sharename presented in the PRESENTSHARES response.
							 */
							if ($this->transaction_obj->getMessageOfSequenceNumber(1)->getCommand() == "REQUESTSYNC")
							{
								$local_mode = null;

								$requestsync_message = $this->transaction_obj->getMessageOfSequenceNumber(1);
								$local_share_name = $requestsync_message->getArgumentValue("SHARENAME");
								$remote_share_name = $requestsync_message->getArgumentValue("LOCALSHARENAME");
								if ($requestsync_message->getArgumentValue("MODE") == "PUSH")
								{
									$local_mode = "pull";
								}
								else if ($requestsync_message->getArgumentValue("MODE") == "PULL")
								{
									$local_mode = "push";
								}
								else if ($requestsync_message->getArgumentValue("MODE") == "DUPLEX")
								{
									$local_mode = "duplex";
								}

								$this->dispatch_query->runQuery("select id 
														   from tbl_shares
														   where tbl_shares.share_name = '$local_share_name'");
								$query_result = $this->dispatch_query->getResultAssoc();
								$fk_local_share_id = $query_result["id"];


								$sync_partner_id = $this->message_obj->getClientID();
								$this->dispatch_query->runQuery("insert into tbl_sync_partner_watches
														   (sync_partner_id, fk_local_share_id, fk_partner_share_name, local_mode, active)
														   values('$sync_partner_id', $fk__local_share_id, $remote_share_name, '$local_mode', 1)");
							}
						}
					}
					// Calculate the repsonse command and add the response message to the transaction.
					$return_message = new Message($this->message_obj->getSocket());
					$return_message->setCommand($this->calculateResponseCommand());
					return $this->transaction_obj->addMessage($return_message);
				}
				else  // TODO: Need to implement the Throw exception stuff here for errors.
				{
					echo "ERROR:  Dispatcher::receivedPresentShares() - Message sequence number is not > 0.  Potentially spoofed message by unauthorized client/server.\n";
				}
			}
			else
			{
				echo "ERROR:  Incorrect password\n";
				return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
			}
		}
		else  // TODO: Need to implement the Throw exception stuff here for errors.
		{
			echo "ERROR:  Dispatcher:: receivedPresentShares() - Client is not registered.\n";
			return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
		}
	}

	protected function receivedRequestSync()  // TODO:  Need to implement logic to update parameters if the sync point
	{								  //        already exists in the db.  If so update the info from remote.
		$clientid = $this->message_obj->getClientID();
		if ($this->checkPassword($this->message_obj))
		{
			if ($this->isClientRegistered($clientid))  // Make sure the client is registered with this server before going further.
			{
				if ($this->transaction_obj->hasPendingMessages())
				{
					// First we need to get the message object for the REQUESTYSYNC command.
					$pending_message_object = $this->message_obj;  // This is set at the top of this file.  LOOK!!
					$local_sharename = $pending_message_object->getArgumentValue("SHARENAME");
					$mode = $pending_message_object->getArgumentValue("MODE");

					$local_mode_map = array("DUPLEX" => "DUPLEX", "PUSH" => "PULL", "PULL" => "PUSH");
					$local_mode = $local_mode_map[$mode];  // Transform the remote mode into the corresponding local mode.
					// Doing it this way saves from the if statements that we would
					// have to use.

					$present_share_object = $this->transaction_obj->searchForCommand("PRESENTSHARES");

					// Being a little paranoid on making sure the info get returned is correct.
					if (is_object($present_share_object) && $present_share_object->getComand() == "PRESENTSHARES")
					{
						$remote_sharename = $present_share_object->getArgumentValue("SHARENAME");
						$remote_size = $present_share_object->getArgumentValue("SIZE");
						$remote_available_storage = $present_share_object->getArgumentValue("AVAILABLESTORAGE");
						$remote_permission = $present_share_object->getArgumentValue("PERMISSIONS");
						$remote_is_active = $present_share_object->getArgumentValue("ACTIVE");

						// Only proceed if the remote side's share is active.  If it's not then it request to sync with us
						// for this share is irrelavent.
						if ($remote_is_active == '1')
						{
							// Get all of the relevant requested info from the db.
							$this->dispatch_query->runQuery("select share_name, size, available_size, permission
														 from tbl_shares 
														 where active = '1' and share_name = '$local_sharename'");	

							if ($result = $this->dispatch_query->getResultAssoc())
							{
								$local_size = $result["size"];
								$local_available_size = $result["available_size"];
								$local_permision = $result["permission"];

								// Make sure the local and remote permissions jive.
								if (($mode == "PULL" && $remote_permission == "write" && $local_permision == "read") || ($mode == "PUSH" && $remote_permission == "read" && $local_permision == "write") || ($mode == "DUPLEX" && $remote_permission == "read/write" && $local_permision == "read/write"))
								{
									// Now that the permissions and sync mode's jive, make sure the sync sizes jive.
									if (($mode == "PULL" && $remote_available_storage > ($remote_size + $local_size)) || ($mode == "PUSH" && $local_available_size > ($local_size + $remote_size)) || ($mode == "DUPLEX" && (($local_available_size > ($local_size + $remote_size)) || $remote_available_storage > ($remote_size + $local_size))))
									{
										if($this->dispatch_query->runQuery("insert into tbl_sync_partner_watches
																	 (sync_partner_id, fk_share_id, mode, active) 
																	 select '$clientid', tbl_shares.id, '$local_mode', '1' 
																	 	from tbl_shares where tbl_shares.share_name = '$local_sharename'") != false)
										{
											return $this->transaction_obj->addMessage($this->buildReturnAck(), $this->message_obj);  // $this->message_obj is the queued REQUESTSYNC object.
										}
										else
										{
											$return_message = new Message($this->message_obj->getSocket());
											$return_message->setCommand("ERROR");
											$return_message->setReplyErrorCode("500");
											$return_message->setReplyNotes("Internal Server Error - Failed to add the requested sync point to the remote database.\n");
											echo "ERROR:  Dispatcher:: receivedRequestSync() - 500 - Internal Server Error - Failed to add the requested sync point to the local database.\n";
											return $this->transaction_obj->addMessage($return_message);
										}
									}
								}
								else
								{
									$return_message = new Message($this->message_obj->getSocket());
									$return_message->setCommand("ERROR");
									$return_message->setReplyErrorCode("409");
									$return_message->setReplyNotes("Conflict - Your share permissions conflict with our share's local settings/attributes.");
									echo "ERROR:  Dispatcher::receivedRequestSync() - Conflict - Received parameters conflict with local settings/attributes.\n";
									return $this->transaction_obj->addMessage($return_message);
								}
							}
						}
						else
						{
							$return_message = new Message($this->message_obj->getSocket());
							$return_message->setCommand("ERROR");
							$return_message->setReplyErrorCode("480");
							$return_message->setReplyNotes("Temporarily Unavailable - The share on your side for this sync request is not active at this time.  Please fix and try again.");
							echo "ERROR:  Dispatcher::receivedRequestSync() - 480 - Temporarily Unavailable - The share on the remote side is not active for the sync request they presented to us.\n";
							return $this->transaction_obj->addMessage($return_message);
						}
					}
					else
					{
						$return_message = new Message($this->message_obj->getSocket());
						$return_message->setCommand("ERROR");
						$return_message->setReplyErrorCode("500");
						$return_message->setReplyNotes("Internal Server Error - Fatal Server Error.  If this continues, contact AFRS creator to fix!.");
						echo "ERROR:  Dispatcher::receivedRequestSync() - 500 - Internal Server Error - Fatal Server Error.  If this continues, contact AFRS creator to fix!. \n";
					}
				}
				else // Only run if we don't have any pending messages.  I.E.  First occurance of REQUESTSYNC.
				{
					// Now we return the command QUEUED since we will have to eventually generate the
					// GETSHARE for the sharename specified.  We need info about the remote side's SHARE before
					// we can fulfill this requst.  Therefore, request pending...
					$return_message = new Message($this->message_obj->getSocket());
					$return_message->setCommand("QUEUED");
					return $this->transaction_obj->addMessage($return_message);
				}
			}
			else
			{
				echo "ERROR: Dispatcher::receivedRequestSync() - 407 - Authentication Required - The remote client is not a registered client of ours so they can't make requests like these.\n";
				return $this->transaction_obj->addMessage($this->buildErrorMsg401NonRegClient());
			}
		}
		else
		{
			echo "ERROR: Dispatcher::receivedRequestSync() - 407 - Authentication Required - The remote client provided an incorrect password for this server.\n";
			return $this->transaction_obj->addMessage($this->buildErrorMsg401BadPass());
		}

	}

	protected function receivedPending()
	{
		// Mark the message this received PENDING command is for, as PENDING.
		$this->transaction_obj->markMessagePending($this->transaction_obj->getMessageOfSequenceNumber($this->message_obj->getReplyToSequenceNumber()));

		// After we have marked the appropriate message as PENDING, reply with an ACK.
		return $this->transaction_obj->addMessage($this->buildReturnAck());
	}

	protected function sendGetShare($share_name_on_remote)
	{
		$return_message = new Message($this->message_obj->getSocket());
		$return_message->setCommand("GETSHARE");
		$return_message->setArgumentValue("SHARENAME", array(0 => $share_name_on_remote));
		return $return_message;
	}

	protected function sendGetShares($remote_client_id)
	{

	}

	protected function sendPresentShares($shares_array)
	{
		$shares_message = new Message($this->message_obj->getSocket());  // Create a new message to send to client referencing the original socket to reply to.
		$shares_message->setCommand("PRESENTSHARES");  // Set the message command.
		$serialized_shares = serialize($shares_array);
		//$sid = getSID();  // Get this server's securityID (SID).  The SID is used as the CLIENT_ID to remote servers (clients).
		$shares_message->setArgumentValues(array('SHARES' => $serialized_shares));  // Set the sharenames and the sizes.

		$shares_message->saveXML();  // Create the actual XML string to send to the client.
		return $shares_message; // Return the reply message to afrsd to send the response.
	}

	protected function receivedEndTransaction()
	{
		// We will blindly honor an BYE command.  I.e. we will not check anything, we will just ack it and
		// afrsd parent will tear-down/destroy the transaction tied to this message when we return from this method.
		return($this->buildReturnAck());
	}

	protected function receivedAck()
	{
		$return_message = new Message($this->message_obj->getSocket());
		$return_message->setCommand($this->calculateResponseCommand());
		return $this->transaction_obj->addMessage($return_message);
	}

	protected function receivedError()
	{
		if ($this->message_obj->getErrorCode() == "411")
		{
			$return_message = new Message($this->message_obj->getSocket());
			$return_message->setCommand("ACK");
			return $this->transaction_obj->addMessage($return_message);
		}

	}

	protected function isClientRegistered()
	{
		$sync_partners_bean = R::find("syncpartners", " clientid = ? and partner_public_key = ? ", array($this->message_obj->getClientID(), $this->message_obj->getClientKey()));
		if (sizeof($sync_partners_bean) == 1)
		{
			return (true);
		}
		else if (sizeof($sync_partners_bean) == 0)
		{
			return (false);
		}
	}

	protected function checkPassword()  // TODO:  This needs to be renamed to checkKey() since it actually checks the key not the password of the remote side.
	{
		//echo "Checking password against: " . $this->local_password . "\n";
		//echo "Password provided by remote is: " . $this->message_obj->getArgumentValue("MEMBERSHIPPASSWORD") . "\n";
		if ($this->message_obj->getArgumentValue("MEMBERSHIPPASSWORD") == $this->local_password)
		{
			return(true);
		}
		else
		{
			return(false);
		}
	}

	protected function buildReturnAck()
	{
		$return_message = new Message($this->message_obj->getSocket());
		$return_message->setCommand("ACK");
		return $return_message;
	}

	public function calculateResponseCommand()
	{
		
		if (!$this->transaction_obj->hasNewMessages() && $this->transaction_obj->hasPendingMessages())
		{
			$latest_message_obj = $this->transaction_obj->getNewestMessage();
			$pending_message_obj = $this->message_obj;  // This was set at the beginning when we called the popPendingMessages() method.
			$orig_msg_obj = $this->transaction_obj->getMessageOfSequenceNumber(1);
			$response_command = $this->message_obj->dialogue_array[$orig_msg_obj->getCommand()][$latest_message_obj->getCommand()]["null"];
			return $response_command;
		}
		else  // This is if their are new messages.  If you run the hasNewMessages() here it may return false since we already popped the new messages queue.
		{
			// Compare the remote's replytocommand with our recorded sent message to make sure it's not spoofed.
			//$prev_message = ($this->transaction_obj->getNewestMessage());
			//echo "PREV SEQUENCE NUMBER IS: " . get_class($prev_message) . "\n";
			//$prev_command = $prev_message->getCommand();
			$previously_sent_message_obj = $this->transaction_obj->getMessageOfSequenceNumber($this->message_obj->getSequenceNumber() - 1);
			
			if ($previously_sent_message_obj === false)
			{
				echo "DEBUG:  FATAL ERROR: Dispatcher::calculateResponseCommand() - Could not retrieve transaction's previous Message object.  Check logic of calling program/method.\n";
				exit(1);
			}
			
			$previously_sent_command = $previously_sent_message_obj->getCommand();
			if ($this->message_obj->getReplyToCommand() == $previously_sent_command)
			{
				
				$orig_msg_obj = $this->transaction_obj->getMessageOfSequenceNumber(1);	// Proxied transactions will not be affected by
                                                                                                        // this since we hardcode the original proxy message's
                                                                                                        // sequence # to 0.
                                
				// Response commands are calculated from the dialague_array created in the constructor of this class.
				$response_command = $this->message_obj->dialogue_array[$orig_msg_obj->getCommand()][$previously_sent_command][$this->message_obj->getCommand()];
				if ($this->message_obj->isCommandValid($response_command))  // Can use any valid message object here to call isCommandValid() method.
				{
					return $response_command;
				}
				else
				{
					echo "FATAL ERROR: Dispatcher::calculateResponseCommand() - Internal system error.  Internally generated command of " . $response_command . " is not valid! aborting now!\n";
					exit(1);
				}
			}
			else
			{
				echo "WARNING!!!  Spoofed message Dispatcher::calculateResponseCommand().\n";
				exit(1);
			}
		}
	}

	protected function buildErrorMsg401BadPass()
	{
		$return_message = new Message($this->message_obj->getSocket());
		$return_message->setCommand("ERROR");
		$return_message->setReplyErrorCode("401");
		$return_message->setReplyNotes("Unauthorized - Bad password specified.  Go away.");
		return $return_message;
	}

	protected function buildErrorMsg401NonRegClient()
	{
		$return_message = new Message($this->message_obj->getSocket());
		$return_message->setCommand("ERROR");
		$return_message->setReplyErrorCode("401");
		$return_message->setReplyNotes("Unauthorized - You are not a registered client with this server.  Go away.");
		return $return_message;
	}
}
?>