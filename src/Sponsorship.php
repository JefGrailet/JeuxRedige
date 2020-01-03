<?php

/**
* Script to invite a third party to create an account on the site.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// Script is useless if user is already connected. A special message is displayed.
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('reason' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/Sponsorship.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Opération non autorisée');
}
// Sames goes if the user has no access to the advanced features yet.
else if(!Utils::check(LoggedUser::$data['can_invite']))
{
   $tplInput = array('reason' => 'notPermitted');
   $tpl = TemplateEngine::parse('view/user/Sponsorship.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Opération non autorisée');
}

require './libraries/Mailing.lib.php';
require './model/User.class.php';
require './model/Invitation.class.php';

$display = '';

$formTplInput = array('success' => '',
'error' => '', 
'email' => '');

if(!empty($_POST['sent']))
{
   $inputEmail = Utils::secure($_POST['email']);
   
   // Basic errors
   if(strlen($inputEmail) < 10)
   {
      $formTplInput['error'] = 'emptyField';
      $formTplInput['email'] = $inputEmail;
      $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
   }
   else if(strlen($inputEmail) > 60)
   {
      $formTplInput['error'] = 'tooLong';
      $formTplInput['email'] = $inputEmail;
      $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
   }
   // At this point, the provided e-mail is considered to be OK
   else
   {
      try
      {
         // Checks e-mail availability
         if(User::isEmailUsed($inputEmail))
         {
            $formTplInput['error'] = 'alreadyUsed';
            $formTplInput['email'] = $inputEmail;
            $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
         }
         else
         {
            /*
             * Checks if this is a new invitation, a re-send or if the address has been invited 
             * by a third party.
             */
            
            $sponsor = Invitation::hasBeenInvited($inputEmail);
            
            if(strlen($sponsor) == 0)
            {
               $invitation = Invitation::insert($inputEmail);
               $invitationKey = $invitation->get('invitation_key');
               $invitationLink = PathHandler::HTTP_PATH().'Invitation.php?key='.$invitationKey;
               $mailInput = array('pseudo' => LoggedUser::$data['pseudo'], 'invitationLink' => $invitationLink);
               $mailContent = TemplateEngine::parse('view/user/Sponsorship.mail.ctpl', $mailInput);
               
               // Success message display
               $formTplInput['success'] = 'mailFail';
               if(!TemplateEngine::hasFailed($mailContent))
               {
                  if(Mailing::send($inputEmail, 'Vous êtes invité sur Project AG', $mailContent))
                  {
                     $formTplInput['success'] = 'newInvitation';
                     $invitation->updateLastEmailDate();
                  }
               }
               $formTplInput['success'] .= '||'.$inputEmail;
               $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
            }
            else if($sponsor === LoggedUser::$data['pseudo'])
            {
               $invitation = Invitation::getByEmail($inputEmail);
               
               // Checks the last attempt at sending an invitation was more than an hour ago
               $delay = Utils::SQLServerTime() - Utils::toTimestamp($invitation->get('last_email'));
               
               if($delay < 3600)
               {
                  $formTplInput['error'] = 'recentAttempt';
                  $formTplInput['email'] = $inputEmail;
                  $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
               }
               else
               {
                  $invitationKey = $invitation->get('invitation_key');
                  $invitationLink = PathHandler::HTTP_PATH().'Invitation.php?key='.$invitationKey;
                  $mailInput = array('pseudo' => LoggedUser::$data['pseudo'], 'invitationLink' => $invitationLink);
                  $mailContent = TemplateEngine::parse('view/user/Sponsorship.mail.ctpl', $mailInput);
                  
                  // Success message display
                  $formTplInput['success'] = 'mailFail';
                  if(!TemplateEngine::hasFailed($mailContent))
                  {
                     if(Mailing::send($inputEmail, 'Vous êtes invité sur Project AG', $mailContent))
                     {
                        $formTplInput['success'] = 'newEmail';
                        $invitation->updateLastEmailDate();
                     }
                  }
                  $formTplInput['success'] .= '||'.$inputEmail;
                  $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
               }
            }
            else
            {
               $formTplInput['error'] = 'alreadyInvited';
               $formTplInput['email'] = $inputEmail;
               $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
            }
         }
      }
      // Fail while creating new account : check the error provided by SQL
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'for key \'guest_email\'') != FALSE)
            $formTplInput['error'] = 'alreadyInvited';
         else
            $formTplInput['error'] = 'dbError';
         $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl', $formTplInput);
      }
   }
}
else
{
   $display = TemplateEngine::parse('view/user/Sponsorship.form.ctpl');
}

WebpageHandler::wrap($display, 'Inviter un ami');
?>
