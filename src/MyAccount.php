<?php

/**
* This script can be used by the user to edit his/her password, avatar and e-mail address while
* browsing a single page (instead of having a kind of menu). The PHP code is a kind of
* juxtaposition of each part of the page.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::noRedirectionAtLoggingOut();

// User must be (of course) logged in to see this page.
if(!LoggedUser::isLoggedIn())
{
   WebpageHandler::wrap(TemplateEngine::parse('view/user/NotLoggedIn.ctpl'), 'Erreur');
}

WebpageHandler::changeContainer('contentMultiple');

require './model/User.class.php';
$user = new User(LoggedUser::$fullData);

/*
 * Each display for a part of the data to edit (respectively password, avatar, e-mail address, 
 * preferences, access to advanced features and sentence history).
 */

$display1 = '';
$display2 = '';
$display3 = '';
$display4 = '';
$display5 = '';
$display6 = '';

// Input for the avatar form template (avatar path must be provided)
$avatarTplInput = array('avatar' => PathHandler::getAvatar(LoggedUser::$data['used_pseudo']),
'pseudo' => LoggedUser::$data['used_pseudo'],
'success' => '',
'error' => '',
'form' => './MyAccount.php');

// Input for the e-mail edition form template (old e-mail address must be provided)
$emailTplInput = array('oldEmail' => $user->get('email'),
'pwd' => '',
'newEmail' => '',
'errors' => '');

// Input for the preferences edition form template
$prefTplInput = array('outcome' => '', 
'using_preferences' => $user->get('using_preferences').'||yes,Oui|no,Non', 
'message_size' => $user->get('pref_message_size').'||default,Taille par défaut|medium,Taille "Medium"', 
'posts_per_page' => $user->get('pref_posts_per_page').'||'.implode('|', range(5, 100)), 
'video_default_display' => $user->get('pref_video_default_display').'||thumbnail,Vignette cliquable|embedded,Intégration directe', 
'video_thumbnail_style' => $user->get('pref_video_thumbnail_style').'||hq,Grande taille (480x360 pixels)|small,Petite taille (120x90 pixels)', 
'default_nav_mode' => $user->get('pref_default_nav_mode').'||classic,Pagination classique|dynamic,Pagination dynamique|flow,Flot continu de messages', 
'auto_preview' => $user->get('pref_auto_preview').'||yes,Toujours actif|no,Sur demande', 
'auto_refresh' => $user->get('pref_auto_refresh').'||yes,Toujours actif|no,Sur demande');

// Input for displaying if the user has access to advanced features or not
$advFeatTplInput = array('display' => 'noAccess');

if(Utils::check($user->get('advanced_features')))
   $advFeatTplInput['display'] = 'hasAccess';

