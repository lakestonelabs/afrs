<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once("MysqlQuery.php");
require_once("MysqliQuery.php");
require_once("SQLiteQuery.php");
require_once("MssqlQuery.php");

Class QueryWrapper
{
	private	$myDbConnectionWrapperObject = null,
			$myqueryObject = null;
	
	function __construct($DbConnectionWrapperObject)
	{
		$this->myDbConnectionWrapperObject = $DbConnectionWrapperObject;
		
		if ($this->myDbConnectionWrapperObject->getDbType() == "mysql")
		{
			$this->myqueryObject = new MysqlQuery($this->myDbConnectionWrapperObject->getDbLink());
		}
                if ($this->myDbConnectionWrapperObject->getDbType() == "mysqli")
		{
			$this->myqueryObject = new MysqliQuery($this->myDbConnectionWrapperObject->getDbLink());
		}
		if ($this->myDbConnectionWrapperObject->getDbType() == "sqlite")
		{
			$this->myqueryObject = new SQLiteQuery($this->myDbConnectionWrapperObject->getDbLink());
		}
		if ($this->myDbConnectionWrapperObject->getDbType() == "mssql")
		{
			$this->myqueryObject = new MssqlQuery($this->myDbConnectionWrapperObject->getDbLink());
		}
	}
	
	function runQuery($query)
	{
            $arg_c = func_num_args();
            
            if ($this->myDbConnectionWrapperObject->getDbType() == "mysqli") // Support for MysqliQuery class.
            {
                if ($arg_c == 1)
                {
                    return $this->myqueryObject->runQuery($query, null);
                }
                else if ($arg_c == 2)
                {
                    return $this->myqueryObject->runQuery($query, func_get_arg(($arg_c - 1)));
                }
            }
            else
            {
                return $this->myqueryObject->runQuery($query);
            }
	}
	
	function runQueryGetResults($query, $return_type)  // Runs the query and returns the complete result set in a numeric or assoc. 2d array.
	{												   // $return_type can be "num" for numeric array or "assoc" for associative array.
		$this->myqueryObject->runQuery($query);
		if ($return_type == "num")
		{
			return $this->getResultsArray();
		}
		if ($return_type == "assoc")
		{
			return $this->getResultsAssoc();
		}
		else
		{
			echo "ERROR: QueryWrapper::runQueryGetREsults - Second argument for runQueryGetResults is not a valid return type.  Valid return types are \"num\" for numeric or \"assoc\" for associative array.\n";
		}
	}
	
	function getResultSize()	//Returns an integer.
	{
		return $this->myqueryObject->getResultSize();
	}
	
	function getResultArray()	//
	{
		return $this->myqueryObject->getResultArray();
	}
	
	function getResultAssoc()	//
	{
		return $this->myqueryObject->getResultAssoc();
	}
	
	function getResultsArray()	//Returns a 2d indexed array containing all records and their elements.
	{
		$results = null;
                
                while ($row = $this->myqueryObject->getResultArray()) 
                {
                    $results[] = $row;  
                }
                
		return $results;
	}
	
	function getResultsAssoc()	//Returns a 2d associative (assoc. array wrapped in a numeric array) array containing all records and their elements.
	{
		$results = null;
                
                while ($row = $this->myqueryObject->getResultAssoc()) 
                {
                    $results[] = $row;  
                }
                
		return $results;
	}
	
	function setResultPointer($position)	//Returns true or false.
	{
		return $this->myqueryObject->setResultPointer($position);
	}
	
	function getAffectedRows()	//Get the number of affected rows from the previous SQL query.
	{
		return $this->myqueryObject->getAffectedRows();
	}
	
	function getInsertID()
	{
		return $this->myqueryObject->getInsertID();
	}
}
?>