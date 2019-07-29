<?php
/* 
 * This class is used as an abstraction layer so that a script or driver program can communicate with the local or
 * remote afrs daemon without having to do all of the heavy lifting associated when communicating with the local and/or
 * remote server(s), such as managing transactions, creating afrs-based xml messages, and dispatching them.  Think of
 * it as a client API.
 *
 * When this class is invoked by a client script, such as the afrs-config and/or afrs-watcher programs, this class will
 * return simple events such as true/false or text-based.  This way these "driver" programs/scripts can focus on their
 * main tasks and not get bogged-down with all of the complicated tasks needed to take part in a network-based
 * client-server communication dialogue.
 *
 */

require_once(__DIR__."/StreamSocketClient.php");
require_once(__DIR__."/../system/Dispatchers/Dispatcher.php");
require_once(__DIR__."/../system/Messages/Message.php");
require_once(__DIR__."/../system/MessageQueue.php");
require_once(__DIR__."/../system/Transaction.php");

Class AfrsClient
{
	// vars here
	private $socket = null,
		$dispatcher = null;

	// methods here

	public function __construct($remote_address, $port, $timeout)
	{
		$this->socket = new StreamSocketClient($remote_address, $port, $timeout); // Create a new client connection to the remote host.
		if (!is_object($this->socket))
		{
			echo "AfrsClient::__construct - FATAL ERROR:  Could not connect to server" . $remote_address.":" . $port . " .\n";
			return false;
		}
		$this->dispatcher = new Dispatcher();
	}

	public function  __destruct()
	{
		
	}

	public function registerNewSyncPartner($parameters_array)  // Returns true if successfull or a message object containing the error.
	{
		if (is_array($parameters_array))
		{
                    return $this->automateServerDialogue("REGISTER", $parameters_array);
		}
		else
		{
			echo "AfrsClient::registerNewSyncPartner() - Method expects an array.\n";
			return false;
		}
	}

	public function unRegisterSyncPartner($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function editSyncPartner($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function addNetworkDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function removeNetworkDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function editNetworkDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function addStorageDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function removeStorageDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function editStorageDevice($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function addWatch($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function editWatch($parameters_array)
	{
		if (true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
        
        public function getRemoteShares()
        {
            return $this->automateServerDialogue("GETSHARES", array()); // Send an empty array since GETSHARES dommand does not require extra parameters.            
        }

	private function waitForServerResponse()  // Returns an array of afrs Message objects.
	{
		$message_obj_array = null;
		$server_response = false;

		while($server_response === false)  // Keep polling for a response until we get one.
		{
			$server_response = $this->socket->getServerData();
		}

		// Now we process the response.
		if (is_array($server_response))  // Make sure the response is in the form of an array.
		{
			foreach ($server_response as $this_server_response)
			{
				$message_obj_array[] = new Message($this->socket, $this_server_response);
			}
			return $message_obj_array;
		}
	}

	// Returns true if dialogue comleted successfully, or returns a message object containing the error.
	private function automateServerDialogue($command, $parameters_array)
	{
		if (func_num_args() == 2)  // Determine how we were called.
		{                                                 
			if (is_array($parameters_array))
			{
				// IMPORTANT:  Since this is a proxied message, we set the messages parameters equal to the settings of the remote
				//			   side.  The Dispatch::receivedRegisterMemberServer() method will notice this is a proxied message, use the provided info in the message
				//			   to determine how to communicated with the remove side and then will replace this message's parameters
				// 			   with this server's local information.

				

				$next_message_obj_to_send = new Message($this->socket);
                                $command_dialogue_array = $next_message_obj_to_send->dialogue_array[$command];
				$next_message_obj_to_send->setCommand($command);
				$next_message_obj_to_send->setArgumentValues($parameters_array);
                                
				$transaction = new Transaction($next_message_obj_to_send);  // Create a new transaction for this operation.
				//$next_message_obj_to_send->buildTransmitMessage();

                                while($next_message_obj_to_send->getCommand() != "DISCARD") // The conversation is not done.
				{
					if ($transaction->getSize() == 1)
					{
						// Need to kick off the discussion if this is the first message in the Transaction.
						//echo "STARTING TRANSACTION\n";
						$this->socket->sendToServer($next_message_obj_to_send->getRawMessage());
					}
					$response_array = $this->waitForServerResponse();
					if (sizeof($response_array) == 1)  // We should only receive one message back from the server.
					{
                                                $response_message = $response_array[0];
						$transaction->addMessage($response_message);  // Add the server's response to the transaction.
                                                
                                                if ($response_message->getCommand() == "PRESENTSHARES")
                                                {
                                                    
                                                }
                                                
                                                
                                                
                                                $next_command_to_send = $command_dialogue_array[$transaction->getPreviousMessageCommand()][$transaction->getNewestMessageCommand()];
                                                $next_message_obj_to_send = new Message($this->socket);
						$next_message_obj_to_send->setCommand($next_command_to_send);
						$next_message_obj_to_send = $transaction->addMessage($next_message_obj_to_send);
                                                
                                                if ($next_message_obj_to_send->getCommand() != "DISCARD")
                                                {
                                                    echo "---> SENDING THIS MESSAGE TO REMOTE " . $next_message_obj_to_send->getCommand() . "\n";
                                                    if (!$this->socket->sendToServer($next_message_obj_to_send->getRawMessage()))
                                                    {
                                                            echo "AfrsClient::automateDialogue() - Error when sending message to server.\n";
                                                            return false;
                                                    }
                                                }
					}
					else
					{
						echo "AfrsClient::automateDialogue() - Response from server was more than one message.  Developers need to fix!\n";
						return false;
					}
				}

				$search_for_errors_obj = $transaction->searchForCommand("ERROR");
				if (is_object($search_for_errors_obj))  // An error occured in the transaction for this message.
				{
					return $search_for_errors_obj;

				}
				else if (!$search_for_errors_obj)  // No errors occured for this transaction, return true.
				{
					return true;
				}
				
			}
			else
			{
				echo "AfrsClient::automateDialogue() - method expects an array.\n";
				return false;
			}
		}
		else
		{
			echo "AfrsClient::automateDialogue() - Method expects two parameters.\n";
			return false;
		}
	}
}

?>
