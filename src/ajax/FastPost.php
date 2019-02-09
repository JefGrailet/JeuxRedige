<?php

/**
* Small script called through AJAX in order to register a new post dynamically, rather than 
* passing by the usual form (with refresh of a page).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Topic.class.php';
require '../model/Post.class.php';
require '../model/Emoticon.class.php';
require '../libraries/FormParsing.lib.php';
require '../libraries/Anonymous.lib.php';

if(!empty($_POST['id_topic']) && preg_match('#^([0-9]+)$#', $_POST['id_topic']) && !empty($_POST['message']))
{
   $topicID = intval(Utils::secure($_POST['id_topic']));
   
   $resStr = '';
   $topic = null;
   
   // 1) Verifications
   try
   {
      $topic = new Topic($topicID);
      
      if(Utils::check($topic->get('is_locked')))
      {
         $resStr = 'Locked';
      }
      else if(!LoggedUser::isLoggedIn() && !Utils::check($topic->get('is_anon_posting_enabled')))
      {
         $resStr = 'No anon';
      }
      else
      {
         // Anonymous posting stuff
         if(!LoggedUser::isLoggedIn())
         {
            $anonPseudo = Anonymous::getPseudo();
            if(strlen($anonPseudo) == 0)
            {
               $inputAnonPseudo = Utils::secure($_POST['pseudo']);
               
               // Randomly generated pseudo
               if(strlen($inputAnonPseudo) == 0)
               {
                  $randAnonPseudo = '';
                  do
                  {
                     $randAnonPseudo = 'Anonyme_'.str_pad(mt_rand(0, pow(10, 4) - 1), 4, '0', STR_PAD_LEFT);
                  }
                  while(!Anonymous::isAvailable($randAnonPseudo));
                  $anonPseudo = $randAnonPseudo;
               }
               else if(strlen($inputAnonPseudo) > 20)
                  $resStr = 'Anon pseudo too long';
               else if(!Anonymous::isAvailable($inputAnonPseudo))
                  $resStr = 'Anon pseudo unavailable';
               else
                  $anonPseudo = $inputAnonPseudo;
            }
            
            // Checks captcha and last activity if no problem so far
            if($resStr === '')
            {
               $lastActivity = Anonymous::lastActivity($anonPseudo);
               if(empty($_POST['captcha']) || sha1(Utils::secure($_POST['captcha'])) != $_SESSION['captcha'])
                  $resStr = 'Anon wrong captcha';
               else if($lastActivity < WebpageHandler::$miscParams['consecutive_anon_posts_delay'] && $lastActivity >= 0)
                  $resStr = 'Anon too many posts';
            }
         }
         // For regular users, we check that they do not create too many messages (max. one every 30s)
         else
         {
            $delay = Post::getUserDelay();
            if($delay < WebpageHandler::$miscParams['consecutive_posts_delay'])
               $resStr = 'Too many posts';
         }
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
      if(!LoggedUser::isLoggedIn())
      {
         $parsedContent = nl2br($content); // No format code for anonymous users
         $newPost = Post::insert($topic->get('id_topic'), $parsedContent, $anonPseudo);
      }
      else
      {
         $parsedContent = Emoticon::parseEmoticonsShortcuts($content);
         $parsedContent = FormParsing::parse($parsedContent);
         $newPost = Post::insert($topic->get('id_topic'), $parsedContent);
         
         // Updating user's view to the new total amount of messages
         $topic->setAllSeen();
      }
      $topic->update($newPost->getAll());
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
