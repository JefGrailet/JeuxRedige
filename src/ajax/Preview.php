<?php

/**
* Small script called through AJAX in order to preview any content that allows format code (forum 
* message, segment of an article, miscellaneous content, etc.) after parsing that code.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/FormParsing.lib.php';

if(LoggedUser::isLoggedIn() && isset($_POST['what']) && isset($_POST['message']))
{
   $getContentType = Utils::secure($_POST['what']);
   
   // segment => articles, content => side (beta) content, message => forum/pings
   $allowedTypes = array('segment', 'content', 'message');
   $whatContent = $allowedTypes[count($allowedTypes) - 1]; // Default
   if(in_array($getContentType, $allowedTypes))
      $whatContent = $getContentType;
   
   // Segment and message parsing slightly differs
   if($whatContent === 'segment')
   {
      require '../libraries/SegmentParsing.lib.php';
      
      $parsedContent = SegmentParsing::parse(FormParsing::parse(Utils::secure($_POST['message'])));
      
      header('Content-Type: text/html; charset=UTF-8');
      echo $parsedContent;
   }
   else
   {
      require '../libraries/MessageParsing.lib.php';
      require '../model/Emoticon.class.php';
      
      $parsedContent = Utils::secure($_POST['message']);
      // The "content" type do not allow emoticons (contrary to "message")
      if($whatContent !== 'content')
      {
         try
         {
            $parsedContent = Emoticon::parseEmoticonsShortcuts($parsedContent);
         }
         catch(Exception $e) {}
      }
      $parsedContent = MessageParsing::parse(FormParsing::parse($parsedContent));
      
      header('Content-Type: text/html; charset=UTF-8');
      echo $parsedContent;
   }
}

?>
