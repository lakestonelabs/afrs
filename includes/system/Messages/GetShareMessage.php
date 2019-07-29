<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of GetShareMessage
 *
 * @author mlee
 * 
 * 
 * *
 * GETSHARE
 * 		=>	SHARENAME [NAME OF SHARE ON REMOTE END]
 * (Possible responses):
 * 
 * 
 * 
 */

require_once("Message.php");

class GetShareMessage extends Message
{
    
    public function __construct()
    {
        // Extend known list of valid commands to include the 'GETSHARE' command.
        $this->commands_array["GETSHARE"] = array('SHARENAME' => null);
    }
}

?>