// Password edition
if(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'password')
{
   $data = array('oldPwd' => Utils::secure($_POST['oldPwd']),
                 'newPwd' => Utils::secure($_POST['newPwd']));
   $recomputedHash = sha1($user->get('pseudo') . $user->get('secret') . $data['oldPwd']);
   
   /*
    * Remark: the "recomputedHash" isn't the hash actually (currently) stored in the DB. It's an 
    * intermediate hash that used to be stored in the DB and which is further hashed with bcrypt 
    * in order to ensure old hashes could be still used without asking users to give again their 
    * passwords upon bringing the bcrypt solution. This solution has also a nice twist: as sha1() 
    * always returns 40 characters long hashes and bcrypt truncates 60+ characters passwords, this 
    * allows users to potentially use any length of password.
    */
   
   // Deals with errors (empty fields, new password too long, etc.)
   $errors = '';
   if(strlen($data['oldPwd']) == 0 || strlen($data['newPwd']) == 0)
      $errors .= 'emptyFields|';
   if(!password_verify($recomputedHash, $user->get('password'))) // bcrypt verification
      $errors .= 'wrongCurrentPwd|';
   if(strlen($data['newPwd']) > 200)
      $errors .= 'pwdTooLong|';
   
   if($errors !== '')
   {
      $data['errors'] = substr($errors, 0, -1);
      $data['success'] = ''; // Avoids CTPL error because "success" field is missing
      $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl', $data);
   }
   else
   {
      // Tries to edit the password and changes the display accordingly.
      try
      {
         $user->setPassword($data['newPwd']);
         
         // Cookies and $_SESSION must be reset
         $_SESSION['password'] = $user->get('password');
         if(isset($_COOKIE['password']) && !empty($_COOKIE['password']))
            $_COOKIE['password'] = $user->get('password');
         
         $data['errors'] = '';
         $data['success'] = 'OK';
      }
      catch(Exception $e)
      {
         $data['errors'] = $errors.'dbError';
         $data['success'] = '';
      }
      $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl', $data);
   }
   
   $display2 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
   $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
   $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   $display5 = TemplateEngine::parse('view/user/AdvancedFeatures.display.ctpl', $advFeatTplInput);
}
// Avatar edition (upload library is required)
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'avatar' && !empty($_FILES['image']))
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
      // Generates the new avatar (3 sizes)
      $res1 = Upload::storeResizedPicture($uploaded, 'avatars', 125, 125, LoggedUser::$data['used_pseudo']);
      $res2 = Upload::storeResizedPicture($uploaded, 'avatars', 100, 100, LoggedUser::$data['used_pseudo'].'-medium');
      $res3 = Upload::storeResizedPicture($uploaded, 'avatars', 30, 30, LoggedUser::$data['used_pseudo'].'-small');
      
      if(strlen($res1) > 0 && strlen($res2) > 0 && strlen($res3) > 0)
         $avatarTplInput['success'] = 'OK';
      else
         $avatarTplInput['error'] = 'resizeError';
   }
   
   $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl');
   $display2 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
   $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
   $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   $display5 = TemplateEngine::parse('view/user/AdvancedFeatures.display.ctpl', $advFeatTplInput);
}
// E-mail address edition
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'email')
{
   require './libraries/Mailing.lib.php';

   // Input data
   $data = array('pwd' => Utils::secure($_POST['pwd']),
                 'newEmail' => Utils::secure($_POST['newEmail']));
   $recomputedHash = sha1($user->get('pseudo') . $user->get('secret') . $data['pwd']);
   
   /*
    * Regarding "recomputedHash": see comment about the same topic in password edition form.
    */
   
   // Copy of input data in the input data for the e-mail edition form template
   $emailTplInput['pwd'] = $data['pwd'];
   $emailTplInput['newEmail'] = $data['newEmail'];
   
   // Deals with errors (empty fields, new password too long, etc.)
   $errors = '';
   if(strlen($data['pwd']) == 0 || strlen($data['newEmail']) == 0)
      $errors .= 'emptyFields|';
   if(!password_verify($recomputedHash, $user->get('password'))) // bcrypt verification
      $errors .= 'wrongCurrentPwd|';
   if(strlen($data['newEmail']) > 60)
      $errors .= 'emailTooLong|';
   if($data['newEmail'] === $user->get('email'))
      $errors .= 'alreadyUsed|';

   if($errors !== '')
   {
      $emailTplInput['errors'] = substr($errors, 0, -1);
      $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
   }
   else
   {
      // Next operations may cause exceptions (while checking availability and saving new address)
      try
      {
         if(User::isEmailUsed($data['newEmail']))
         {
            $emailTplInput['errors'] = 'usedBySomeoneElse';
            $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
         }
         else
         {
            $user->editEmail($data['newEmail']);
         
            // Generates confirmation e-mail
            $confirmKey = $user->get('confirmation');
            $confirmLink = 'https://'.$_SERVER['HTTP_HOST'].'/Confirmation.php?pseudo='.LoggedUser::$data['pseudo'].'&key='.$confirmKey;
            $emailInput = array('pseudo' => LoggedUser::$data['pseudo'], 'confirmLink' => $confirmLink);
            $emailTitle = 'Modification de votre adresse e-mail';
            $emailContent = TemplateEngine::parse('view/user/EmailEdition.mail.ctpl', $emailInput);
            
            // Sends it and checks if it was successfully sent
            $mailSuccess = false;
            if(!TemplateEngine::hasFailed($emailContent))
            {
               if(Mailing::send($data['newEmail'], $emailTitle, $emailContent))
                  $mailSuccess = true;
            }
            
            // Generates output page (not displayed yet)
            $tplInput = array('email' => 'emailFail');
            if($mailSuccess)
               $tplInput['email'] = 'emailSuccess';
            $display3 = TemplateEngine::parse('view/user/EmailEdition.success.ctpl', $tplInput);
         
            // User is logged out
            LoggedUser::$data = NULL;
            LoggedUser::$fullData = NULL;
            LoggedUser::$messages = NULL;
            if(!empty($_COOKIE['pseudonym']) && !empty($_COOKIE['password']))
            {
               $expire = Utils::SQLServerTime() - 10000;
               setcookie('pseudonym', '', $expire);
               setcookie('password', '', $expire);
            }
            session_destroy();
            
            WebpageHandler::resetDisplay(); // Regular page display
            WebpageHandler::wrap($display3, "Mon compte");
         }
      }
      // Error: dialog with the DB could not be properly done
      catch(Exception $e)
      {
         $emailTplInput['errors'] = 'dbError';
         $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
      }
   }
   
   $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl');
   $display2 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
   $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   $display5 = TemplateEngine::parse('view/user/AdvancedFeatures.display.ctpl', $advFeatTplInput);
}
// Preferences edition
elseif(!empty($_POST['sent']) && $_POST['dataToEdit'] === 'preferences')
{
   $data = array('using_preferences' => Utils::secure($_POST['using_preferences']), 
                 'message_size' => Utils::secure($_POST['message_size']), 
                 'posts_per_page' => Utils::secure($_POST['posts_per_page']), 
                 'video_default_display' => Utils::secure($_POST['video_default_display']), 
                 'video_thumbnail_style' => Utils::secure($_POST['video_thumbnail_style']), 
                 'default_nav_mode' => Utils::secure($_POST['default_nav_mode']), 
                 'auto_preview' => Utils::secure($_POST['auto_preview']), 
                 'auto_refresh' => Utils::secure($_POST['auto_refresh']));
   
   $acceptedInput = array(array('yes', 'no'), 
   array('default', 'medium'), 
   range(5,100), 
   array('thumbnail', 'embedded'), 
   array('hq', 'small'), 
   array('classic', 'dynamic', 'flow'), 
   array('yes', 'no'), 
   array('yes', 'no'));
   
   // Deals with errors (empty fields, new password too long, etc.)
   $errors = '';
   if(!in_array($data['using_preferences'], $acceptedInput[0]) || 
      !in_array($data['message_size'], $acceptedInput[1]) || 
      !in_array(intval($data['posts_per_page']), $acceptedInput[2]) || 
      !in_array($data['video_default_display'], $acceptedInput[3]) || 
      !in_array($data['video_thumbnail_style'], $acceptedInput[4]) || 
      !in_array($data['default_nav_mode'], $acceptedInput[5]) || 
      !in_array($data['auto_preview'], $acceptedInput[6]) || 
      !in_array($data['auto_refresh'], $acceptedInput[7]))
   {
      $prefTplInput['outcome'] = 'incorrectInput';
      $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   }
   else
   {
      // Tries to edit preferences and changes the display accordingly.
      try
      {
         $user->updatePreferences($data);
         $prefTplInput['outcome'] = 'OK';
         
         $prefTplInput['using_preferences'] = $user->get('using_preferences').'||yes,Oui|no,Non';
         $prefTplInput['message_size'] = $user->get('pref_message_size').'||default,Taille par défaut|medium,Taille "Medium"';
         $prefTplInput['posts_per_page'] = $user->get('pref_posts_per_page').'||'.implode('|', range(5, 100));
         $prefTplInput['video_default_display'] = $user->get('pref_video_default_display').'||thumbnail,Vignette cliquable|embedded,Intégration directe';
         $prefTplInput['video_thumbnail_style'] = $user->get('pref_video_thumbnail_style').'||hq,Grande taille (480x360 pixels)|small,Petite taille (120x90 pixels)';
         $prefTplInput['default_nav_mode'] = $user->get('pref_default_nav_mode').'||classic,Pagination classique';
         $prefTplInput['default_nav_mode'] .= '|dynamic,Pagination dynamique';
         $prefTplInput['default_nav_mode'] .= '|flow,Flot continu de messages';
         $prefTplInput['auto_preview'] = $user->get('pref_auto_preview').'||yes,Toujours actif|no,Sur demande';
         $prefTplInput['auto_refresh'] = $user->get('pref_auto_refresh').'||yes,Toujours actif|no,Sur demande';
      }
      catch(Exception $e)
      {
         $prefTplInput['outcome'] = 'dbError';
      }
      
      $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   }

   $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl');
   $display2 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
   $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
   $display5 = TemplateEngine::parse('view/user/AdvancedFeatures.display.ctpl', $advFeatTplInput);
}
else
{
   $display1 = TemplateEngine::parse('view/user/PasswordEdition.form.ctpl');
   $display2 = TemplateEngine::parse('view/user/AvatarEdition.form.ctpl', $avatarTplInput);
   $display3 = TemplateEngine::parse('view/user/EmailEdition.form.ctpl', $emailTplInput);
   $display4 = TemplateEngine::parse('view/user/Preferences.form.ctpl', $prefTplInput);
   $display5 = TemplateEngine::parse('view/user/AdvancedFeatures.display.ctpl', $advFeatTplInput);
}

