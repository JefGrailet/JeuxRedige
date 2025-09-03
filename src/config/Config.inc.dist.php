<?php
// Remove ".dist" in file's name
return array('mysql_host' => '127.0.0.1', // Or simply, localhost
             'mysql_port' => -1, // -1 means no port is specifically selected
             'mysql_login' => 'root',
             'mysql_pwd' => '',
             'mysql_db_name' => 'sample_db',
             'paths_js_extension' => '.js', // E.g., if you wish to use minified JS scripts, replace with .min.js
             'protocol' => 'http', // Replace with https if your server implements it
             'www_prefix' => false); // Replace with true if you wish all URLs are prefixed with "www." (unnecessary on localhost)

?>
