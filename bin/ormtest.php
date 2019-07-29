#!/usr/bin/php 
<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(__DIR__."/../Propel/runtime/lib/Propel.php");  // The ORM technology used by Afrs.
Propel::init(__DIR__."/../Propel/build/conf/afrs-conf.php");
set_include_path(__DIR__."/../Propel/build/classes/".":".get_include_path());  // Add the Afrs Propel build classes to our path.

//->filterByName("sid")>findOne()->getValue()

$shares = TblSharesQuery::create();
$shares->filterByShareName("mikes_home");
$shares->find();

foreach($shares as $this_share)
{
    echo $this_share->getSize();
}
?>
