<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("DbConnectionAbstract.php");

Class MssqlDbConnection extends DbConnectionAbstract
{
	
	function __construct($server, $dbname, $username, $password)	//Returns type: resource link.
	{
		parent::__construct($server, $dbname, $username, $password);	//Set our object variables.
		$this->dblink = mssql_pconnect($this->server, $this->username, $this->password);	//Make the database connection.
		if (!mssql_select_db($this->dbname, $this->dblink))	//Set the database to use.
		{
			echo "ERROR:  Could not set database during connection\n";
		}
		
		if (!$this->dblink)
		{
			die("Could not connect\n");
		}
	}
	
	function setDatabase($new_dbname)	//Returns type boolean
	{
		$this->dbname = $new_dbname;
		if (mssql_select_db($this->dbname, $this->dblink))	//Set the database to use.
		{
			return true;
		}
		else
		{
			echo "ERROR:  Could not set database\n";
		}
	}
	
	function getDbLink()
	{
		return $this->dblink;
	}
	
	function disconnect()
	{
		mssql_close($this->dblink);
	}
	
	function __destruct()
	{
		mssql_close($this->dblink);
	}
}
?>