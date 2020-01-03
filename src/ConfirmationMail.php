<?php

/**
* This script allows a non-logged user to re-send a confirmation e-mail (for a new account or
* for a new e-mail address) by giving his/her pseudonym, if he/she did not receive the initial
* e-mail for some reason.
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

$display = '';

if(!empty($_POST['sent']))
{
   $pseudo = Utils::secure($_POST['pseudo']);
   
   if(strlen($pseudo) == 0)
   {
      $tplInput = array('pseudo' => '', 'error' => 'emptyField');
      $display = TemplateEngine::parse('view/user/ConfirmationMail.form.ctpl', $tplInput);
   }
   else
   {
      // Gets the account, checks confirmation key length and generates a new e-mail (+ errors)
      try
      {
         $user = new User($pseudo);
         
         if($user->get('confirmation') == 'DONE')
         {
            $tplInput = array('pseudo' => $pseudo, 'error' => 'alreadyConfirmed');
            $display = TemplateEngine::parse('view/user/ConfirmationMail.form.ctpl', $tplInput);
         }
         else
         {
            // E-mail generation
            $confirmKey = $user->get('confirmation');
            $confirmLink = PathHandler::HTTP_PATH().'Confirmation.php?pseudo='.$pseudo.'&key='.$confirmKey;
            $emailInput = array('pseudo' => $pseudo, 'confirmLink' => $confirmLink);
            $emailContent = '';
            $emailTitle = '';
            $destAddr = $user->get('email');
            if(strlen($confirmKey) == 15)
            {
               $emailContent = TemplateEngine::parse('view/user/Registration.mail.ctpl', $emailInput);
               $emailTitle = 'Inscription sur Project AG';
            }
            elseif(strlen($confirmKey) == 10)
            {
               $emailContent = TemplateEngine::parse('view/user/EmailEdition.mail.ctpl', $emailInput);
               $emailTitle = 'Modification de votre adresse e-mail';
               $exploded = explode('|', $user->get('email'));
               $destAddr = $exploded[1];
            }
            
            // Sends e-mail; a boolean is set to true upon success
            $mailSuccess = false;
            if(!TemplateEngine::hasFailed($emailContent))
            {
               if(Mailing::send($destAddr, $emailTitle, $emailContent))
                  $mailSuccess = true;
            }
            
            // Output page
            if($mailSuccess)
            {
               $display = TemplateEngine::parse('view/user/ConfirmationMail.success.ctpl');
            }
            else
            {
               $tplInput = array('pseudo' => $pseudo, 'error' => 'emailFail');
               $display = TemplateEngine::parse('view/user/ConfirmationMail.form.ctpl', $tplInput);
            }
         }
      }
      catch(Exception $e)
      {
         $tplInput = array('pseudo' => $pseudo, 'error' => '');
         if(strstr($e->getMessage(), 'does not exist') != FALSE)
            $tplInput['error'] = 'nonexistentAccount';
         else
            $tplInput['error'] = 'dbError';
         $display = TemplateEngine::parse('view/user/ConfirmationMail.form.ctpl', $tplInput);
      }
   }
}
// Default form
else
{
   $display = TemplateEngine::parse('view/user/ConfirmationMail.form.ctpl');
}

WebpageHandler::wrap($display, 'Ré-envoi de l\'e-mail de confirmation');
?>
