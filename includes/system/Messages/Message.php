<?php

require_once(__DIR__."/../../functions.php");

/*
 * Message class is a wrapper to pass command messages.  It can be used for both client and server 
 * and is why this class was created.  The ultimate distination for a Message object is the Dispatcher
 * class.  The Dispatcher class then reads the Message object and determines what to do with it.
 * Message objects are also the primary mechanism for various high-level parts of the AFRS Daemon to 
 * communicate with each other.  No direct communication should ever be tried between higher levels
 * of the daemon.
 * 
 * TODO  *  Make sure to string \n from the end of the xml string after using saveXML() method.
 *
 * 
 * 
 * Possible client commands: Ex. "REGISTER ADDRESS afrs-server.afrs.com, NAME afrs-server1, TIMEZONE +7, BANDWIDTHUP 384, BANDWIDTHDOWN 8000, USER afrs, PASSWORD afrspassword"
 * 
 * REGISTER		Registers a remote end server as a member server.
 *   (Possible receive commands seperated by commas):
 * 		=>	ADDRESS	[ADDRESS]			The address of the remote end to register as a member server (fqdn or ip).
 * 		=>	PORT [PORT]					The address of the remote end to register as a member server and the port it listens on for afrs connections.
 * 		=>	NAME [NAME]					The fqdn of the remote server".
 * 		=>	TIMEZONE [GMT OFFSET]		The timezone of the remote end server.
 * 		=>	BANDWIDTHUP	[kb/sec]		The total upstream bandwidth of the remote server (in kb/sec).
 * 		=>	BANDWIDTHDOWN [kb/sec]		The total downstream(download) bandwidth of the remote server (in kb/sec).
 * 		=>  AFRSVERSION [VERSION NUMBER]
 * 		=>  PRIORITY [LOW MEDIUM HIGH]
 *   (Possible reply commands):
 * 		=>  ERROR [error description]
 * 		=>	ACK
 * 
 * UNREGISTER	Unregisters a remote end server from the member server list.
 * 		=>  MEMBERSHIPPASSWORD [PASSWORD]
 * 
 * CHECKIN 
 * 		=>  REMOTECLIENTID [CLIENT ID OF REMOTE SYNC PARTNER]  Primarily used for local client booking.
 * 
 * UPDATE								A request from the remote client to this server to update its database with the follwing info.
 * 										NOTE:  If the remote client's IP has changed, it (the client) will NOT issue an update
 *											  message since the server receiving a CHECKIN message will check if there is an IP discrepancy
 *											  and update it's database on the fly.
 * 		
 * 		=> PARAM [PARAM NAME]				Any of the commands supported by the REGISTER command.
 * 		=> VALUE [NEW VALUE FOR PARAM]
 * 
 * 
 * GETSHARES    (No parameters)
 * 		=>
 * 	(Possible responses):
 * PRESENTSHARES
 * 		
 
 * 
 * PRESENTSHARES (Can have multiple shares returned.  Only active shares should be sent)
 *              =>  SHARECOUNT [NUMBER OF SHARES BEING PRESENTED]
 * 		=>  NAME [NAME OF SHARE]
 *                  =>  UNIQUE_IDENTIFIER [UNIQUE ID HASH] (Unique id to twart sync loops with other partners)
 *                  =>  SIZE [CURRENT SIZE OF SHARE] (in KB)
 *                  =>  FREESIZE [ROOM LEFT ON SHARE]
 *                  =>  GLOBALPERMISSION [SEND/RECIEVE/BOTH]
 *                  =>  CREATIONDATE [DATE SHARE WAS CREATED]
 * 		
 * 		=>  NAME
 * 		etc.
 * 			.
 *			..
 *			...
 * 
 * REQUESTSYNC							Request from the client to add a new sync point.  This can also be used 
 * 										to update requestsync parameters on the remote side.
 * 		=>   MEMBERSHIPPASSWORD [PASSWORD]
 * 		=>	REMOTESHARENAME		   			The name of the share for this watch point at the receiving end.
 * 		=>	LOCALSHARENAME				The name of the share on the server sending the REQUESTSYNC command that SHARENAME will sync to.
 * 		=>	SCHEDULE [ON/OFF]	The schedule for the new sync.  If none provided schedule is assumed to be real-time.
 * 			=>	DAYS (Not implemented)
 * 			=>	HOURS (Not implemented)
 * 			=>	MINUTES (Not implemented)
 * 			=>	BANDWIDTH [kb/sec] (Not implemented)
 * 		=>	MODE [ PULL | PUSH | DUPLEX ]
 * 			//	DUPLEX				Updates will be both PUSHed and PULLed between client and server.
 * 			//	PULL					Udates are pulled from the receving server to this client.
 * 			//	PUSHED				Updates are pushed to the receiving server of this server.
 *   (Possible reply commands):
 * 		=>	ACK
 *                          => UNIQEIDENTIFIER                 The unique identifier code from the remote end to prevent syncing loops.
 * 		=>	ERROR [error description]
 * 
 * 
 * UNREGISTERYSYNC						Used to remove a SYNC point from a remote server.
 * 		=>  MEMBERSHIPPASSWORD [PASSWORD]
 * 		=>  SHARENAME					The name of the share on the remote side.
 * 		=>  LOCALSHARENAME				The name of the share on the local server.
 * 
 * 
 * SYNCREFRESH							This is used as a refresher for a current running sync command.  This is used
 * 										so that the SYNC operation does not timeout (SESSION EXPIRED).
 * 
 * SYNC
 * 		=> MEMBERSHIPPASSWORD [PASSWORD]
 * 		=> UNIQEIDENTIFIER                             The unique identifier code from the remote end to prevent syncing loops.
 * 		
 *              => MANIFEST [MANIFIEST FILE SIZE] [MANIFEST FILE CONTENTS]	Gzipped/B2zipped text file or text stream containing the files' metadata to sync.
 *              or
 *              => FILE [SIZE] [RELATIVE LOCATION ON SHARE]
 * 
 *   (Posslibe reply commands);
 * 		=> ACK
 * 		=> LOCKED (Can be at share level or file level.  Depends on how share was configured)
 * 			=> WAIT [X seconds before retry]
 * 		=> REJECTED		
 * 
 * WATCHEVENT							A file has been changed and this event information has been submitted from afrs-watcher.
 * 		=> WATCHID [WATCH ID]			ID for the patch being watched.
 * 		=> DATETIME [DATE & TIME]
 * 		=> PATH [FULL PATH TO FILE THAT CHANGED]
 * 		=> FILETYPE [TYPE]
 * 		=> ACTION [FILE ACTION]
 * 		=> UID [FS USERID]
 * 		=> USER [USER WHO OWNS THE FILE]
 * 		=> GID [FS GROUPID]
 * 		=> GROUP [GROUP OWNER]
 * 		=> POSIXPERMISSIONS [POSIX PERMISSIONS]
 * 		=> SIZE [FILE SIZE]
 * 
 * QUEUED					This tells the receiving side of this command to put command specified in the reply_to_command 
 * 						directive in a pending/queued response state.  QUEUED is used to tell the other side that other message 
 * 						processing will be needed by both side before a legit response can be generated for the command 
 * 						the PENDING response is for.
 * 
 * BYE					Ends a transaction conversation.  The transaction ID is extracted from the message xml headers.
 * 
 * ACK					Acknowledges the last command received.  It's like an OK.  This would be a valid response to a 
 * 						BYE command or a REGISTER COMMAND.
 * 
 * ERROR					An error has occurred while the remote side tried processing one of our commands/requests.
 * 						The error details are described in the reply section of the message (errorcode, notes).
 *  
 * 	
 */
