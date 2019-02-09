<?php

/**
* This script can be used by an administrator to handle the avatar of some user (if this avatar 
* is inappropriate) and to give/remove their access to the advanced features (e.g. uploads). The 
* PHP code is a kind of juxtaposition of each part of the page.
*/

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/User.class.php';
require './model/Notification.class.php';
require './view/intermediate/Post.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to manage another user
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$data['can_edit_users']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Retrieves user's data if possible; stops and displays appropriate error message otherwise
$user = null;
if(!empty($_GET['user']))
{
   $getUserString = Utils::secure($_GET['user']);
   
   /*
    * One cannot edit self with this page. If selected user is the same as current user, this script 
    * redirects the current user to the regular personal account edition.
    */
   
   if($getUserString == LoggedUser::$data['pseudo'])
   {
      header('Location:./MyAccount.php');
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap(TemplateEngine::parse('view/user/RedirectionMyAccount.ctpl'), 'Redirection...');
   }
   
   try
   {
      $user = new User($getUserString);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingUser';
      $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
   }
}
else
{
   $tplInput = array('error' => 'missingUser');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
}

// Special scenario for when the user isn't confirmed yet
if(strlen($user->get('confirmation')) == 15)
{
   $tplInput = array('pseudo' => $user->get('pseudo'), 'registration' => '');
   try
   {
      $sponsor = $user->getSponsor();
      $presentation = $user->getPresentation();
      
      if(strlen($sponsor) > 0)
         $tplInput['registration'] = 'sponsor||'.$sponsor;
      else if(strlen($presentation) > 0)
         $tplInput['registration'] = 'presentation||'.$presentation;
      else
         $tplInput['registration'] = 'nothing';
   }
   catch(Exception $e)
   {
      $tplInput['registration'] = 'dbError';
   }
   
   $tpl = TemplateEngine::parse('view/user/EditUser.unconfirmed.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Cet utilisateur n\'est pas confirmé');
}

// Webpage settings
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('topic_medium');
else
   WebpageHandler::addCSS('topic');
WebpageHandler::addJS('topic_interaction');
WebpageHandler::noContainer();

// Each display corresponds to a part of the data to manage
$display1 = '';
$display2 = '';
$display3 = ''; // To display details about this user's registration (sponsor/presentation)
$display4 = '';
$display5 = ''; // For relaxing sentences, but only appears if there is an active sentence

// Input for the avatar form template (avatar path must be provided)
$prevMsgSize = WebpageHandler::$miscParams['message_size'];
WebpageHandler::$miscParams['message_size'] = 'default'; // Forces to use default avatar size

$avatarTplInput = array('avatar' => PathHandler::getAvatar($user->get('pseudo')),
'pseudo' => $user->get('pseudo'),
'success' => '',
'error' => '',
'form' => './EditUser.php?user='.$user->get('pseudo'));

WebpageHandler::$miscParams['message_size'] = $prevMsgSize;

// Input for the form modifying access to the advanced features
$advFeatTplInput = array('display' => 'noAccess',
'pseudo' => $user->get('pseudo'));

// Input for the banish form
$banishTplInput = array('sentences' => '',
'display' => '',
'pseudo' => $user->get('pseudo'),
'buttonName' => 'ban');

// Input for the relax form (N.B.: not always showed)
$relaxTplInput = array('pseudo' => $user->get('pseudo'), 
'display' => '');

// Some additions depending on some specific data
if(Utils::check($user->get('advanced_features')))
   $advFeatTplInput['display'] = 'hasAccess';

// Is the user currently banned ?
$isBanned = Utils::toTimestamp($user->get('last_ban_expiration')) > Utils::SQLServerTime();

$relaxSuccess = false; // Because .ctpl for success is not the regular form with a new message

