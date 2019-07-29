<?php
//	DbConnectionWrapper.php	
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
//
//	This file is meant as an abstraction to the rest of the DbConnection Classes 
//	in that this class will make all of the necessar and correct db calls 
//	for all of the supported db types.  Therefore, in your code you don't
//	have to change the include when dealing with different databases.
//

require_once ("MysqlDbConnection.php");
require_once ("MysqliDbConnection.php");
require_once ("SQLiteDbConnection.php");
require_once ("MssqlDbConnection.php");

Class DbConnectionWrapper
{	
	private	$dbtype = null,
			$server = null,
			$dbname = null,
			$username = null,
			$password = null,
			$dbfile = null,
			$myDbConnObject = null;
	
	function __construct($dbtype)
	{	
		$this->dbtype = $dbtype;
		
		// Test if we were called for type SQLite and passed the db file.
		if ($this->dbtype == "sqlite") 
		{
			if (func_num_args() == 2)
			{
				$this->dbfile = func_get_arg(1);
				
				if(!file_exists($this->dbfile))
				{
					echo "WARNING opening SQLite db file: " . $this->dbfile . " does not exists.  Creating it now.\n";
				}
				$this->myDbConnObject = new SQLiteDbConnection($this->dbfile);
			}
			else
			{
				echo "ERROR: You did not specify the db filename as the second argument: new SQLiteDbConnection(\$type, \$dbfile)\n";
				echo "Usage or type sqlite: new DbConnectionWrapper(\$type, \$dbfile)\n";
			}
		}
		
		// Test if we were called for type Mysql and test/get the corresponding parameters.
		else if ($this->dbtype == "mysql")
		{
			if (func_num_args() == 5)
			{
				if (func_get_arg(1) != null && func_get_arg(2) != null && func_get_arg(3) != null)
				{
					$this->server = func_get_arg(1);
					$this->dbname = func_get_arg(2);
					$this->username = func_get_arg(3);
					$this->password = func_get_arg(4);
					
					$this->myDbConnObject = new MysqlDbConnection($this->server, $this->dbname, $this->username, $this->password);
				}
				else
				{
					echo "ERROR creating Mysql database connection.  You forgot to specify a host or username or passowrd.\n";
					echo "Usage or type mysql: new DbConnectionWrapper(\$type, \$server, \$dbname, \$username, \$password)\n";
				}
			}
		}
                else if ($this->dbtype == "mysqli")
		{
			if (func_num_args() == 5)
			{
				if (func_get_arg(1) != null && func_get_arg(2) != null && func_get_arg(3) != null)
				{
					$this->server = func_get_arg(1);
					$this->dbname = func_get_arg(2);
					$this->username = func_get_arg(3);
					$this->password = func_get_arg(4);
					
					$this->myDbConnObject = new MysqliDbConnection($this->server, $this->dbname, $this->username, $this->password);
				}
				else
				{
					echo "ERROR creating Mysqli database connection.  You forgot to specify a host or username or passowrd.\n";
					echo "Usage or type mysql: new DbConnectionWrapper(\$type, \$server, \$dbname, \$username, \$password)\n";
				}
			}
		}
		else if ($this->dbtype == "mssql")
		{
			if (func_num_args() == 5)
			{
				$this->server = func_get_arg(1);
				$this->dbname = func_get_arg(2);
				$this->username = func_get_arg(3);
				$this->password = func_get_arg(4);
				
				$this->myDbConnObject = new MssqlDbConnection($this->server, $this->dbname, $this->username, $this->password);
			}
			
		}
	}
	
	function setDatabase($new_dbname)	//Returns type boolean
	{
		if ($this->dbtype == "sqlite")
		{
			echo "This method is not supported with db type 'sqlite'\n";
		}
		else
		{
			$this->myDbConnObject->setDatabase($this->dbname);
		}
			
	}
	
	function getDbType()
	{
		return $this->dbtype;
	}
	
	function getDbLink()
	{
		return $this->myDbConnObject->getDbLink();
	}
	
	function disconnect()
	{
		$this->myDbConnObject->disconnect();
	}
	
	function __destruct()
	{
		//$this->myDbConnObject->__destruct();
	}
}
?>