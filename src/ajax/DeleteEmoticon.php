<?php

/**
* Script to delete an emoticon via AJAX. Only an authorized user might be able to perform this 
* operation.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Emoticon.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}
else if(!Utils::check(LoggedUser::$data['can_edit_all_posts']))
{
   exit();
}

if(!empty($_POST['id_emoticon']) && preg_match('#^([0-9]+)$#', $_POST['id_emoticon']))
{
   $emoticonID = Utils::secure($_POST['id_emoticon']);

   $resStr = '';
   try
   {
      $emoticon = new Emoticon($emoticonID);
      $emoticon->delete();
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