// Starts dealing with forms
if(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'avatar' && !empty($_FILES['image']))
{
   require './libraries/Upload.lib.php';
   
   // Form input (never kept from one "try" to another)
   $uploaded = $_FILES['image'];
   $extension = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
   
   if($uploaded['error'] != 0)
      $avatarTplInput['error'] = 'uploadError';
   elseif($uploaded['size'] > 1048576)
      $avatarTplInput['error'] = 'tooBig';
   elseif(($uploaded['size'] + Upload::directorySize('avatars')) > (2 * 1024 * 1024 * 1024))
      $avatarTplInput['error'] = 'notEnoughSpace';
   elseif($extension != 'jpeg' && $extension != 'jpg')
      $avatarTplInput['error'] = 'notJPEG';
   else
   {
      // Generates the new avatar
      $res1 = Upload::storeResizedPicture($uploaded, 'avatars', 125, 125, $user->get('pseudo'));
      $res2 = Upload::storeResizedPicture($uploaded, 'avatars', 100, 100, $user->get('pseudo').'-medium');
      $res3 = Upload::storeResizedPicture($uploaded, 'avatars', 40, 40, $user->get('pseudo').'-small');
      
      if(strlen($res1) > 0 && strlen($res2) > 0 && strlen($res3) > 0)
         $avatarTplInput['success'] = 'OK';
      else
         $avatarTplInput['error'] = 'resizeError';
   }
}
// Advanced features allowance switch
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'advancedFeatures')
{
   $detailedMotif = nl2br(Utils::secure($_POST['motif']));
   
   if(strlen($detailedMotif) == 0)
   {
      $advFeatTplInput['display'] = 'missingMotif';
   }
   else
   {
      try
      {
         $res = $user->updateAdvancedFeatures();
         $notificationTitle = '';
         if($res)
         {
            $advFeatTplInput['display'] = 'hasNowAccess';
            $notificationTitle = 'Fonctionnalités avancées activées';
         }
         else
         {
            $advFeatTplInput['display'] = 'noMoreAccess';
            $notificationTitle = 'Fonctionnalités avancées désactivées';
         }
         
         // Notification
         $notificationInput = array('admin' => LoggedUser::$data['used_pseudo'], 
                                    'messageType' => $advFeatTplInput['display'],
                                    'justification' => $detailedMotif);
         $notificationMsg = TemplateEngine::parse('view/user/AdvancedFeatures.notification.ctpl', $notificationInput);
         if(!TemplateEngine::hasFailed($notificationMsg))
         {
            Notification::insert($user->get('pseudo'), $notificationTitle, $notificationMsg);
         }
      }
      catch(Exception $e)
      {
         $advFeatTplInput['display'] = 'dbError';
      }
   }
}
// Banish form
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'banishment' &&
       !empty($_POST['duration']) && preg_match('#^([0-9]+)$#', $_POST['duration']))
{
   $selectedDuration = intval($_POST['duration']);
   $detailedMotif = nl2br(Utils::secure($_POST['motif']));
   $durationSeconds = $selectedDuration * 24 * 60 * 60;
   
   if(strlen($detailedMotif) == 0)
   {
      $banishTplInput['display'] = 'missingMotif';
   }
   else
   {
      Database::beginTransaction();
      try
      {
         $user->banish($durationSeconds);
         $user->recordSentence($durationSeconds, $detailedMotif);
         if($banishTplInput['buttonName'] == 'extend')
            $banishTplInput['display'] = 'extended||'.$selectedDuration;
         else
            $banishTplInput['display'] = 'banned||'.$selectedDuration;
         Database::commit();
         
         $isBanned = true;
      }
      catch(Exception $e)
      {
         Database::rollback();
         $banishTplInput['display'] = 'dbError';
      }
   }
}
// Relax form
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'relax' && $isBanned)
{
   $detailedMotif = nl2br(Utils::secure($_POST['motif']));
   
   if(strlen($detailedMotif) == 0)
   {
      $relaxTplInput['display'] = 'missingMotif';
   }
   else
   {
      Database::beginTransaction();
      try
      {
         $user->relax();
         $user->relaxSentences();
         $relaxSuccess = true;
         
         Database::commit();
         
         $isBanned = false;
         
         // Sends a notification
         $notificationInput = array('admin' => LoggedUser::$data['used_pseudo'], 
                                    'justification' => $detailedMotif);
         $notificationTitle = 'Vous avez été relaxé';
         $notificationMsg = TemplateEngine::parse('view/user/Relax.notification.ctpl', $notificationInput);
         if(!TemplateEngine::hasFailed($notificationMsg))
         {
            Notification::insert($user->get('pseudo'), $notificationTitle, $notificationMsg);
         }
      }
      catch(Exception $e)
      {
         Database::rollback();
         $relaxTplInput['display'] = 'dbError';
      }
   }
}

