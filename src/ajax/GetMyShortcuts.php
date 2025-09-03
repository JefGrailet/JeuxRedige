<?php

/**
* Script to list the user's emoticon shortcuts along the file of the associated emoticons.
*/

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

$resStr = '';
try
{
   $emoticons = Emoticon::getMyShortcuts();
   for($i = 0; $i < count($emoticons); $i++)
   {
      $emoticon = $emoticons[$i];
      $resStr .= '<img class="emoticon" src="'.PathHandler::HTTP_PATH().'upload/emoticons/'.$emoticon['file'].'" alt="'.$emoticon['name'].'" ';
      $resStr .= 'title="'.$emoticon['name'].'" data-shortcut="'.$emoticon['shortcut'].'"/>'."\n";
   }
}
catch(Exception $e)
{
   if(strchr($e->getMessage(), 'No emoticon') !== NULL)
      $resStr = 'Empty library';
   else
      $resStr = 'DB error';
}

header('Content-Type: text/html; charset=UTF-8');
echo $resStr;

?>
