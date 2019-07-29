<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.

require_once ("DbConnectionAbstract.php");

Class SQLiteDbConnection extends DbConnectionAbstract
{
	function __construct($filename)	//Returns type: resource link.
	{
		$this->filename = $filename;
		
		// We must use the new PDO interface for SQLite since the previous sqlite
		// functions only support SQLite v2.  PDO is the new way to go.
		// Eventually all of my classes need to be rewritten for to use PDO.
		try 
		{
	    	$this->dblink = new PDO("sqlite:$this->filename");
		} 
		catch (PDOException $e) 
		{
	    	echo 'Connection failed: ' . $e->getMessage();
		}
		//$this->dblink = sqlite_open($this->filename);  // Open the SQLite database file in read/write mode.
		
		/*if (!$this->dblink)
		{
			die("Could not connect:"); //. sqlite_error_string(sqlite_last_error($this->dblink)));
		}*/
	}
	
	function getDbLink()
	{
		return $this->dblink;
	}
}

?>