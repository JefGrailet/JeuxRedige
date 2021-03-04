<?php

/**
* This script displays the published articles and last messages of some user. It also provides 
* some general information.
*/

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/User.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';
require './view/intermediate/Post.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Retrieves user's data if possible; stops and displays appropriate error message otherwise
$user = null;
if(!empty($_GET['user']))
{
   $getUserString = Utils::secure($_GET['user']);
   
   try
   {
      $user = new User($getUserString);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingUser';
      $tpl = TemplateEngine::parse('view/user/User.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
   }
}
else
{
   $tplInput = array('error' => 'missingUser');
   $tpl = TemplateEngine::parse('view/user/User.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
}

// Webpage settings
WebpageHandler::addCSS('user_profile');
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('topic_medium');
else
   WebpageHandler::addCSS('topic');
WebpageHandler::addJS('topic_interaction');
WebpageHandler::noContainer();

// Gets main details and full-size avatar
$prevMsgSize = WebpageHandler::$miscParams['message_size'];
WebpageHandler::$miscParams['message_size'] = 'default';

$finalTplInput = array('pseudo' => $user->get('pseudo'), 
'avatar' => PathHandler::getAvatar($user->get('pseudo')), 
'registrationDate' => date('d/m/Y \à H\hi', Utils::toTimestamp($user->get('registration_date'))), 
'lastConnection' => date('d/m/Y \à H\hi', Utils::toTimestamp($user->get('last_connection'))), 
'advFeatures' => Utils::check($user->get('advanced_features')) ? 'yes' : '', 
'banned' => (Utils::toTimestamp($user->get('last_ban_expiration')) > Utils::SQLServerTime()) ? 'yes' : '', 
'sentences' => '', 
'articles' => '', 
'userLastMessages' => '');

WebpageHandler::$miscParams['message_size'] = $prevMsgSize;

// Prepares the list of sentences for that user
try
{
   $sentences = $user->listSentences();
   
   if(count($sentences) > 0)
   {
      $finalTplInput['sentences'] = "\n<br/>\n<strong>Historique des bannissements</strong><br/>\n<br/>\n";
      for($i = 0; $i < count($sentences); $i++)
      {
         $durationDays = $sentences[$i]['duration'] / (60 * 60 * 24);
         $dateStr = date('d/m/Y à H:i:s', Utils::toTimestamp($sentences[$i]['date']));
         $expiration = Utils::toTimestamp($sentences[$i]['date']) + $sentences[$i]['duration'];
         
         $sentenceTplInput = array('active' => '',
         'nbDays' => $durationDays,
         'date' => $dateStr,
         'banisher' => $sentences[$i]['judge'],
         'timestamp' => Utils::toTimestamp($sentences[$i]['date']),
         'motif' => $sentences[$i]['details']);
         
         if(Utils::check($sentences[$i]['relaxed']))
            $sentenceTplInput['special'] = 'relaxed';
         else if($expiration > Utils::SQLServerTime())
            $sentenceTplInput['special'] = 'active';
         
         $sentenceTpl = TemplateEngine::parse('view/user/Sentence.item.ctpl', $sentenceTplInput);
            
         if(!TemplateEngine::hasFailed($sentenceTpl))
            $finalTplInput['sentences'] .= $sentenceTpl;
         else
            WebpageHandler::wrap($sentenceTpl, 'Impossible de consulter l\'utilisateur');
      }
   }
}
catch(Exception $e)
{
   $tpl = TemplateEngine::parse('view/user/User.fail.ctpl', array('error' => 'dbError'));
   WebpageHandler::wrap($tpl, 'Impossible de consulter l\'utilisateur');
}

// Articles published by that user
try
{
   $articles = $user->getArticles();
   if($articles != NULL)
   {
      $articlesTplInput = array('user' => $user->get('pseudo'), 'articles' => '');
      $fullInput = array();
      for($i = 0; $i < count($articles); $i++)
      {
         $intermediate = ArticleThumbnailIR::process($articles[$i], false, false);
         array_push($fullInput, $intermediate);
      }
      
      if(count($fullInput) > 0)
      {
         $fullOutput = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $fullInput);
         if(!TemplateEngine::hasFailed($fullOutput))
         {
            for($i = 0; $i < count($fullOutput); $i++)
               $articlesTplInput['articles'] .= $fullOutput[$i];
         }
      }
      
      if(strlen($articlesTplInput['articles']) > 0)
      {
         $artTplInput = TemplateEngine::parse('view/user/Articles.ctpl', $articlesTplInput);
         $finalTplInput['articles'] = $artTplInput;
      }
   }
}
catch(Exception $e)
{
   if(strstr($e->getMessage(), 'No article has been found') == FALSE)
   {
      $tpl = TemplateEngine::parse('view/user/User.fail.ctpl', array('error' => 'dbError'));
      WebpageHandler::wrap($tpl, 'Impossible de consulter l\'utilisateur');
   }
}

// 5 last messages from that user
try
{
   $posts = $user->getPosts(0, 5, true);
   
   if($posts != NULL)
   {
      $lastMsgTplInput = array('user' => $user->get('pseudo'), 'posts' => '');
      
      for($i = 0; $i < count($posts); $i++)
      {
         $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($i + 1));
         $posts[$i]['content'] = MessageParsing::removeReferences($posts[$i]['content']);

         $postIR = PostIR::process($posts[$i], 0, false);
         $postTpl = TemplateEngine::parse('view/content/Post.ctpl', $postIR);
         
         if(!TemplateEngine::hasFailed($postTpl))
            $lastMsgTplInput['posts'] .= $postTpl;
         else
            WebpageHandler::wrap($postTpl, 'Une erreur est survenue lors de la lecture des messages');
      }
      
      $msgTplInput = TemplateEngine::parse('view/user/LastMessages.ctpl', $lastMsgTplInput);
      $finalTplInput['userLastMessages'] = $msgTplInput;
   }
   else
   {
      $lastMsgTplInput = array('user' => $user->get('pseudo'));
      $msgTplInput = TemplateEngine::parse('view/user/LastMessages.empty.ctpl', $lastMsgTplInput);
      $finalTplInput['userLastMessages'] = $msgTplInput;
   }
}
catch(Exception $e)
{
   if(strstr($e->getMessage(), 'No message has been found') == FALSE)
   {
      $tpl = TemplateEngine::parse('view/user/User.fail.ctpl', array('error' => 'dbError'));
      WebpageHandler::wrap($tpl, 'Impossible de consulter l\'utilisateur');
   }
}

// Dialog for showing interactions with this user's last messages
$dialogs = '';
$interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.multiple.ctpl');
if(!TemplateEngine::hasFailed($interactionsTpl))
   $dialogs .= $interactionsTpl;

$finalPage = TemplateEngine::parse('view/user/User.composite.ctpl', $finalTplInput);
WebpageHandler::wrap($finalPage, 'A propos de '.$user->get('pseudo'), $dialogs);
?>
