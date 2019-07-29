<?php

/*
 * This class is only used to parse the raw xml data for the command and
 * create the appropriate extended message object.
 * 
 * Returns an extended Message object.
 */

/**
 * Description of MessageFactory
 *
 * @author mlee
 */

require_once(__DIR__."/CheckinMessage.php");
require_once(__DIR__."/GetShareMessage.php");
require_once(__DIR__."/GetSharesMessage.php");
require_once(__DIR__."/PresentSharesMessage.php");
require_once(__DIR__."/WatchEventMessage.php");


class MessageFactory
{
    private $command = null,
            $message_object;
    
    public static function startFactory($socket_or_address, $xml_or_command)
    { 
        // Pretty much all we care about at this stage is the socket or address and the command.
        // Once we have the command we can call the appropriate class.
        if (is_resource($socket_or_address) && get_resource_type($socket_or_address) == "stream")
        {
            $domdoc = new DOMDocument();
            if($domdoc->loadXML($xml_or_command))
            {
                $this->command = $domdoc->getElementsByTagName("cmdname");
            }
            else
            {
                throw new Exception("2nd argument is not a valid Message XML document.");
            }
            
        }
        else if (is_string($socket_or_address) && strlen($socket_or_address) > 0)
        {
            $ip_address_and_port = explode(":", $socket_or_address);  // Address string should be in form ip_address:port;
            if (sizeof($ip_address_and_port) != 2)
            {
                throw new Exception("1st argument is not in the form of IP_ADDRESS:PORT.");
            }
            
            if (is_string($xml_or_command) && strlen($xml_or_command) > 0)
            {
                $this->command = $xml_or_command;
            }
            else 
            {
                throw new Exception("2nd argument is not a valid string for command.");
            }
            
        }
        else
        {
            throw new Exception("1st argument is not a valid resource or connect string.");
        }        
        
        if ($this->command == "REGISTER") // 
        {
            
        }
        else if ($this->command == "WATCHEVENT") // Completed and tested.
        {
            
        }
        else if ($this->command == "UNREGISTER") // Prototype, needs work and better error reporting.
        {
                
        }
        else if ($this->command == "CHECKIN") // Completed and tested.
        {
            $this->message_object = new CheckinMessage($socket_or_address, $xml_or_command);
        }
        else if ($this->command == "UPDATE") // Needs work, possible removal from code.
        {
                
        }
        else if ($this->command == "GETSHARES")
        {
           
        }
        else if ($this->command == "GETSHARE") // Completed.  Needs testing.
        {
                
        }
        else if ($this->command == "PRESENTSHARES")  // Completed 7/12/2010.  Needs testing.
        {
                
        }
        else if ($this->command == "REQUESTSYNC")  // Completed on 8/11/2010.  Needs extensive testing.
        {
                
        }
        else if ($this->command == "SYNC")
        {

        }
        else if ($this->command == "SYNCREFRESH")
        {

        }
        else if ($this->command == "QUEUED")
        {
            
        }
        
        return $this->message_object;
    }
}
?>
