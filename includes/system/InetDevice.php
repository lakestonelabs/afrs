<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of InetDevice
 *
 * @author mlee
 */
class InetDevice
{
    private     $name = null,
                $mac = null,
                $ip_address = null,
                $public_ip_address = null,
                $broadcast = null,
                $mask = null,
                $link_detected = null,
                $state = null,
                $gateway = null,
                $nameserver = null,
                $speed = null,
                $duplex = null;
                
    function __construct($device_name, $quick)
    {
        $this->name = trim($device_name);
        $this->mac = trim(shell_exec("cat /sys/class/net/$device_name/address"));
        $this->ip_address = trim(shell_exec("/sbin/ifconfig $device_name | grep 'inet addr' | cut -d : -f 2 | cut -d ' ' -f 1"));
        $this->broadcast = trim(shell_exec("/sbin/ifconfig $device_name | grep 'inet addr' | cut -d : -f 3 | cut -d ' ' -f 1"));
        $this->mask = trim(shell_exec("/sbin/ifconfig $device_name | grep 'inet addr' | cut -d : -f 4 | cut -d ' ' -f 1"));
        $this->link_detected = (int) trim(shell_exec("cat /sys/class/net/$device_name/carrier"));
        $this->state = trim(shell_exec("cat /sys/class/net/$device_name/operstate"));        
        
        if (!$quick)
        {
            if ($this->link_detected == 1)
            {
                $gateway_temp = preg_split("/[\s,]+/", trim(shell_exec("route -n | grep UG | grep $device_name")));
                if (sizeof($gateway_temp) > 1)
                {
                    $this->gateway = $gateway_temp[1];

                    $has_internet_access = trim(shell_exec("ping -I $device_name -W 2 -c 2 8.8.8.8 | grep received | cut -d ',' -f 2 | cut -d ' ' -f 2"));
                    if ($has_internet_access != 0)
                    {
                        $this->public_ip_address = trim(shell_exec("wget -q --bind-address=$this->ip_address -O - checkip.dyndns.org|sed -e 's/.*Current IP Address: //' -e 's/<.*$//'"));
                    }
                    else
                    {
                        $this->public_ip_address = "0.0.0.0";
                    }
                }
                else
                {
                    $this->gateway = null;
                    $this->public_ip_address = null;
                }
            }
        }

        $this->nameserver = trim(shell_exec("cat /etc/resolv.conf | grep nameserver | cut -d ' ' -f 2"));

        if (preg_match("/eth/", $device_name) == 1)  // Test to see if we are dealing with an ethernet device.
        {
            if ($this->link_detected == 1)  // The below entries are only valid when there is a carrier.
            {
                $this->speed = trim(shell_exec("cat /sys/class/net/$device_name/speed"));
                $this->duplex = trim(shell_exec("cat /sys/class/net/$device_name/duplex"));
            }
        }
        elseif (preg_match("/wlan/", $device_name) == 1)  // Determine if we are dealing with a wireless device.  
        {
            if ($this->link_detected == 1)  // The below entries are only valid when there is a carrier.
            {
                $speed_temp = preg_split("/[\s,]+/", trim(shell_exec("iwlist $device_name bitrate | grep 'Current Bit Rate' | cut -d : -f 2")));
                $this->speed = $speed_temp[0];
                $this->duplex = "half";
            }
        }
        else
        {
            $this->speed = "unknown";
            $this->duplex = "unknown";
        }
    }
    
    public function getBroadcast()
    {
        return $this->broadcast;
    }
    
    public function getDuplex()
    {
        return $this->duplex;
    }
    
    public function getGateway()
    {
        return $this->gateway;
    }
    
    public function getIpAddress()
    {
        return $this->ip_address;
    }
    
    public function getPublicIpAddress()
    {
        return $this->public_ip_address;
    }
    
    public function getLinkDetected()
    {
        if ($this->link_detected == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
    public function getMac()
    {
        return $this->mac;
    }
    
    public function getSubnetMask()
    {
        return $this->mask;
    }
    
    public function getDeviceName()
    {
        return $this->name;
    }
    
    public function getNameServer()
    {
        return $this->nameserver;
    }
    
    public function getSpeed()
    {
        return $this->speed;
    }
    
    public function getState()
    {
        return $this->state;
    }
}

?>
