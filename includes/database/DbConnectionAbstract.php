<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
Class DbConnectionAbstract
{
	protected $server,
			$username, 
			$password, 
			$dbname,
			$dblink,
			$filename;
	
	function __construct($server, $dbname, $username, $password)
	{
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->dbname = $dbname;
	}
}
?>