// Sentences history
try
{
   $sentences = $user->listSentences();
   if(count($sentences) == 0)
   {
      $display6 = TemplateEngine::parse('view/user/BanishHistory.empty.ctpl');
   }
   else
   {
      $sentencesTplInput = array('sentences' => '');
   
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
         
         $sentenceTpl = TemplateEngine::parse('view/user/Sentence.item.ctpl', $sentenceTplInput);
            
         if(!TemplateEngine::hasFailed($sentenceTpl))
            $sentencesTplInput['sentences'] .= $sentenceTpl;
         else
            WebpageHandler::wrap($sentenceTpl, 'Une erreur est survenue lors de la lecture des logs');
      }
      
      $display6 = TemplateEngine::parse('view/user/BanishHistory.ctpl', $sentencesTplInput);
   }
}
catch(Exception $e)
{
   $display6 = TemplateEngine::parse('view/user/BanishHistory.error.ctpl');
}

$display = WebpageHandler::wrapInBlock($display1);
$display .= WebpageHandler::wrapInBlock($display2);
$display .= WebpageHandler::wrapInBlock($display3);
$display .= WebpageHandler::wrapInBlock($display4);
$display .= WebpageHandler::wrapInBlock($display5);
$display .= WebpageHandler::wrapInBlock($display6);

WebpageHandler::wrap($display, 'Mon compte');
?>
