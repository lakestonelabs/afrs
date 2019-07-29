<?php

require_once(realpath("../includes/system/Dispatcher.php"));
require_once(realpath("../includes/system/ConnectionManager.php"));

// NOTE:  This is not used anymore. Use StreamSocketServer instead.  All future communications on the daemon side will be encrypted (SSL).
//		  Therefore, stream sockets were chosen instead and are easier to use than low-level sockets.  They (stream sockets)
//		  also support creating a ssl server right out of the box because they are at a higher level than plain sockets.
// http://marc.info/?l=php-internals&m=121716684606195&w=2
// Note:  Ticks will be going away/deprecated as of php version 5.3.0.  Use pcntl_signal_dispatch() function
// 		  instead when modifying this for future verions where ticks don't exist.  For now we use ticks.

Class SocketServer
{
	protected	$server_socket,
				$client_socket,
				$socket_result,
				$process_ids = array(),
				$server_listening = false,
				$input_buffer = null,
				$dispatcher = null,  // The conn_manager will handle the bulk of the communication.  The SocketServer 
									   // is only used to handle the initial connection from the client and then 
									   // read and write to and from the client.
				$conn_manager = null;
				
	private		$sleep_interval = 5000;  // How long the server waits in milliseconds between checking for new connections.
	
	function __construct($address, $port)
	{
		declare(ticks = 1);
		
		// create socket
		$this->server_socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");
		
		// bind socket to port
		$this->socket_result = socket_bind($this->server_socket, $address, $port) or die("Could not bind to socket\n");
		
		// start listening for connections
		$this->socket_result = socket_listen($this->server_socket, 3) or die("Could not set up socket listener\n");
		
		// accept incoming connections
		// spawn another socket to handle communication
		socket_set_nonblock($this->server_socket);
	}
	
	function start()
	{
		$this->server_listening = true;
		$this->conn_manager = new ConnectionManager();  // Create a conneciton manager to keep track of the individual connectons.
		
		/* handle signals */  // See note about the deprecation of ticks as of php 5.3.0.
		// Note: Below to specify the function to call if it a method you have to use the two element array.  Fucking stupid!!.
		pcntl_signal(SIGCHLD, array($this, 'sig_handler'));
		pcntl_signal(SIGTERM, array($this, 'sig_handler'));
		pcntl_signal(SIGINT, array($this, 'sig_handler'));
		
		while($this->server_listening == true)
		{
			$this->client_socket = @socket_accept($this->server_socket);
			if ($this->client_socket > 0)
			{
				if ($this->conn_manager->canConnect())  // Check to see if we can accept more connections before we fork.
				{
					$fork_pid = pcntl_fork();
					
					if ($fork_pid == -1)
					{
						echo "Failed to create child.  Committing suicide!\n";
						exit(1);
					}
					else if ($fork_pid == 0)
					{
						// I'm the child
						// read client input
						$this->server_listening = false;  // Forked child does not listen for any more connections and dies after processing input.
						socket_close($this->server_socket); // Close the server socket since children only need the client socket connection and will die after processing.
						
						// Send the client the welcome screen.
						$output = "AFRS ver. 0.1alpha (Advanced File Replication Service)\n";
						socket_write($this->client_socket, $output, strlen($output)) or die("Could not write output\n");
						
						// Create a new ConnectionManager object to handle the rest of the communication transaction.
						$this->dispatcher = new Dispatcher();
						
						while(true)  // Loop and keep reading input.
						{
							$this->input_buffer = socket_read($this->client_socket, 65535) or die("Could not read input\n");
							// clean up input string
							$this->input_buffer = trim($this->input_buffer);
							if ($this->input_buffer != "quit")
							{
								// Now that we have input from a client, we need to handle it effectively.  Send it to the Connection Manager.
								$this->dispatcher->handleInput($this->input_buffer);  // Write the client input to the connectio manager to interperet.
							}
							else
							{
								$this->dispatcher->quit();
								$this->closeClientSocket();
								break;  // Break out of the while loop.
							}
						}
					}
					else
					{
						// I am the parent.
						// Since we are here we know that the last connection attempt succeeded.  So increment the connection counter.
						$this->conn_manager->incrementConnectionCount();  // Increment the number of clients/connections currently in process.
						echo "Current connections: " . $this->conn_manager->getConnectionCount() . "\n";
						$this->process_ids[] = $fork_pid;  // Store the pid of the forked child process.
						socket_close($this->client_socket);
					}
				}
				else  // We can't accept any more connections.  Tell the client to go away.
				{
					$output = "AFRS ver. 0.1alpha (Advanced File Replication Service)\nNo more connections accepted at this time.\n";
					socket_write($this->client_socket, $output, strlen($output)) or die("Could not write output\n");
					$this->closeClientSocket();
				}
			}
			else if ($this->client_socket === false)
			{
				usleep($this->sleep_interval);
			}
			else
			{
				echo "error: ".socket_strerror($client_socket);
			    die;
			}
		}  // End of server_listening while loop
		
	}  // End of start() method
	
	//  This will stop the entire socket server (client and server sockets).
	function stop()
	{
		$this->server_listening = false;
		socket_close($this->server_socket);  // Close the parent server socket.
	}
	
	function closeClientSocket()
	{
		echo "Closing client socket\n";
		// close spawned socket
		socket_close($this->client_socket);
	}
	
	function sendToClient($output)
	{
		socket_write($this->client_socket, $output, strlen($output)) or die("Could not write output to client\n");
	}
	
	/**
	  * Signal handler
	  */
	function sig_handler($sig)
	{
		//global $current_connections;
		
	    switch($sig)
	    {
	        case SIGTERM:
	        case SIGINT:
	            exit();
	        break;
	
	        case SIGCHLD:
	        	//echo "WAITPID Called for Child\n";
	            pcntl_waitpid(-1, $status);
	       
	            $this->conn_manager->decrementConnectionCount();
	            echo "Current connections end: " . $this->conn_manager->getConnectionCount() . "\n";
	
	        break;
	    }
	}
}
?>