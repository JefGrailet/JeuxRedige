<?php

/**
* Script to change both the name and the suggested shortcut of an emoticon. The logged user must 
* have the rights to perform this operation (authorized user OR uploader of that emoticon).
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

if(!empty($_POST['id_emoticon']) && preg_match('#^([0-9]+)$#', $_POST['id_emoticon']) && !empty($_POST['name']) && !empty($_POST['shortcut']))
{
   $emoticonID = Utils::secure($_POST['id_emoticon']);
   $gotName = Utils::secure($_POST['name']);
   $gotShortcut = Utils::secure($_POST['shortcut']);

   $resStr = '';
   if(!Emoticon::hasGoodFormat($gotShortcut))
   {
      $resStr = 'bad shortcut';
   }
   else
   {
      $emoticon = null;
      try
      {
         $emoticon = new Emoticon($emoticonID);
         if($emoticon->get('name') === $gotName && $emoticon->get('suggested_shortcut') === $gotShortcut)
         {
            $resStr = 'OK';
         }
         else if($emoticon->get('uploader') === LoggedUser::$data['pseudo'] || Utils::check(LoggedUser::$data['can_edit_all_posts']))
         {
            $emoticon->update($gotName, $gotShortcut);
            if($emoticon->isMappedTo(LoggedUser::$data['pseudo']))
               $emoticon->updateMapping(LoggedUser::$data['pseudo'], $gotShortcut);
            $resStr = 'OK';
         }
         else
         {
            $resStr = 'forbidden operation';
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