// Prepares the details about the registration
$registrationTplInput = array('registration' => '');
try
{
   $sponsor = $user->getSponsor();
   $presentation = $user->getPresentation();
   
   if(strlen($sponsor) > 0)
      $registrationTplInput['registration'] = 'sponsor||'.$sponsor;
   else if(strlen($presentation) > 0)
      $registrationTplInput['registration'] = 'presentation||'.$presentation;
   else
      $registrationTplInput['registration'] = 'nothing';
}
catch(Exception $e) {}

// Prepares the list of sentences for that user
try
{
   $sentences = $user->listSentences();
   
   $banishTplInput['sentences'] = "<strong>Historique des bannissements</strong><br/><br/>\n";
   if(count($sentences) == 0)
   {
      $banishTplInput['sentences'] .= TemplateEngine::parse('view/user/Sentences.empty.ctpl');
   }
   else
   {
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
            $banishTplInput['sentences'] .= $sentenceTpl;
         else
            WebpageHandler::wrap($sentenceTpl, 'Une erreur est survenue lors de la lecture des logs');
      }
   }
}
catch(Exception $e)
{
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', array('error' => 'dbError'));
   WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
}

// Additionnal stuff for banish form
if($isBanned)
   $banishTplInput['buttonName'] = 'extend';

// Produces all parts of the form
$display1 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
$display2 = TemplateEngine::parse('view/user/AdvancedFeatures.form.ctpl', $advFeatTplInput);
if(strlen($registrationTplInput['registration']) > 0)
   $display3 = TemplateEngine::parse('view/user/UserRegistration.ctpl', $registrationTplInput);
else
   $display3 = TemplateEngine::parse('view/user/UserRegistration.fail.ctpl');
$display4 = TemplateEngine::parse('view/user/Banish.form.ctpl', $banishTplInput);
if($isBanned && !$relaxSuccess)
   $display5 = TemplateEngine::parse('view/user/Relax.form.ctpl', $relaxTplInput);
else if($relaxSuccess)
   $display5 = TemplateEngine::parse('view/user/Relax.success.ctpl', $relaxTplInput);

$display = WebpageHandler::wrapInBlock($display1);
$display .= WebpageHandler::wrapInBlock($display2);
$display .= WebpageHandler::wrapInBlock($display3);
$display .= WebpageHandler::wrapInBlock($display4);
if($relaxSuccess || $isBanned)
   $display .= WebpageHandler::wrapInBlock($display5);

// Prepares the final HTML page
$finalTplInput = array('editionBlocks' => $display, 
'userLastMessages' => '');

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
      $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', array('error' => 'dbError'));
      WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
   }
}

// Dialog for showing interactions with this user's last messages
$dialogs = '';
$interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.dialog.ctpl');
if(!TemplateEngine::hasFailed($interactionsTpl))
   $dialogs .= $interactionsTpl;

$finalPage = TemplateEngine::parse('view/user/EditUser.composite.ctpl', $finalTplInput);
WebpageHandler::wrap($finalPage, 'Gestion de l\'utilisateur '.$user->get('pseudo'), $dialogs);
?>
