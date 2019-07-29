<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

include_once 'phpmailer/class.phpmailer.php';

$error = null;

$from = "cslinuxboy@gmail.com";
$from_name = "Mike Lee";
$subject = "Test email from Raspberry PI";
$body = "This is some test text.  Can you see it?";
$to = "cslinuxboy@gmail.com";

$mail = new PHPMailer();  // create a new object
$mail->IsSMTP(); // enable SMTP
$mail->SMTPDebug = 0;  // debugging: 1 = errors and messages, 2 = messages only
$mail->SMTPAuth = true;  // authentication enabled
$mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for GMail
$mail->Host = 'smtp.gmail.com';
$mail->Port = 465; 
$mail->Username = "cslinuxboy@gmail.com";  
$mail->Password = "k377_ishot!";           
$mail->SetFrom($from, $from_name);
$mail->Subject = $subject;
$mail->Body = $body;
$mail->AddAddress($to);
if(!$mail->Send()) {
        $error = 'Mail error: '.$mail->ErrorInfo; 
        return false;
} else {
        $error = 'Message sent!';
        return true;
}


?>
