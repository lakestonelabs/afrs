#!/usr/bin/php
<?php

$fp = stream_socket_client("tcp://10.0.2.15:4747", $errno, $errstr, 15);
if (!$fp) 
{
    echo "$errstr ($errno)\n";
} 
else 
{
	$doc = new DOMDocument();
	$doc->load("getshares.xml");
	$xml_string = $doc->saveXML();
	//echo "XML: " . $xml_string . "\n";
    
	fwrite($fp, $xml_string . "\n");
    /*$count = 0;
	while($count <= 10)
	{
		for($i = 0; $i <= 100; $i++)
		{
			fwrite($fp, $xml_string . "\n");
		}
    	//usleep(100000);
    	$count++;
	}*/
    
    fclose($fp);
}

?>