Class Message
{
              
            protected 	$timestamp = null,
			$command = null,  // Text
			$arguments = null, // An associative array that holds the arguments as the keys to the command and the values to the arguments.
			$socket = null,
			$transaction_id = null,  // Used to make sure each message belongs with the correct conversation/transaction.
			$client_id = null,
                        $sender_public_ip = null,
                        $sender_private_ip = null,
			//$args_values_array = null,
			$seq_number = null,  // Used to track conversations and keep them in order.
			$key = null, // The public key.
                        $data_payload = null,  // Binary data. (this is usually a compress text file containing the files to be synced.)
			$xml_message = null,  // Unparsed message from client/server.
			$my_argv = null,
			$my_argc = null,
                        $is_proxied_response = null,  //  This let's us determine if the message is the response to a proxied transaction.
			$sid = null, // This is the SID/ClientID of this server, not the remote end.
			$reply_to_command = null,  // Stores the previous command that this message is a response/reply to.
			$reply_to_sequence_number = null,
			$reply_errorcode = null,
			$reply_notes = null,			
			$commands_array = array('REGISTER' => array('ADDRESS' => null,
                                                                    'PORT' => null,
                                                                    'NAME' => null,
                                                                    'TIMEZONE' => null,
                                                                    'BANDWIDTHUP' => null,
                                                                    'BANDWIDTHDOWN' => null,
                                                                    'AFRSVERSION' => null,
                                                                    'PRIORITY' => null),
                                                'UNREGISTER' => array('MEMBERSHIPPASSWORD' => null),
                                                'UPDATE' => array('PARAM' => null,
                                                                                      'VALUE' => null),
                                                'GETSHARES' => array(),
                                                'SYNCREFRESH' => null,
                                                'REQUESTSYNC' => array('MEMBERSHIPPASSWORD' => null,
                                                                                       'SHARENAME' => null,
                                                                                       'LOCALSHARENAME' => null,
                                                                                       'MODE' => null),
                                              'SYNC' => array('SYNCID' => null,
                                                                              'MANIFEST' => null),
                                              'QUEUED' => null,
                                              'BYE' => null,
                                              'ACK' => null,
                                              'ERROR' => null,
                                              'DISCARD' => null),
			$filepathname = null,
                        $filetype = null,
			$fileaction = null,
			$fileuid = null,
			$filegid = null,
			$fileuser = null,
			$filegroup = null,
			$fileposixpermissions = null,
			$filesize = null,
			
			
			// The below variables are used for DOM/XML file creation.
			$doc = null,
			$message_element = null,
			$client_element = null,
                        $sender_private_ip_element = null,
                        $sender_public_ip_element = null,                        
			$transaction_element = null,
                        $key_element = null,
			$sequence_element = null,
			$timestamp_element = null,
			$command_element = null,
			$cmd_element = null,
			$arguments_element = null,
			$argument_element = null,
			$argname_element = null,
			$argvalue_element = null,

                        $filelist_element = null,
			$file_element = null,
			$filedate_element = null,
			$filepathname_element = null,
                        $filetype_element = null,
                        $fileaction_element = null,
                        $fileuid_element = null,
                        $filegid_element = null,
			$fileuser_element = null,
		        $filegroup_element = null,
		        $fileposixpermissions_element = null,
			$filesize_element = null,

			$shares_element = null,
			$share_element = null,
			$share_name_element = null,
			$share_size_element = null,
			$share_available_storage_element = null,
			$share_active_element = null,
			$share_permissions_element = null,
			$share_creation_date_element = null,

			$reply_element = null,
			$reply_tocommand_element = null,
			$reply_tosequence_number_element = null,
			$reply_errorcode_element = null,
			$reply_notes_element = null,
			
			// Below are the node variables used for DOM
			$message_node,
			$client_node,
			$transaction_node,
                        $key_node,
			$sequence_node,
			$timestamp_node,
                        $sender_privateip_node,
                        $sender_publicip_node,
			$command_node,
			$cmd_node,
			$arguments_node,
			$argument_node,
			$argname_node,
			$argvalue_node,
			$filelist_node,
			$file_node,
			$filepathname_node,
			$filesize_node,
			$filejournaldate_node,
			$share_node,
			$share_name_node,
			$share_size_node,
			$share_available_storage_node,
			$share_active_node,
			$share_permissions_node,
			$share_creation_date_node,
			$reply_node,
			$reply_tocommand_node,
			$reply_tosequence_node,
			$reply_errorcode_node,
			$reply_notes_node;
        
        public          // Now we build the 3-dimensional associative array that will help us compute the message responses to received commands.
                        //
                        // This is how the 3D array is read:
                        //
                        // [originally initiated command][what our previous sent msg was][what we received from remote] = our resonse;
                        // IMPORTANT:  "null" is the string 'null' and not the null directive/value.
                        // NOTE:  Some commands will never be in the 1-d part of the array as the originally sent command, since these
                        //        will need to be solicited.  For example, the PRESENTSHARES will never originally be sent by any server
                        //        since it is solicited by the GETSHARES COMMAND.
                        //
                        // If we receive a message out of sequence that results in our array query returning a null (not 'null'), then
                        // we will responsd with a 491 Request Pending and kill the transaction.
                        //
                        // Error response commands should not be included in the dialogue_array since they can occur at any time during
                        // the transaction and therefore has too many possible scenarios.

                        /*
                         * TODO:  Need to account for error messages in the responses from remote and respond appropriately
                         */

                        $dialogue_array = array("REGISTER" => array("null" => array("REGISTER" => "ACK"),
                                                     "REGISTER" => array("ACK" => "BYE", "ERROR" => "ACK"),
                                                     "ACK" => array("BYE" => "ACK"),
                                                     "BYE" => array("ACK" => "DISCARD"),
                                                     "ERROR" => array("ACK" => "BYE")),
                                       "UNREGISTER" => array("null" => array("UNREGISTER" => "ACK"),
                                                     "UNREGISTER" => array("ACK" => "BYE"),
                                                     "ACK" => array("BYE" => "ACK"),
                                                     "BYE" => array("ACK" => "DISCARD")),
                                       "GETSHARES" => array("null" => array("GETSHARES" => "PRESENTSHARES"),
                                                     "GETSHARES" => array("PRESENTSHARES" => "ACK"),
                                                     "PRESENTSHARES" => array("ACK" => "BYE"),
                                                     "ACK" => array("BYE" => "ACK"),
                                                     "BYE" => array("ACK" => "DISCARD")),
                                       "REQUESTSYNC" => array("null" => array("REQUESTSYNC" => "QUEUED"),
                                                     "REQUESTSYNC" => array("QUEUED" => "ACK"),
                                                     "QUEUED" => array("ACK" => "GETSHARE"),
                                                     "ACK" => array("GETSHARE" => "PRESENTSHARES"),
                                                     "GETSHARES" => array("PRESENTSHARES" => "ACK"),
                                                     "PRESENTSHARES" => array("ACK" => "DISCARD"),
                                                     "ACK" => array("null" => "ACK"),  // Finally ACK the REQUESTSYNC command.
                                                     "REQUESTSYNC" => array("ACK" => "BYE"),  // Finish the REQUESTSYNC command.
                                                     "ACK" => array("BYE" => "ACK"),
                                                     "BYE" => array("ACK" => "DISCARD")));
	
	function __construct($socket_or_address, $xml_or_command)  // __construct($ip_and_port) --> internal->remote,  __construct($socket, $xml_message)  --> remote->internal or watch record event.
	{
		require(__DIR__."/../../../conf/afrs_vars.php");
		$this->sid = $sid; // $sid comes from the afrs_vars.php file.
                $this->key = $key;  // The key used in all communication to verify our authenticity.
                
                // If it's a string it will be used by the StreamSocketServer class to create a socket to the remote party.
                // If it's an actual stream resource then it will be used as is by the StreamSocketServer class.
                $this->socket = $socket_or_address;
                
                $this->doc = new DOMDocument();
                                
		$num_args = func_num_args();  // Get the number of arguments passed to this constructor.
		
		//if ($num_args == 1)  // Message created from internal.  Only argument is the socket.
                if (in_array($xml_or_command, $this->commands_array))  // If command was found we know it's a locally-generated message.
		{
			echo "Local Message\n";
			$this->sender_private_ip = $private_ip;  // These vars come from the afrs_vars.php file.
                        $this->sender_public_ip = $public_ip;  // These vars come from the afrs_vars.php file.
                       
                        $this->doc->version = "1.0";
                        $this->doc->encoding = "UTF-8";
                        
			// Elements section.
			$this->message_element = $this->doc->createElement("afrsmessage");
				$this->client_element = $this->doc->createElement("clientid");
				$this->transaction_element = $this->doc->createElement("transactionid");
                                $this->key_element = $this->doc->createElement("key");
				$this->sequence_element = $this->doc->createElement("sequencenumber");
				$this->timestamp_element = $this->doc->createElement("timestamp");  // The timestamp element should not get it's value set until right before the xml doc is written to disk/memeory via saveXML() method.
				$this->sender_private_ip_element = $this->doc->createElement("senderprivateip");
                                $this->sender_public_ip_element = $this->doc->createElement("senderpublicip");
                                
                                $this->command_element = $this->doc->createElement("command");
					$this->cmd_element = $this->doc->createElement("cmdname");
					$this->arguments_element = $this->doc->createElement("arguments");
						
				$this->reply_element = $this->doc->createElement("reply");
					$this->reply_tocommand_element = $this->doc->createElement("tocommand");
					$this->reply_tosequencenumber_element = $this->doc->createElement("tosequencenumber");
					$this->reply_errorcode_element = $this->doc->createElement("errorcode");
					$this->reply_notes_element = $this->doc->createElement("notes");
			// End of DOM element creation.	
		}
		else if ($this->doc->loadXML($xml_or_command))  // (READ XML) Message was from remote client to us.  Process the DOM/XML document message and set message variables accordingly.
		{
			echo "Remote Message\n";
			$this->xml_message = $xml_or_command;
			
			$xml_clientid = $this->doc->getElementsByTagName("clientid");
			$this->client_id = trim($xml_clientid->item(0)->nodeValue);
			
			$xml_transactionid = $this->doc->getElementsByTagName("transactionid");
			$this->transaction_id = trim($xml_transactionid->item(0)->nodeValue);
                        
                        $xml_publicip = $this->doc->getElementsByTagName("senderpublicip");
			$this->sender_public_ip = trim($xml_publicip->item(0)->nodeValue);
                        
                        $xml_privateip = $this->doc->getElementsByTagName("senderprivateip");
			$this->sender_private_ip = trim($xml_privateip->item(0)->nodeValue);
                        
                        $xml_key = $this->doc->getElementsByTagName("key");
			$this->key = trim($xml_key->item(0)->nodeValue);
                        			
			$xml_sequence_number = $this->doc->getElementsByTagName("sequencenumber");
			$this->seq_number = trim($xml_sequence_number->item(0)->nodeValue);
			
			$xml_command = $this->doc->getElementsByTagName("cmdname");
			$this->command = trim($xml_command->item(0)->nodeValue);  // Get and set the command.

			$xml_command = $this->doc->getElementsByTagName("notes");
			$this->reply_notes = trim($xml_command->item(0)->nodeValue);  // Get and set the reply notes.
				
			if (array_key_exists($this->command, $this->commands_array))  // Only proceed if the first argument from the client is a recognized command.
			{
				$xml_timestamp = $this->doc->getElementsByTagName("timestamp");
				$this->timestamp = trim($xml_timestamp->item(0)->nodeValue);  // Get and set client xml timestamp.  This is the time right before the xml doc was sent to us.
				
				$xml_args = $this->doc->getElementsByTagName("argument");
				$this->my_argc = $xml_args->length;  // Get how many arguments were supplied for the command.
				
				$xml_reply_to_command = $this->doc->getElementsByTagName("tocommand");
				$this->reply_to_command = trim($xml_reply_to_command->item(0)->nodeValue);
																							
				if (($this->my_argc) === sizeof($this->commands_array[$this->command]) || ($this->my_argc % $this->getExpectedCommandArgSize() === 0)) // Make sure the # of args supplied matches # of args expected for the command.
				{																																				  // The Modulus "%" is used for commands that may have multiples of the same arguments.
					foreach($xml_args as $this_xml_arg)  // Process each argument.																				  // for example, the PRESENTSHARES can have multiple shares presented and therefore the absolute # of args comparison would faile here.
					{
						$xml_argument = $this_xml_arg->getElementsByTagName("argname");
						$argument = trim($xml_argument->item(0)->nodeValue);
						if (array_key_exists($argument, $this->commands_array[$this->command]))  // Determinie if the argument is a key of the subarray for the command specified.
						{
							$values_array = null;
							$xml_value = $this_xml_arg->getElementsByTagName("argvalue");
							$argument_value = trim($xml_value->item(0)->nodeValue);
							$this->setArgumentValue($argument, $argument_value);
						}
						else
						{
							// Need to send an error to the client.
							throw new Exception("ERROR,  Argument " . $argument . " is not valid for command " . $this->command);
						}											   
					}
				}
				else
				{
					// Need to send an error to the client.
					throw new Exception("ERROR, Number of arguments passed does not match number of arguments expected for command " . $this->command);
				}
			}
			else
			{
				// Need to send an error to the client.
				throw new Exception("ERROR, " . $this->command . " is not a valid command.");
			}
		} // End of origin_location == "external"
	}
	
	function appendTransmitFile($file, $size, $journal_date)  //  $file is the complete path and name of file from db.
	{
		$file_node_list = $this->doc->getElementByTagName("filelist");
		if ($file_node_list->length === 0)  // We have to create the initial <filelist></filelist> node.
		{
			$this->filelist_node = $this->command_node->appendChild($this->filelist_element);
		}
		// Append <file> nodes to the <filelist> node.
		$this->file_node = $this->filelist_node->appendChild($this->file_element);
			$this->filepath_node = $this->file_node->appendChild($this->filepath_element);
				$this->filepath_node->appendChild($this->doc->createTextNode($file));
			$this->filesize_node = $this->file_node->appendChild($this->filesize_element);
				$this->filesize_node->appendChild($this->doc->createTextNode($size));
			$this->filejournaldate_node = $this->file_node->appendChild($this->filejournaldate_element);
				$this->filejournaldate_node->appendChild($this->doc->createTextNode($journal_date));
	}
	
	private function createHash()
	{
		$hash = hash('md5', microtime());  // Create a unique identifier for a transaction conversation.
		return $hash;
	}
	
	function getCommandKeys()
	{
		return array_keys($this->commands_array[$this->command]);
	}
	
	function getRawMessage()
	{
		return $this->xml_message;
	}
	
	function getCommand()
	{
		return $this->command;
	}
	
	function getTimeStamp()
	{
		return $this->timestamp;
	}
        
        function getSenderPublicIP()  // Returns the Public IP of the remote side
        {
            return $this->sender_public_ip;
        }
        
        function getSenderPrivateIP()  // Returns the Public IP of the remote side
        {
            return $this->sender_private_ip;
        }
        
	function getSocket()
	{
		return $this->socket;
	}

	function getChangedFiles()
	{
		return $this->changed_files_array;
	}
	
	function getClientID()
	{
		return $this->client_id;  // This is the client id of the remote client that we received the message from.
	}
	
	function getClientIP()  // TODO:  Potentially deprecated now that we are storing the client's public and private ip in the xml headers.
	{
            $received_from_ip = explode(":", stream_socket_get_name($this->socket, true));
            return trim($received_from_ip[0]);
	}
        
        function getClientKey()
        {
            return $this->key;
        }
	
	function getClientPortNumber()
	{
		$port_number = array_values(preg_split("/:/", stream_socket_get_name($this->socket, true)));
		return $port_number[1];
	}
	
	/*function getArguments()
	{
		return $this->arguments;
	}*/
	
	function getArgumentValue($argument)  // Returns a value for a single argument.  Don't use on commands that have multiples for the same argument.  i.e. PRESENTSHARES can have multiple entries for sharename, etc.
	{
		//var_dump($this->arguments);
		if (array_key_exists($argument, $this->arguments))
		{
			return $this->arguments[$argument];  // Return the value of the argument found.
		}
		else
		{
			return false;
		}
	}
	
	function getCommandArgumentsValues()  // Returns an associative array of all arguments and values for a command.
	{
		return $this->arguments;
	}
	
	// IMPORTANT:  This method is deprecated since the commands_array is an associative array.  No numicals indexing is used.
	/*function getCommandArgumentAtIndex($index) // Returns the name of the command argument at the specified position in the argument array for the command.
	{
		$count = 0;
		$arg_name = null;
		foreach ($this->commands_array[$this->command] as $this_argname)
		{
			if ($count == $index)
			{
				$arg_name = $this_argname;
			}
		}
		return($arg_name);
	}*/
	
	private function getExpectedCommandArgSize()  // Returns the number of arguments expexted for a command.
	{									  // Only works on commands with a max of 2d arrays for its arguments.
		$command_array = $this->commands_array[$this->command];
		$arg_count = sizeof($this->commands_array[$this->command]);  // Get initial size of the args for this command.
		
		// Below logic is not used at this point.  It is used if parameters for a command are themselves arrays.
		for ($i = 0; $i < $arg_count; $i++)
		{
			if(is_array($command_array[$i]))
			{
				$arg_count--;  // Reduce the argument count for this command since the argument at this location is an array and not an argument.
				$arg_count = $arg_count + sizeof($command_array[$i]);
			}
		}
		return $arg_count;
	}
	
	function getErrorCode()
	{
		return $this->reply_errorcode;
	}
	
	function getReplyToCommand()
	{
		return $this->reply_to_command;
	}

	function getReplyNotes()
	{
		return $this->reply_notes;
	}
	
	function getSequenceNumber()
	{
		return $this->seq_number;
	}

		
	function getTransactionID()
	{
		return $this->transaction_id;
	}
	
	function isCommandValid($command)
	{
		return (array_key_exists($command, $this->commands_array));
	}
	
	// IMPORTANT:  This method is deprecated since the arguments array is an associative array at its root.
	/*private function setArgument($argument)
	{
		//$this->arguments[][0] = $argument;
		$this->arguments[] = array($argument => NULL);
		//echo "Argument is: " . $argument;
	}*/
	
	
	private function setDataPayload($data_payload)
	{
		$this->data_payload = $data_payload;
	}
	
	function setSequenceNumber($sequence_number)
	{
		$this->seq_number = $sequence_number;
	}
	
	function setTransactionID($transaction_id)
	{
		$xml_transactionid = $this->doc->getElementsByTagName("transactionid");  // Retrieve the DOMNode from the DOMDocument.
		$xml_transactionid->item(0)->nodeValue = $transaction_id; // Set the DOMNode value with the passed transaction id.
		
                if ($this->xml_message != null)  // Rebuild the xml string only if it already exists.  Not for new messages.
                {
                    $this->saveXML();  // Write the changes to the actual XML string.
                }
		$this->transaction_id = $transaction_id;  // Set the object variable.
	}
	
	
	// IMPORTANT!!
	// The below methods should only be used when setting attributes on a message to transmit.
	//
	
	function setArgumentValue($argument, $value)
	{
            if ($this->command != null)  // The commmand should be set first before trying to set its arguments and values.
            {
		if (array_key_exists($argument, $this->commands_array[$this->command]))  // Make sure this is a valid argument for the given command.
		{
			if ($argument == "CLIENTID")  // We don't permit setting the CLIENTID manually.  This is set automatically in the constructor.
			{
				echo "WARNING:  Manually setting this machine's client ID via Message::setArgumentValue() is not permitted.  FYS!\n";
			}
			if (!is_array($this->arguments))  // This is the first argument for the argument array.
			{
				$this->arguments[$argument] = $value;
			}
			else if (!array_key_exists($argument, $this->arguments))
			{
				$this->arguments[$argument] = $value;
			}
			else
			{
				echo "WARNING:  You are modifying an already set attribute!\n";
			}
		}
		else 
		{
			echo "ERROR:  Message::setArgumentValue() - Argument " . $argument . " is not valid for command " . $this->command . "\n";
		}
            }
            else
            {
                echo "EROR:  Message::setArgumentValue() - The can't set argument values when the command has not been set.\n";
            }
	}

	public function setSocket($socket)
	{
		$this->socket = $socket;
	}
	
	// WARNING!:  The below method is a shortcut on setting the arguments and their values by setting them in one call.
	//			  Use this function with care!!  If you can, always try and use the setArgumentValue() method instead.
	//			  This method may be deprecated in the future.
	function setArgumentValues($argument_and_values_array)  // Accepts an associative array with the arguments as the array keys and the values as the key values.
	{										
			$this->arguments = $argument_and_values_array;
	}

	function setClientID($client_id)
	{
		$xml_clientid = $this->doc->getElementsByTagName("clientid");
		$xml_clientid->item(0)->nodeValue = $client_id;
                if ($this->xml_message != null)  // Rebuild the xml string only if it already exists.  Not for new messages.
                {
                    $this->saveXML();  // Write the changes to the actual XML string.
                }
		$this->client_id = $client_id;
	}
	
	function setCommand($command)  // Used when sending messages.  Builds the <command></command> part of the XML message.
	{
		if ($this->command == null)  // Can remove this if statement when we determine why the command is being double set.
		{
			if (array_key_exists($command, $this->commands_array))  // Only proceed if the first argument from the client is a recognized command.
			{
				$this->command = $command;
				return true;
			}
			else
			{
				echo "FATAL ERROR:  Message::setCommand(command) - Provided parameter is not a valid command!\n";
				return false;
			}
		}
		else
		{
			echo "FATAL ERROR:  Message:: setCommand - The command has already been set for this message object.  Please fix the bug.\n";
			exit(1);
		}
	}
	
	function setReplyToCommand($reply_prev_command)
	{
		$this->reply_to_command = $reply_prev_command;
	}
	
	function setReplyToSequenceNumber($reply_sequence_number)
	{
		$this->reply_to_sequence_number = $reply_sequence_number;
	}
	
	function setReplyErrorCode($reply_error_code)
	{
		//$this->transmit_reply_errorcode = $reply_error_code;
		$this->reply_errorcode = $reply_error_code;
	}
	
	function setReplyNotes($reply_notes)
	{
		$this->reply_notes = $reply_notes;
	}
	
	function buildTransmitMessage()  // This is the last method that should be called when creating a transmit message.  This actuall builds the xml.
	{
		// REMEMBER:  The transaction class increments the sequence number and therefore, we don't increment it here.
		$client_id = $this->sid;  // The $this->sid is set from the included conf/afrs_vars.php file in the constructor.
		$this->message_node = $this->doc->appendChild($this->message_element);
			$this->client_node = $this->message_node->appendChild($this->client_element);
				$this->client_node->appendChild($this->doc->createTextNode($client_id));
			$this->transaction_node = $this->message_node->appendChild($this->transaction_element);
				$this->transaction_node->appendChild($this->doc->createTextNode($this->transaction_id));
                        $this->key_node = $this->message_node->appendChild($this->key_element);
				$this->key_node->appendChild($this->doc->createTextNode($this->key));
			$this->sequence_node = $this->message_node->appendChild($this->sequence_element);
				$this->sequence_node->appendChild($this->doc->createTextNode($this->seq_number));
			$this->timestamp_node = $this->message_node->appendChild($this->timestamp_element);
				$this->timestamp_node->appendChild($this->doc->createTextNode(time()));
                        $this->sender_privateip_node = $this->message_node->appendChild($this->sender_private_ip_element);
				$this->sender_privateip_node->appendChild($this->doc->createTextNode($this->sender_private_ip));
                        $this->sender_publicip_node = $this->message_node->appendChild($this->sender_public_ip_element);
				$this->sender_publicip_node->appendChild($this->doc->createTextNode($this->sender_public_ip));
			$this->command_node = $this->message_node->appendChild($this->command_element);
						$this->cmd_node = $this->command_node->appendChild($this->cmd_element);
							$this->cmd_node->appendChild($this->doc->createTextNode($this->command));
							
			//if ($this->args_values_array != null)
			if ($this->arguments != null)
			{	
				$this->arguments_node = $this->command_node->appendChild($this->arguments_element);  // Create the <arguments></arguments> part of the XML file.
				
				//foreach($this->args_values_array as $this_assoc_array)
				$key_index = 0;
				foreach($this->arguments as $this_arg) // Remember that $args_values_array is a 2D associative array.
				{																// i.e. there are associative arrays within an indexed array.
					$key_names = array_keys($this->arguments);
					
					${"argument_element".$key_index} = $this->doc->createElement("argument");
					${"argument_node".$key_index} = $this->arguments_node->appendChild(${"argument_element".$key_index});  // Create the subnode <argument></argument> part of the XML file.
						${"argname_element".$key_index} = $this->doc->createElement("argname");
						${"argname_node".$key_index} = ${"argument_node".$key_index}->appendChild(${"argname_element".$key_index});  // Create subnode <argname></argname>.
						${"argname_node".$key_index}->appendChild($this->doc->createTextNode($key_names[$key_index]));  // Set the actual value of <argname></argname>.
						${"argvalue_element".$key_index} = $this->doc->createElement("argvalue");
						${"argvalue_node".$key_index} = ${"argument_node".$key_index}->appendChild(${"argvalue_element".$key_index});  // Create subnode <argvalue></argvalue>.
						${"argvalue_node".$key_index}->appendChild($this->doc->createTextNode($this_arg));  // Set value for <argvalue></argvalue>.

					$key_index++;
				}	
			}

			// Build the <shares> part of the message if this message's command is PRESENTSHARES.
			
			
			$this->reply_node = $this->message_node->appendChild($this->reply_element);
				$this->reply_tocommand_node = $this->reply_node->appendChild($this->reply_tocommand_element);
					$this->reply_tocommand_node->appendChild($this->doc->createTextNode($this->reply_to_command));
				$this->reply_errorcode_node = $this->reply_node->appendChild($this->reply_errorcode_element);
					$this->reply_errorcode_node->appendChild($this->doc->createTextNode($this->reply_errorcode));
				$this->reply_notes_node = $this->reply_node->appendChild($this->reply_notes_element);
					$this->reply_notes_node->appendChild($this->doc->createTextNode($this->reply_notes));
		
		$this->saveXML();
	}
	
	private function saveXML()  // This should be the final method called after using "set" methods.  This builds the xml and sets the xml_message variable for sending to a client.
	{
            $this->xml_message = $this->doc->saveXML();
	}
        
        final public function isNated()
        {
            /*
             * If the IP address that the connection was received from is different from private 
             * IP address in the XML message, then yes, this client is nated.
             * 
             * Therefore, if isNated() is true then use the IP from $this->getClientIP(), otherwise 
             * use the $this->getSenderPrivateIP();
             */
            
            echo ":::: Message socket IP is " . $this->getClientIP() . " and XML Private IP is " . $this->getSenderPrivateIP() . "\n";
            
            if ($this->getClientIP() != $this->getSenderPrivateIP())
            {
                return true;
            }
            else
            {
                return false;
            }
            
            
            // TODO:  Need to think if the below code is needed to determine nat.
            // For nated non-public-facing sync partners.
            /*if ($sender_ip_address_from_socket == $sender_public_ip_address && $sender_public_ip_address != $sender_private_ip_address)
            {
                $nated = 1;
            }*/
            // For non-nated non-public-facing sync partners.
            /*else if ($sender_ip_address_from_socket != $sender_public_ip_address && $sender_ip_address_from_socket == $sender_private_ip_address)
            {
                $nated = 0;
            }*/
            // For non-nated public-facing sync partners.
            /*else if ($sender_ip_address_from_socket == $sender_public_ip_address && $sender_ip_address_from_socket == $sender_private_ip_address)
            {
                $nated = 0;
            } */         
        }
}
?>