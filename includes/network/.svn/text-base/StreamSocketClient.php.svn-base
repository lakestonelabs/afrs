<?php

/*
 * Usage
 *
 * This class implements a generic socket-based client.  The client initiates a connection to the server.  It then
 * simply appends the server socket and data to a 2-d array.  Then when it's done listening it returns the
 * 2-d array.
 *
 * This is essentially a cop of the StreamSocketServer class with some modifications/removals to accomidate for a simpler
 * client-based scenario.
 *
 */



/*
 * Changes
 * 
 */

Class StreamSocketClient
{
	protected		$client_socket,
				$sockets_to_remote_servers = null,  // Holds an array of sockets used to make connections to remote servers.
											 // This is usually only used with the stream_select() method/function to
											 // block for input from remote servers.  See php nativestream_select() method.
				$read = null,
				$write,
				$mod_fd = null,
				$data_buffer = null;

	private		$sleep_interval = 100000;  // How long the server waits in milliseconds between checking for new connections.
										   // TODO  The sleep_interval should be stored and read from the db.

	// Returns type ConnectionManager
	function __construct($address, $port, $timeout)
	{
		// create a stream socket
		$this->client_socket = stream_socket_client("tcp://$address:$port", $errno, $errstr, $timeout);

		if ($this->client_socket != false)
		{
			$this->sockets_to_remote_servers[] = $this->client_socket; // Need to store the single socket in an array for stream_select() to accept.
			//return true;  // Successfully connected to the remote server.
		}
		else if ($this->client_socket === false)
		{
			echo "Error in client socket creation: " . $errstr . ":" . $errno . "\n";
			exit(1);
		}
	}

	function  __destruct()
	{
		if (is_resource($this->client_socket))
		{
			return (fclose($this->client_socket));
			echo "Closed server connection to " . $server_info . "\n" . "----------------------------------------------------\n";
		}
		echo "Closed server connection.\n";
	}

	private function listen()
	{
		// Some parts dapted from the StreamSocket class.
		// Remember $this->sockets_to_remote_servers only holds one socket, the socket to the remote server.
		// Needed to use an array here since this is how stream_select works.  See php doco for further details.
		if (sizeof($this->sockets_to_remote_servers) > 0)
		{
			$this->mod_fd = stream_select($this->sockets_to_remote_servers, $_w = NULL, $_e = NULL, 0, $this->sleep_interval);  // Block for input.
		}
		if ($this->mod_fd === false)
		{
			echo "Could not read stream arrays.  Exiting listening loop.\n";
			return(false);
		}

		$sock_data = null;
		$read_size = 0;

		$socket_info = stream_get_meta_data($this->client_socket);
		$unread_data_size = $socket_info["unread_bytes"];  // Dynamically get the size of client data
														   // to send to fread();
		if ($unread_data_size >= 1024 || $unread_data_size === 0)
		{
			$read_size = 1024;  // 1024 was chosen so it does not block too long waiting for data
								// and therefore give better program interaction response time.
		}
		else
		{
			$read_size = $unread_data_size;  // If we don't do this when the unread_bytes < 1024, then fread()
											 // will block until it times-out or until it receives more data
											 // until the buffer becomes 1024 again.  Therefore we need to
											 // dynamically set it.
		}

		$sock_data = fread($this->client_socket, $read_size);
		if ($sock_data === false)
		{
			return false;
		}
		else
		{
			$this->data_buffer .= $sock_data;  // Append the client data for that socket.
		}
	   return(true);
	}  // End of listen() method
	
	function getServerData()  // Returns an array of Message objects.
	{
		$server_messages = null;  // array that holds messages to be returned.

		if ($this->listen())  // This is a little different from the ServerSocket class since we combine the getServerData and listen methods in one.
		{
			if (preg_match('/\n\n/', $this->data_buffer))  // Determine if a complete client message has been read. Denoted by a double return.
			{
				$end = strrpos($this->data_buffer, "\n\n");  // Find the last occurance of a double-line return.  "a" in "abcde" is 0.
				$sub_string = substr($this->data_buffer, 0, $end);  // Get everything up to the last occurance of a line return.
				$sub_messages = preg_split('/\n\n/', $sub_string);  // Many messages can be in one client data string.  This is usually true for afrs-watcher events.
				foreach ($sub_messages as $this_sub_message)  // Many messages can be in one client data string.  This is usually true for afrs-watcher events.
				{
					if (strlen($this_sub_message) > 0)  // Don't create new message objects for the last element in $sub_messages which is always \n\
					{
						try
						{
							echo "//////////////  Received this message from the remote server //////////////\n" . trim($this_sub_message, "\n") . "///////////////////////////////\n";
							$server_messages[] = trim($this_sub_message, "\n");  // Make sure to trim any line returns from the message.
							echo "New message received on: " . time() . "\n";
						}
						catch (Exception $e)
						{
							// Send exception error message to remote server.
							echo $e . "\n";
							return (false);
						}
					}
				}

				if (($end + 2) < strlen($this->data_buffer))  // We have some leftover data from a previously unfinished conversation.
				{
					$leftovers = substr($this->data_buffer, ($end + 2));  // +2 is so we don't reprocess the \n\n from the prior message.
					$this->data_buffer = $leftovers;  // Get the leftovers and store so
																	  // the rest of the conversatioin can be completed
																	  // at the next pass of getting remote server data.
				}
				else
				{
					$this->data_buffer = null;  // Zero out the server data buffer since the current data transaction has completed.
				}
				return $server_messages;
			}
		}
		else
		{
			return false;
		}
	}

	function sendToServer($message)  // Expects raw data to be sent to server.
	{
		if ($message != null)
		{
			echo "//////////////  StreamSocketClient::sendToServer() - Sending this message to remote server //////////////\n" . $message . "///////////////////////////////\n";
			
			$message = $message . "\n\n"; // Make sure to append two line returns so the remote end knows this is a complete conversation.
			if(fwrite($this->client_socket, $message) != false)
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			echo "StreamSocketClient::sendToServer : socket or message variable is null.  Invalid call.  Please correct.\n";
		}
	}
}
?>