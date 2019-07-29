#!/usr/bin/php

<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(__DIR__."/../includes/orm/rb.php");  // The ORM technology used by Afrs.

R::setup('mysql:host=localhost;dbname=afrs','afrs','afrspassword');

/*
$book = R::dispense("book");
$book->title = "My first RedBean Object.";
$book->author = "Mike Lee";

$id = R::store($book);

$journal_count = R::count("tbl_journal");

$all_journals = R::findAll("tbl_journal", "order by date");

foreach($all_journals as $this_journal)
{
    echo "Date: " . $this_journal->date . " File: " . $this_journal->file . " Size: " . $this_journal->size . "\n";
}

echo "Journal Count is: " . $journal_count . "\n";
 */

//$sid = R::getCell("select value from tbl_registry where name = 'sid'");
$sid = R::find("tbl_registry", " name = ? ", array("sid"));
var_dump($sid);

foreach($sid as $this_sid)
{
    echo "VALUE: " . $this_sid->value . "\n";
}

?>
