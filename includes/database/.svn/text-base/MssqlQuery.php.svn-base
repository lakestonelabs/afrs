<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("QueryAbstract.php");

Class MssqlQuery extends QueryAbstract
{
	function __construct($dblink)
	{
		parent::__construct($dblink);	//Set our new object attributes.
	}
	
	function runQuery($query)
	{
		$this->query = $query;
		/*if (is_array($this->query)) 
		{
	        foreach($this->query AS $id => $value) 
	        {
	            $this->query[$id] = addslashes_mssql($value);
	        }
    	} else 
    	{
        	$this->query = str_replace("'", "''", $this->query);   
    	}*/
    	//echo $this->query . "\n";
		//$this->query = mssql_real_escape_string($this->query, $this->dblink);
		//echo "DB link: " . $this->dblink . "\n";
		//exit (0);
		$this->query_link = mssql_query($this->query, $this->dblink);
		if (!$this->query_link)
		{
			echo "QUERY ERROR\n";
		}
	}
	
	function getResultSize()	//Returns an integer.
	{
		
		// Check to see this method is being called on an INSERT, UPDATE, DELETE query (invalid for this method)
		if ($this->query_link != false || $this->query_link != true)
		{
			$this->result_size = mssql_num_rows($this->query_link);
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
			$this->result = mssql_fetch_array($this->query_link);
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
			$this->result = mssql_fetch_assoc($this->query_link);
			return $this->result;
		}
		else
		{
			echo "ERROR\n";
		}
	}
	
	function setResultPointer($position)	//Returns true or false.
	{
		if ($this->query_link != false)
		{
			return mssql_data_seek($this->query_link, $position);
		}
		else
		{
			echo "ERROR\n";
		}
	}
	
	function getAffectedRows()	//Get the number of affected rows from the previous SQL query.
	{
		if ($this->query_link == true)	//Make sure we are being called correctly.  Don't run if the previous query was a SELECT command.
		{
			return mssql_affected_rows($this->query_link);
		}
		else
		{
			echo "INVALID getAffectedRows call.  You're trying to call this method on a previous SELECT query statement.";
		}
	}
}

?>