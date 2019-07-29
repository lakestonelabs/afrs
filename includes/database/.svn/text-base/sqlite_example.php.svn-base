<?php
// Licensed under GPLv3.  Copyright Mike Lee 2008-2009.
require_once ("DbConnectionWrapper.php");
require_once ("QueryWrapper.php");

$mydbconn = new DbConnectionWrapper("sqlite", "/home/mlee/test.sqlite");
$myquery = new QueryWrapper($mydbconn);

$myquery->runQuery("select * from main");
echo ("Result: " . $myquery->getResultSize() . "\n");
print_r($myquery->getResultAssoc());

$myquery->runQuery("select * from main");
print_r($myquery->getResultArray());

?>