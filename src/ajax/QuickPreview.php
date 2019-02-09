<?php

/**
* Small script called through AJAX in order to preview a message using format code (content only).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/FormParsing.lib.php';
require '../libraries/MessageParsing.lib.php';
require '../model/Emoticon.class.php';

if(LoggedUser::isLoggedIn() && isset($_POST['message']))
{   
   $parsedContent = Utils::secure($_POST['message']);
   try
   {
      $parsedContent = Emoticon::parseEmoticonsShortcuts($parsedContent);
   }
   catch(Exception $e)
   {
   }
   $parsedContent = MessageParsing::parse(FormParsing::parse($parsedContent));
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $parsedContent;
}

?>
