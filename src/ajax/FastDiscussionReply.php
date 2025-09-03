<?php

/**
* Small script called through AJAX in order to register a new private message dynamically, rather 
* than passing by the usual form (with refresh of a page).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Ping.class.php';
require '../model/PingPong.class.php';
require '../model/Emoticon.class.php';
require '../libraries/FormParsing.lib.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

if(!empty($_POST['id_ping']) && preg_match('#^([0-9]+)$#', $_POST['id_ping']) && !empty($_POST['message']))
{
   $pingID = intval(Utils::secure($_POST['id_ping']));
   
   $resStr = '';
   $discussion = null;
   
   // 1) Verifications
   try
   {
      $discussion = new PingPong($pingID);
      
      if($discussion->get('state') === 'archived')
         $resStr = 'Archived';
      else
      {
         $delay = PingPong::getUserDelayBis();
         if($delay < WebpageHandler::$miscParams['consecutive_posts_delay'])
            $resStr = 'Too many posts';
      }
   }
   catch(Exception $e)
   {
      $resStr = 'DB error';
   }
   
   // If $resStr is not empty, then it means there is an error.
   if($resStr !== '')
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo $resStr;
      exit(); // Quitting
   }
   
   // 2) Registering the new message (if no issue during verifications)
   $content = Utils::secure($_POST['message']);
   Database::beginTransaction();
   try
   {
      $parsedContent = Emoticon::parseEmoticonsShortcuts($content);
      $parsedContent = FormParsing::parse($parsedContent);
      $discussion->append($parsedContent);
      
      if(!empty($_POST['archive']) && $_POST['archive'] === 'Yes')
         $discussion->archive();
      
      Database::commit();
      $resStr = 'OK';
   }
   catch(Exception $e)
   {
      Database::rollback();
      $resStr = 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>
