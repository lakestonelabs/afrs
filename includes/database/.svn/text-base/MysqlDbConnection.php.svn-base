<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("DbConnectionAbstract.php");

Class MysqlDbConnection extends DbConnectionAbstract
{
	
	function __construct($server, $dbname, $username, $password)	//Returns type: resource link.
	{
		parent::__construct($server, $dbname, $username, $password);	//Set our object variables.
		$this->dblink = mysql_pconnect($this->server, $this->username, $this->password);	//Make the database connection.
		if (!mysql_select_db($this->dbname, $this->dblink))	//Set the database to use.
		{
			echo "ERROR:  Could not set database during connection\n";
		}
		
		if (!$this->dblink)
		{
			die("Could not connect:" . mysql_error());
		}
	}
	
	function setDatabase($new_dbname)	//Returns type boolean
	{
		$this->dbname = $new_dbname;
		if (mysql_select_db($this->dbname, $this->dblink))	//Set the database to use.
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
		mysql_close($this->dblink);  // Note, this is useless when utilizing persistant connections.
	}
	
	function __destruct()
	{
		//echo "MysqlDbConnection::destructor called\n";
		//mysql_close($this->dblink);
	}
}
?>