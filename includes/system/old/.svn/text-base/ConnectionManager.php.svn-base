<?php

require_once(realpath("../includes/system/Dispatcher.php"));

Class ConnectionManager
{
	private		$connection_count = null,
				$max_connections = null,  // Possibly get this info from the tbl_registry db table.
				$client_sockets = array(), 
				$client_data_buffer = array(array()),  // 2d array that holds the client sockets and their data.
												   // [1][0] => client 1's socket. ([0][0] is always the server socket.)
												   // [1][1] => client 1's data.  The client data element is appended to 
												   // itself until a full conversation has been completed.  Then the 
												   // complete conversation is processed.
				$dispatcher = null;
	
	function __construct($max_connections)
	{
		$this->max_connections = $max_connections;
		$this->connection_count = 0;
		$this->dispatcher = new Dispatcher();
		return(true);
	}
	
	function &getRawAccess()  // Allows the raw access to the $client_sockets array (by reference).  Use this caution.  Only StreamSocketServer should use this.
	{
		return $this->client_sockets;  // Cannot be return ($this->client_sockets) .  See php return by reference doco.
	}
	
	// Returns type boolean.
	function canConnect()
	{
		if ($this->connection_count < $this->max_connections)
		{
			return(true);
		}
		else
		{
			echo "Maximum connections reached: " . $this->max_connections . "\n";
			return(false);
		}
	}
	
	// Returns type Dispatcher.
	function registerClient($client_socket)
	{
		if ($this->connection_count < $this->max_connections)  //  Accepts type socket.  Returns type bool.
		{
			//echo "Registering client: " . $client_socket . "\n";
     		// Get the client's network info (ip:port).
     		$client_info = stream_socket_get_name($client_socket, true);
     		$this->connection_count++;
     		$this->client_sockets[] = $client_socket;
			$this->client_data_buffer[$this->connection_count][0] = $client_socket;  // Append the client socket to the rest of the client data array.
			//echo "Client " . $this->connection_count . " registered from: " . $client_info . "\n";
			//var_dump($this->client_sockets);
			//var_dump($this->client_data_buffer);
			return(true);
		}
		else
		{
			return(false);
		}
	}
	
	function unregisterClient($client_socket)  //  Accepts type socket.  Returns type bool.
	{
		//echo "Unregistering client: " . $client_socket . "\n";
		$key_to_del = array_search($client_socket, $this->client_sockets, true);  // Find the array position for the client.
		$client_info = stream_socket_get_name($client_socket, true);
		fclose($this->client_sockets[$key_to_del]);  // Must do this on the array not just the socket since the array is what a record keeper for all connections.
		unset($this->client_sockets[$key_to_del]);  // Must do this on the array not just the socket since the array is what a record keeper for all connections.
		$this->connection_count--;
		echo "Client connection closed from " . $client_info . "\n";
		//var_dump($this->client_sockets);
		return(true);
	}
	
	function readClientData($client_socket)
	{
		$sock_data = null;
		
		// Below is the communication with the client after the master has connected to the client 
		$sock_data = fread($client_socket, 1024);  // Using 1024 so that multi-client response is faster.
		if (strlen($sock_data) === 0) // Entire client connection closed.
		{ 
			if (!$this->unregisterClient($client_socket))  // First unregister the client from the AFRS server..
			{
				echo "ERROR trying to unregister client from the Connection Manager\n";
			}
		}
		else if ($sock_data === false) 
		{
			echo "Something bad happened while reading the client input.\n";
			$this->unregisterClient($client_socket);
			
		} 
		else
		{
			$this->appendClientData($client_socket, $sock_data);
		}
	}
	
	private function appendClientData($client_sock, $sock_data)  // Append data to the current data stream for a particular client.
	{
		for($i=1; $i <= sizeof($this->client_data_buffer); $i++)  // Note $this->client_data_buffer[0][0] & [0][1] is always blank.
		{
			if ($this->client_data_buffer[$i][0] === $client_sock)  // Don't just match on name(text), match also on type (===),
			{
				$this->client_data_buffer[$i][1] .= $sock_data;  // Append the client data for that socket.
				//echo "Received data from: " . $this->client_data_buffer[$i][0] . "\n";
				/*if (preg_match('/\r?\n\r?\n/', $this->client_data_buffer[$i][1]))  // Determine if the client has finished the current converstation with a double return.
				{
					//echo "Client ended current data transaction. (conversation still going...)\n";
					$this->dispatcher->dispatch($this->client_data_buffer[$i][1]);  // If the current conversation is complete then send the data to be dispatched.
					$this->client_data_buffer[$i][1] = null;  // Zero out the client data buffer since the current dat transaction has completed.
				}*/
			}
		}
	}
	
	function getConnectionCount()
	{
		return($this->connection_count);
	}
	
	// Closes all connection stored in the $client_sockets array.
	// Does not actually close connections on the StreamSocketServer.
	// This method should be called from the StreamSocketServer class when it 
	// receives the call to its stop() method.
	function closeConnections()
	{
		echo "Closing client connections\n";
		// close spawned socket
		//socket_close($this->client_socket);
		foreach($this->client_sockets as $client_socket)
		{
			$was_closed = fclose($client_socket);
			if ($was_closed)
			{
				echo "Successfully closed client socket\n";
			}
			else
			{
				echo "Could not close client socket\n";
			}
		}
	}
}
?>