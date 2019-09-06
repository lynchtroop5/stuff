<?php
$context = new ZMQContext();

// This will send a test response to the socket which will fire a message to 
// CLIPS and update the response/request buckets
$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->connect("tcp://127.0.0.1:8384");
$message = 'New Transaction Received';

$sender->send($message);
?>