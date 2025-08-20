<?php
$mysqli = new mysqli("localhost", "user", "password", "database");

echo $mysqli->host_info . "\n";
$fiber = new Fiber(function(){
    // get data from database;
    
});