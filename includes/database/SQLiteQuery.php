<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.

require_once ("QueryAbstract.php");

Class SQLiteQuery extends QueryAbstract
{
	private $orig_query = null;  // Holds the original query so we can restore it when we do a getResultSize() method call.
	
	function __construct($dblink)
	{
		parent::__construct($dblink);	// $dblink in this the case of type 'sqlite' is a PDO Object and not a resource link.
										// PDO is the new way to do db shit.  SQLiteQuery is the first instance where I use
										// PDO and not the older sqlite functions.  Need to have php 5.1 & > to use PDO.
										// PDO is needed to use sqlite db files version 3 & >.
	}

	function runQuery($query)  // Returns type boolean.
	{
		$this->query = $query;
		$this->query_link = $this->dblink->prepare($this->query); // Returns a PDO Statement object, not a resource link.
		return($this->query_link->execute());  	// Returns boolean.
	}
	
	function getResultSize()	//Returns an integer.
	{
		// Check to see this method is being called on an INSERT, UPDATE, DELETE query (invalid for this method)
		// NOTE:  This will always be true for the case of sqlite since $this->query_link is always an object and therefore never true nor false.
		if ($this->query_link != false || $this->query_link != true)
		{
			// Fucking stupid!!  You have to do a SELECT COUNT(*) and then use fetchColumn() to get the first column which has the number from the select statement.
			$this->orig_query = $this->query;
			$this->query = preg_replace("/^select.*from/", "select count(*) from", $this->query);  // Need to replace the 'select....from' statement with 'select count(*) from'.
			$this->query_link = $this->dblink->query($this->query);
			$this->result_size = $this->query_link->fetchColumn();
			
			// Now that we go the result size, we need to rerun the original query so we actual results and not just the count..
			$this->query = $this->orig_query;  // Restore to the original query and not the 'select count(*)....' one.
			$this->query_link = $this->dblink->prepare($this->query); // Returns a PDO Statement object, not a resource link.
			$this->query_link->execute();
			return $this->result_size;
		}
		else
		{
			echo "INVALID getSize call.  You're tring to see the result size of an INSERT/UPDATE/DELETE query.\n";
		}
	}
	
	function getResultArray()	//Returns an array.
	{
		if ($this->query_link != false)
		{
			$this->result = $this->query_link->fetchAll(PDO::FETCH_NUM);
			return $this->result;
		}
		else
		{
			echo "ERROR\n";
		}
	}
	
	function getResultAssoc()	//Returns and array.
	{
		if ($this->query_link != false)
		{
			//$this->result = sqlite_fetch_array($this->query_link, SQLITE_ASSOC);  // Per the PHP SQLite doco for returning associative arrays.
			$this->result = $this->query_link->fetchAll(PDO::FETCH_ASSOC);
			return $this->result;
		}
		else
		{
			echo "ERROR\n";
		}
	}
	
	function setResultPointer($position)	//Returns true or false.
	{
		/*if ($this->query_link != false)
		{
			return sqlite_seek($this->query_link, $position);
		}
		else
		{
			echo "ERROR\n";
		}*/
		echo "ERROR:  PDO does not support db pointer seeking for SQLite.  Bitch to the php developers!\n";
		return(false);
	}
	
	function getAffectedRows()	//Get the number of affected rows from the previous SQL query.
	{
		return $this->query_link->rowCount();
	}
}
?>