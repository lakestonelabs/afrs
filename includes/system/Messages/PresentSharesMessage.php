<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PresentSharesMessage
 *
 * @author mlee
 */

require_once("Message.php");

class PresentSharesMessage
{
    protected   
                $shares_and_values_array = null,  // Is a 2-D associative array that stores the sharenames and their values.
                $shares_node;


    public function __construct()
    {
        // Extend known list of valid commands to include the 'WATCHEVENT' command.
        $this->commands_array["PRESENTSHARES"] = array('SHARECOUNT' => null);  // We don't account for the subarray of the actual shares info.
        $this->dialogue_array["PRESENTSHARES"] = array("null" => array("PRESENTSHARES" => "ACK"),
                                                     "PRESENTSHARES" => array("ACK" => "BYE"),
                                                     "ACK" => array("BYE" => "ACK"),
                                                     "BYE" => array("ACK" => "DISCARD"));
        
        
        
        if ($this->command == "PRESENTSHARES")
        {
                $shares_nodes = $this->doc->getElementsByTagName("shares"); // Get an array of containing all of the <file></files>.
                $shares_elements = $shares_nodes->item(0);
                $share_nodes = $file_list_elements->getElementsByTagName("share");

                for ($i = 0; $i < $share_nodes->length; $i++)
                {
                        $share = $share_nodes->item($i); // Get the the <share></share> element at the specified position.
                        $share_name = trim($share->getElementsByTagName("sharename")->item(0)->nodeValue);
                        $this->shares_and_values_array[$share_name]["SIZE"] = trim($share->getElementsByTagName("size")->item(0)->nodeValue);
                        $this->shares_and_values_array[$share_name]["AVAILABLESTORAGE"] = trim($share->getElementsByTagName("availablestorage")->item(0)->nodeValue);
                        $this->shares_and_values_array[$share_name]["ACTIVE"] = trim($share->getElementsByTagName("active")->item(0)->nodeValue);
                        $this->shares_and_values_array[$share_name]["PERMISSIONS"] = trim($share->getElementsByTagName("permissions")->item(0)->nodeValue);
                        $this->shares_and_values_array[$share_name]["CREATIONDATE"] = trim($share->getElementsByTagName("creationdate")->item(0)->nodeValue);
                }
        }
    }
    
    
    
    public function setShareValues($share_name, $share_values_array)
    {
            // First make sure that this method is being called on a message the has the appropriate command.
            if ($this->command == "PRESENTSHARES")
            {
                    $this->shares_and_values_array[$share_name] = $share_values_array;
                    return true;
            }
            else
            {
                    echo "Invalid call to Message::setShareValues() method.  Message command is not PRESENTSHARRES\n";
                    return false;
            }
    }
    
    function getSharesValues()  // Returns a true 2-D associative array containing the names of the shares and each share's settings.
    {
            return $this->shares_and_values_array;
    }
    
    public function buildTransmitMessage()  // This is the last method that should be called when creating a transmit message.  This actuall builds the xml.
    {
        if ($this->command == "PRESENTSHARES")
        {
                if (sizeof($this->shares_and_values_array) > 0)
                {
                        $this->shares_node = $this->message_node->appendChild($this->shares_element);
                        $shares_keys = array_keys($this->shares_and_values_array);
                        for($i = 0; $i < sizeof($this->shares_and_values_array); $i++)
                        {
                                ${"share_element".$i} = $this->doc->createElement("share");
                                ${"share_node".$i} = $this->shares_node->appendChild(${"share_node".$i});

                                        ${"share_name_element".$i} = $this->doc->createElement("sharename");
                                        ${"share_name_node".$i} = ${"share_node".$i}->appendChild(${"share_name_element".$i});
                                        ${"share_name_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]));

                                        ${"share_size_element".$i} = $this->doc->createElement("size");
                                        ${"share_size_node".$i} = ${"share_node".$i}->appendChild(${"share_size_element".$i});
                                        ${"share_size_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]["SIZE"]));

                                        ${"share_available_storage_element".$i} = $this->doc->createElement("freesize");
                                        ${"share_available_storage_node".$i} = ${"share_node".$i}->appendChild(${"share_available_storage_element".$i});
                                        ${"share_available_storage_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]["AVAILABLESTORAGE"]));

                                        ${"share_active_element".$i} = $this->doc->createElement("active");
                                        ${"share_active_node".$i} = ${"share_node".$i}->appendChild(${"share_active_element".$i});
                                        ${"share_active_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]["ACTIVE"]));

                                        ${"share_permissions_element".$i} = $this->doc->createElement("globalpermissions");
                                        ${"share_permissions_node".$i} = ${"share_node".$i}->appendChild(${"share_permissions_element".$i});
                                        ${"share_permissions_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]["PERMISSIONS"]));

                                        ${"share_creation_date_element".$i} = $this->doc->createElement("creationdate");
                                        ${"share_creation_date_node".$i} = ${"share_node".$i}->appendChild(${"share_creation_date_element".$i});
                                        ${"share_creation_date_node".$i}->appendChild($this->doc->createTextNode($this->shares_and_values_array[$shares_keys[$i]]["CREATIONDATE"]));

                        }
                }
                else
                {
                        echo "ERROR:  Message::buildTransmitMessage() - This message does not have any shares to process for command PRESENTSHARES.\n";
                }
        }
    }
}

?>
