<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("QueryAbstract.php");

Class MysqliQuery extends QueryAbstract
{
        protected   $statement_obj = null,
                    $result_obj = null,  // Only used when a query result set is returned as a mysqli_result object.
                                         // In this case, this is infrequently used since mysqlnd is not supported yet
                                         // by this class.  See mysqli::get_result for more info.
                    $type_map = array("integer" => "i",
                                      "double" => "d",
                                      "string" => "s",
                                      "object" => "b"),  // b is for BLOB.  Not supported at the moment.
                    
                    $result_pointer = null  // Stores the location of the last queried result record from the 3d result array
                    ;
        
	function __construct($dblink)
	{
            parent::__construct($dblink);	//Set our new object attributes.
	}        
        
        // Returns true on an SELECT query, and the number of affected rows on INSERT/UPDATE/DELETE queries, or FALSE on errors.
        function runQuery($query_string, $parameters_ref_array)
        {
            $this->query = $query_string;
            $param_types_string = null;  // Holds a concatinated string of the parameter variable types.
            if ($parameters_ref_array !== null)  // Has parameters, therefore, we can't do just a simple mysqli::query.
            {
                $this->statement_obj = $this->dblink->prepare($this->query);

                if (!$this->statement_obj)  // Something bad happened.
                {
                    return false;
                }
                else
                {
                    // Now build the string describing the paramter types that are to be bound to the statement
                    // and the parameters passed by reference.
                    for($i = 0; $i < sizeof($parameters_ref_array); $i++)
                    {
                        $var_type = gettype($parameters_ref_array[$i]);
                        $param_types_string .= $this->type_map[$var_type];
                    }
                    array_unshift($parameters_ref_array, $param_types_string); // Put the types string at the head of the parameters array.
                    array_unshift($parameters_ref_array, $this->statement_obj); // Append the statement object to the head of the array to be passed to mysqli_stmt_bind_param().
                    
                    // TODO:  In future, possibly use the ReflectionMethod class to be more objected-oriented.
                    if (call_user_func_array("mysqli_stmt_bind_param", $parameters_ref_array))
                    {
                        if ($this->statement_obj->execute())
                        {
                            // If query was a select, then get the result in the form of a result object.
                            if (preg_match("/^[Ss][Ee][Ll][Ee][Cc][Tt]/", $this->query) == 1)
                            {
                                // $this->result_obj = $this->statement_obj->get_result();  // Can't use until mysqlnd is supported.
                                
                                $this->statement_obj->store_result();  // Transfer result set from server to client and buffer.
                                $result_metadata = $this->statement_obj->result_metadata();  // Returns type mysqli_result.
                                $result_data_array = array();
                                $bind_variables = array();
                                
                                // Below partial code used from php.net's site.
                                // URL: http://us2.php.net/manual/en/mysqli-stmt.bind-result.php
                                // have to use the call_user_func_array along with mysqli_stmt_bind_result 
                                // to make dynamic.
                                while($field = $result_metadata->fetch_field())
                                {
                                    var_dump(&$result_data_array[$field->name]);
                                    $bind_variables[] = &$result_data_array[$field->name]; // pass by reference
                                }
                                
                                array_unshift($bind_variables, $this->statement_obj);  // Make statement_obj the first element on the array stack.
                                call_user_func_array('mysqli_stmt_bind_result', $bind_variables);
                                
                                $count = 0;
                                while($this->statement_obj->fetch())
                                {
                                    foreach($bind_variables as $this_bind_variable)
                                    {
                                        var_dump($this_bind_variable);
                                        $this->result[$count][$this_bind_variable] = $this_bind_variable;
                                    }
                                    $count++;
                                }
                                $this->result_pointer = 0;
                            }
                            return true;
                        }
                        else  // TODO:  Need to throw some type of exception.
                        {
                            return false;
                        }
                    }
                    else  // TODO:  Need to throw some type of exception.
                    {
                        return false;
                    }
                }
            }
            else if ($parameters_ref_array === null) // Just a plain query.
            {
                $this->result_obj = $this->dblink->query($this->query);
                if ($this->result_obj instanceof mysqli_result)
                {
                    return true;
                }
                else if (is_bool($this->result_obj))
                {
                    return $this->result_obj;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
        
	function getResultSize()	// Returns an integer.
	{
		
            // Check to see this method is being called on an INSERT, UPDATE, DELETE query (invalid for this method)
            if ($this->result_obj instanceof mysqli_result)
            {
                $this->result_size = $this->result_obj->num_rows;
                return $this->result_size;
            }
            else if ($this->statement_obj instanceof mysqli_stmt)
            {
                $this->result_size = $this->statement_obj->num_rows;
                return $this->result_size;
                
            }
            else
            {
                    echo "ERROR: MysqliQuery::getResultSize - Either you're tring to see the result size of an INSERT/UPDATE/DELETE query. or you have not executed the query yet.\n";
                    return false;
            }
	}
	
	function getResultArray()	//Returns a 2d array for the latest record from the queried result.
	{
            if ($this->result_obj instanceof mysqli_result)
            {
                $this->result = $this->result_obj->fetch_array(MYSQLI_NUM);
                return $this->result;
            }
            else if ($this->statement_obj instanceof mysqli_stmt && sizeof($this->result) > 0)
            {
                /* The query results are stored in a 3d array, index array referencing an associative array.
                 * Since this method is to return a 2d indexed array and not an associative array, we will have
                 * to copy the results of the assoc array into an indexed array and return that.
                 */
                $result_pointer = $this->result_pointer;
                $this->result_pointer++;
                return array_values($this->result[$result_pointer]);
            }
            else
            {
                    echo "ERROR: MysqliQuery::getResultArray - Can't call this method on a non-existant result object\n";
                    return false;
            }
	}
        
        function getResultsArray()	//Returns a 3D array will all results from the queried result.
	{
            if ($this->result_obj instanceof mysqli_result)
            {
                $this->result = $this->result_obj->fetch_array(MYSQLI_NUM);
                return $this->result;
            }
            else if ($this->statement_obj instanceof mysqli_stmt && sizeof($this->result) > 0)
            {
                $return_array = null;
                
                // Need to transform the 3d (index/assoc.) array into a 3d index/index array.
                foreach($this->result as $this_result_record_assoc_array)
                {
                    $return_array[] = array_values($this_result_record_assoc_array);
                }
                $this->result_pointer = 0;  // Reset to the beginning of the array regardless of it's previous position.
                return $return_array;
            }
            else
            {
                    echo "ERROR: MysqliQuery::getResultsArray - Can't call this method on a non-existant result object\n";
                    return false;
            }
	}
	
	function getResultAssoc()	//Returns and array.
	{
            if ($this->result_obj instanceof mysqli_result)
            {
                $this->result = $this->result_obj->fetch_array(MYSQLI_ASSOC);
                return $this->result;
            }
            else if ($this->statement_obj instanceof mysqli_stmt && sizeof($this->result) > 0)
            {
                $result_pointer = $this->result_pointer;
                $this->result_pointer++;
                return $this->result[$result_pointer];
            }
            else
            {
                    echo "ERROR: MysqliQuery::getResultAssoc - Can't call this method on a non-existant result object\n";
                    return false;
            }
	}
        
        function getResultsAssoc()	//Returns an array.
	{
            if ($this->result_obj !== null)
            {
                return $this->result_obj->fetch_all(MYSQLI_ASSOC);
            }
            else if ($this->statement_obj instanceof mysqli_stmt && sizeof($this->result) > 0)
            {
                return $this->result;
            }
            else
            {
                    echo "ERROR: MysqliQuery::getResultsAssoc - Can't call this method on a non-existant result object\n";
                    return false;
            }
	}
	
	function setResultPointer($position)	//Returns true or false.
	{
            if ($this->result_obj instanceof mysqli_result && $this->result_obj->num_rows > 0)
            {
                return $this->result_obj->data_seek($position);
            }
            else if ($this->statement_obj instanceof mysqli_stmt && $this->statement_obj->num_rows > 0)
            {
                $this->result_pointer = $position;
            }
            else
            {
                    echo "ERROR: MysqliQuery::setResultPointer - Can't call this method on a non-existant result object\n";
                    return false;
            }
	}
	
	function getAffectedRows()	//Get the number of affected rows from the previous SQL query.
	{
            /* Need to do a regular expresion on the query to determine if we ran a select, update, delete, or insert
             * and then proceed accordingly.
            */
            if (preg_match("/^[Ss][Ee][Ll][Ee][Cc][Tt]/", $this->query) == 1)  // Query was a select statement.
            {
                if ($this->result_obj instanceof mysqli_result)
                {
                    return $this->result_obj->num_rows;
                }
                else if ($this->statement_obj instanceof mysqli_stmt)
                {
                    return $this->statement_obj->num_rows;
                }
                else
                {
                    echo "ERROR - MysqliQuery::getAffectedRows - No result set exists.  Can't seek.  Did you run a query?\n";
                    return false;
                }
            }
            else  // We must have ran an insert, update, or delete query thenp.
            {
                if ($this->result_obj instanceof mysqli_result)
                {
                    return $this->result_obj->affected_rows;
                }
                else if ($this->statement_obj instanceof mysqli_stmt)
                {
                    return $this->statement_obj->affected_rows;
                }
                else
                {
                    echo "ERROR - MysqliQuery::getAffectedRows - NULL data returned.  Did you run a query?\n";
                    return false;
                }
            }
	}
	
	function resetPointer()
	{
            if ($this->result_obj instanceof mysqli_result && $this->result_obj->num_rows > 0)
            {
                return $this->result_obj->data_seek(0);
            }
            else if ($this->statement_obj instanceof mysqli_stmt && $this->statement_obj->num_rows > 0)
            {
                return $this->result_pointer = 0;
            }
            else
            {
                echo "ERROR - MysqliQuery::resetPointer - No result set exists.  Can't seek.  Did you run a query?\n";
                return false;
            }
	}
	
	function getInsertID()
	{
            return $this->statement_obj->insert_id;
	}
        
        public function enableAutoCommit()
        {
            // TODO:  
        }
        
        public function disableAutoCommit()
        {
            // TODO:  
        }
        
        public function getAutoCommitState()
        {
            // TODO:  
        }
}

?>