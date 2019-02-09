<?php

/**
* This script is used to create a new account via the sponsorship process. It is a sort of mix 
* between the registration and confirmation, as here, the confirmation is not required afterwards 
* (it is already done through the invitation key provided in the invitation e-mail).
*/

require './libraries/Header.lib.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'registration');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './model/User.class.php';
require './model/Invitation.class.php';
require './libraries/Mailing.lib.php';

if(!empty($_GET['key']))
{
   $invitationKey = Utils::secure($_GET['key']);
   
   // Getting the invitation and checking there is no error.
   $invitation = null;
   try
   {
      $invitation = new Invitation($invitationKey);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => '');
      if(strstr($e->getMessage(), 'does not exist') !== FALSE)
         $tplInput['error'] = 'noInvitation';
      else
         $tplInput['error'] = 'dbError';
      $tplRes = TemplateEngine::parse('view/user/Invitation.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tplRes, 'Erreur lors de l\'inscription (sur invitation)');
   }
   
   // Now checking an account for that e-mail address does not exist already
   try
   {
      $accountExists = User::isEmailUsed($invitation->get('guest_email'));
      if($accountExists)
      {
         $tplInput = array('error' => 'alreadyRegistered');
         $tplRes = TemplateEngine::parse('view/user/Invitation.fail.ctpl', $tplInput);
         WebpageHandler::wrap($tplRes, 'Erreur lors de l\'inscription (sur invitation)');
      }
   }
   catch(Exception $e)
   {
      $tplRes = TemplateEngine::parse('view/user/Invitation.fail.ctpl', array('error' => 'dbError'));
      WebpageHandler::wrap($tplRes, 'Erreur lors de l\'inscription (sur invitation)');
   }
   
   $formTplInput = array('errors' => '',
   'invitationKey' => $invitationKey,
   'pseudo' => '',
   'newPwd' => '');
   
   if(!empty($_POST['sent']))
   {
      $data = array('pseudo' => Utils::secure($_POST['pseudo']), 
                    'newPwd' => Utils::secure($_POST['newPwd']));
      
      // Copying in formTplInput
      $formTplInput['pseudo'] = $data['pseudo'];
      $formTplInput['newPwd'] = $data['newPwd'];
      
      // Testing several possible errors (labels are self-explanatory)
      $errors = '';
      if(strlen($data['pseudo']) == 0 || strlen($data['newPwd']) == 0)
         $errors .= 'emptyFields|';
      if(strlen($data['pseudo']) > 20 || strlen($data['newPwd']) > 20)
         $errors .= 'dataTooBig|';
      if(!preg_match('!^[a-zA-Z0-9_-]{3,20}$!', $data['pseudo']))
         $errors .= 'badPseudo|';
      
      // Also checking that the pseudo is available
      try
      {
         $pseudoExists = User::isPseudoUsed($data['pseudo']);
         if($pseudoExists)
            $errors .= 'pseudoAlreadyUsed|';
      }
      catch(Exception $e)
      {
         $formTplInput['errors'] = 'dbError';
         $tplRes = TemplateEngine::parse('view/user/Invitation.form.ctpl', $formTplInput);
         WebpageHandler::wrap($tplRes, 'Erreur lors de l\'inscription (sur invitation)');
      }
      
      if($errors !== '')
      {
         $formTplInput['errors'] = substr($errors, 0, -1);
         $display = TemplateEngine::parse('view/user/Invitation.form.ctpl', $formTplInput);
         WebpageHandler::wrap($display, 'Inscription sur invitation');
      }
      // At this point, the provided data is considered to be valid
      else
      {
         Database::beginTransaction();
         try
         {
            $user = User::insert($data['pseudo'], $invitation->get('guest_email'), $data['newPwd']);
            $user->confirm();
            $user->updateAdvancedFeatures();
            Database::commit();
            
            // Small e-mail to inform the new user his/her account has been created
            $inputEmail = array('pseudo' => $data['pseudo']);
            $emailContent = TemplateEngine::parse('view/user/Invitation.mail.ctpl', $inputEmail);
            if(!TemplateEngine::hasFailed($emailContent))
               Mailing::send($user->get('email'), 'Bienvenue sur Project AG', $emailContent);
            
            $display = TemplateEngine::parse('view/user/Invitation.success.ctpl');
            WebpageHandler::wrap($display, 'Bienvenue sur Project AG!');
         }
         // Fail while creating new account : check the error provided by SQL
         catch(Exception $e)
         {
            Database::rollback();
            if(strstr($e->getMessage(), 'for key \'PRIMARY\'') != FALSE)
               $formTplInput['errors'] = 'pseudoAlreadyUsed';
            else
               $formTplInput['errors'] = 'dbError';
            $display = TemplateEngine::parse('view/user/Invitation.form.ctpl', $formTplInput);
            WebpageHandler::wrap($display, 'Inscription sur invitation');
         }
      }
   }
   else
   {
      $tplRes = TemplateEngine::parse('view/user/Invitation.form.ctpl', $formTplInput);
      WebpageHandler::wrap($tplRes, 'Inscription sur invitation');
   }
}
else
{
   $tplRes = TemplateEngine::parse('view/user/Invitation.fail.ctpl', array('error' => 'missingKey'));
   WebpageHandler::wrap($tplRes, 'Erreur lors de l\'inscription (sur invitation)');
}
?>
