<?php

/**
* Script to check if a given string can be used as an alias for a game title. The script receives 
* a "needle" as a $_POST value and returns a simple response: "OK" when the alias can be created,
* "Not OK" otherwise.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Tag.class.php';

if(isset($_POST['alias']) && !empty($_POST['alias']))
{
   $needle = mb_convert_encoding(Utils::secure($_POST['alias']), 'ISO-8859-1', 'UTF-8');
   $needle = str_replace('|', '', $needle);
   $needle = str_replace('"', '', $needle);
   
   if($needle === '')
   {
      exit();
   }
   
   try
   {
      $tag = new Tag($needle);
      $msg = 'Not OK';
      if($tag->canBeAnAlias())
         $msg = 'OK';
      header('Content-Type: text/html; charset=UTF-8');
      echo $msg;
   }
   catch(Exception $e)
   {
      exit();
   }
}

?>
