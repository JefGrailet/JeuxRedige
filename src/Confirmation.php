<?php

/**
* This script is used to confirm new account or new e-mail addresses. It simply receives a $_GET
* variable and uses it to confirm the account, producing either an error either a success page.
*/

require './libraries/Header.lib.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'confirmation');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './model/User.class.php';
require './libraries/Mailing.lib.php';

if(!empty($_GET['pseudo']))
{
   $pseudo = Utils::secure($_GET['pseudo']);
   
   // Getting the account and checking there is no error.
   try
   {
      $user = new User($pseudo);
      
      if($user->get('confirmation') == 'DONE')
      {
         $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', array('error' => 'alreadyConfirmed'));
         WebpageHandler::wrap($tplRes, 'Ce compte est déjà confirmé');
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => '');
      if(strstr($e->getMessage(), 'does not exist') !== FALSE)
         $tplInput['error'] = 'nonexistentAccount';
      else
         $tplInput['error'] = 'dbError1';
      $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tplRes, 'Erreur lors de la confirmation');
   }
   
   // Proceeds by checking the confirmation key
   if(!empty($_GET['key']))
   {
      $confirmKey = Utils::secure($_GET['key']);
      $dbKey = $user->get('confirmation');
      
      if($confirmKey == $dbKey)
      {
         try
         {
            // Key length indicates whether we are confirming a new account or a new e-mail address
            if(strlen($dbKey) == 15)
            {
               $user->confirm();
               
               $inputEmail = array('pseudo' => $pseudo, 'type' => 'newAccount');
               $tplRes = TemplateEngine::parse('view/user/Confirmation.success.ctpl', array('type' => 'newAccount'));
               $titleRes = 'Bienvenue sur JeuxRédige !';
            }
            elseif(strlen($dbKey) == 10)
            {
               $user->confirmEmail();
               
               $inputEmail = array('pseudo' => $pseudo, 'type' => 'newEmail');
               $tplRes = TemplateEngine::parse('view/user/Confirmation.success.ctpl', array('type' => 'newEmail'));
               $titleRes = 'Votre nouvelle adresse a été confirmée';
            }
            
            $emailContent = TemplateEngine::parse('view/user/Confirmation.mail.ctpl', $inputEmail);
            if(!TemplateEngine::hasFailed($emailContent))
               Mailing::send($user->get('email'), 'Confirmation de votre compte', $emailContent);
            WebpageHandler::wrap($tplRes, $titleRes);
         }
         // Error with SQL
         catch(Exception $e)
         {
            $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', array('error' => 'dbError2'));
            WebpageHandler::wrap($tplRes, 'Erreur lors de la confirmation');
         }
      }
      else
      {
         $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', array('error' => 'wrongKey'));
         WebpageHandler::wrap($tplRes, 'Erreur lors de la confirmation');
      }
   }
   else
   {
      $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', array('error' => 'missingKey'));
      WebpageHandler::wrap($tplRes, 'Erreur lors de la confirmation');
   }
}
else
{
   $tplRes = TemplateEngine::parse('view/user/Confirmation.fail.ctpl', array('error' => 'missingPseudo'));
   WebpageHandler::wrap($tplRes, 'Erreur lors de la confirmation');
}
?>
