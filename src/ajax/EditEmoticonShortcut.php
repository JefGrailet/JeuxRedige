<?php

/**
* Script to change the shortcut chosen by some user mapped to an emoticon.
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

if(!empty($_POST['id_emoticon']) && preg_match('#^([0-9]+)$#', $_POST['id_emoticon']) && !empty($_POST['shortcut']))
{
   $emoticonID = Utils::secure($_POST['id_emoticon']);
   $gotShortcut = Utils::secure($_POST['shortcut']);

   $resStr = '';
   if(!Emoticon::hasGoodFormat($gotShortcut))
   {
      $resStr = 'bad shortcut';
   }
   else
   {
      try
      {
         $emoticon = new Emoticon($emoticonID);
         if($emoticon->isMappedTo(LoggedUser::$data['pseudo']))
         {
            if($emoticon->get('shortcut') === $gotShortcut)
            {
               $resStr = 'OK';
            }
            else
            {
               $emoticon->updateMapping(LoggedUser::$data['pseudo'], $gotShortcut);
               $resStr = 'OK';
            }
         }
         else
         {
            $resStr = 'no mapping';
         }
      }
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'uplicat') != FALSE)
            $resStr = 'duplicate shortcut';
         else
            $resStr = 'DB error';
      }
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>
