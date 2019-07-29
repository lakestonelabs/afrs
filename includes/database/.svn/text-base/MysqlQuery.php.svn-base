<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("QueryAbstract.php");

Class MysqlQuery extends QueryAbstract
{
	function __construct($dblink)
	{
		parent::__construct($dblink);	//Set our new object attributes.
	}
	
	function runQuery($query)
	{
		$this->query = $query;
		//$this->query = mysql_real_escape_string($this->query, $this->dblink);
		$this->query_link = mysql_query($this->query, $this->dblink);
		if ($this->query_link === false)
		{
			echo "DB link: " . $this->dblink . "\n";
			echo "QUERY ERROR: " . mysql_error() . "\n";
                        echo "Query was: " . $this->query . "\n";
			return (false);
		}
		else
		{
			return (true);
		}
	}
	
	function getResultSize()	//Returns an integer.
	{
		
		// Check to see this method is being called on an INSERT, UPDATE, DELETE query (invalid for this method)
		if ($this->query_link != false || $this->query_link != true)
		{
			$this->result_size = mysql_num_rows($this->query_link);
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
			$this->result = mysql_fetch_array($this->query_link);
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
			$this->result = mysql_fetch_assoc($this->query_link);
			return $this->result;
		}
		else
		{
			echo "ERROR: Empty query result set.\n";
                        return false;
		}
	}
	
	function setResultPointer($position)	//Returns true or false.
	{
		if ($this->query_link != false)
		{
			return mysql_data_seek($this->query_link, $position);
		}
		else
		{
			echo "ERROR\n";
		}
	}
	
	function getAffectedRows()	//Get the number of affected rows from the previous SQL query.
	{
		if ($this->query_link === true)	//Make sure we are being called correctly.  Don't run if the previous query was a SELECT command.
		{
			return mysql_affected_rows($this->query_link);
		}
		else
		{
			echo "INVALID getAffectedRows call.  You're trying to call this method on a previous SELECT query statement.";
		}
	}
	
	function resetPointer()
	{
		mysql_data_seek($this->query_link, 0);
	}
	
	function getInsertID()
	{
		$insert_id = mysql_insert_id($this->dblink);
		return $insert_id;
	}
}

?>