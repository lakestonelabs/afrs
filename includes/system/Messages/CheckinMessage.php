<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CheckinMessage
 *
 * @author mlee
 */

require_once("Message.php");

class CheckinMessage extends Message
{
    public function __construct($socket_or_address, $xml_or_command)
    {
        // Extend known list of valid commands to include the 'WATCHEVENT' command.
        $this->commands_array["CHECKIN"] = array();
        
        // Extend the dialogue array to handle the 'CHECKIN' command.
        $this->dialogue_array["CHECKIN"] = array("null" => array("CHECKIN" => "ACK"),
                                                "CHECKIN" => array("ACK" => "BYE", "ERROR" => "ACK"),
                                                "ACK" => array("BYE" => "ACK"),
                                                "BYE" => array("ACK" => "DISCARD"),
                                                "ERROR" => array("ACK" => "BYE"));
        
        parent::__construct($socket_or_address, $xml_or_command);
    }
}

?>
