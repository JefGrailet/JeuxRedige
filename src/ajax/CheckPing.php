<?php

/**
* This script marks a ping/discussion as seen by the current user. The ID of that ping is given by 
* $_POST.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Ping.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_POST['id_ping']))
{
   $pingToCheck = Utils::secure($_POST['id_ping']);

   try
   {
      $ping = new Ping($pingToCheck);
      $ping->updateView();
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo $e->getMessage();
      exit();
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo 'OK';
}

?>
