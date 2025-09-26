<?php

return array('mysql_host' => 'mysql', // Or simply, localhost
             'mysql_port' => -1, // -1 means no port is specifically selected
             'mysql_login' => 'root',
             'mysql_pwd' => 'root',
             'mysql_db_name' => 'jeux_redige',
             'paths_js_extension' => '.js', // E.g., if you wish to use minified JS scripts, replace with .min.js
             'protocol' => 'http', // Replace with https if your server implements it
             'www_prefix' => false); // Replace with true if you wish all URLs are prefixed with "www." (unnecessary on localhost)

?>

<!--
         $host = 'mysql';
$db = 'jeux_redige';
$user = 'root';
$pass = 'root';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4"; -->
