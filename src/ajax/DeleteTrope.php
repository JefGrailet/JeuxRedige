<?php

/**
* Script to delete a trope via AJAX. Only an authorized user should be able to perform this 
* operation.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Trope.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}
/*
else if(!Utils::check(LoggedUser::$data['can_edit_games']))
{
   exit();
}
*/

if(!empty($_POST['trope']))
{
   $tropeName = Utils::secure(urldecode($_POST['trope']));

   $resStr = '';
   try
   {
      $trope = new Trope($tropeName);
      $trope->delete();
      $resStr = 'OK';
   }
   catch(Exception $e)
   {
      $resStr = 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>
