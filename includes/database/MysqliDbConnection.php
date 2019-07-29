<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("DbConnectionAbstract.php");

Class MysqliDbConnection extends DbConnectionAbstract
{
	
	function __construct($server, $dbname, $username, $password)	//Returns type: resource link.
	{
		parent::__construct($server, $dbname, $username, $password);	//Set our object variables.
		$this->dblink = new mysqli($server, $username, $password, $dbname); //Make the database connection.	
                
                /*
                 * Use this instead of $connect_error if you need to ensure
                 * compatibility with PHP versions prior to 5.2.9 and 5.3.0.
                 */
                if (mysqli_connect_error()) 
                {
                    die('FATAL ERROR: MysqliDbConnection::__construct - Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
                }
	}
	
	function setDatabase($new_dbname)	//Returns type boolean
	{
		$this->dbname = $new_dbname;
		if ($this->dblink->select_db($this->dbname))	//Set the database to use.
		{
			return true;
		}
		else
		{
			echo "ERROR: MysqliDbConnection::setDatabase`` - Could not set database\n";
		}
	}
	
	function getDbLink()
	{
		return $this->dblink;
	}
	
	function disconnect()
	{
		$this->__destruct();
	}
	
	function __destruct()
	{
		//echo "MysqlDbConnection::destructor called\n";
		//mysql_close($this->dblink);
	}
}
?>