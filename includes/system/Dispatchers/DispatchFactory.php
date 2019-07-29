<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DispatchFactory
 *
 * @author mlee
 */

require_once(__DIR__."/../Messages/Message.php");
require_once(__DIR__."/../Transaction.php");
require_once(__DIR__."/DispatchRegister.php");
require_once(__DIR__."/DispatchCheckin.php");
require_once(__DIR__."/DispatchWatchEvent.php");
require_once(__DIR__."/DispatchGetShares.php");

class DispatchFactory
{
    
    public static function dispatch($transaction_obj) 
    {
        $original_command = $transaction_obj->getMessageOfSequenceNumber(1)->getCommand();
        $dispatch_object = null;
        
        if ($original_command == "REGISTER") // 
        {
            $dispatch_object = new DispatchRegister();
        }
        else if ($original_command == "WATCHEVENT") // Completed and tested.
        {
            $dispatch_object = new DispatchWatchEvent();
        }
        else if ($original_command == "UNREGISTER") // Prototype, needs work and better error reporting.
        {
                return $this->receivedUnregisterMemberServer();
        }
        else if ($original_command == "CHECKIN") // Completed and tested.
        {
            $dispatch_object = new DispatchCheckin();
        }
        else if ($original_command == "UPDATE") // Needs work, possible removal from code.
        {
                return $this->updateMemberServerValues();
        }
        else if ($original_command == "GETSHARES")
        {
            $dispatch_object = new DispatchGetShares();
        }
        else if ($original_command == "GETSHARE") // Completed.  Needs testing.
        {
                return $this->receivedGetShare();
        }
        else if ($original_command == "PRESENTSHARES")  // Completed 7/12/2010.  Needs testing.
        {
                return $this->receivedPresentShares();
        }
        else if ($original_command == "REQUESTSYNC")  // Completed on 8/11/2010.  Needs extensive testing.
        {
                return $this->receivedRequestSync();
        }
        else if ($original_command == "SYNC")
        {

        }
        else if ($original_command == "SYNCREFRESH")
        {

        }
        else if ($original_command == "QUEUED")
        {
        }
        return $dispatch_object->dispatch($transaction_obj);
    }
}

?>
