<?php

/**
* Script to reset a password of one's account without being logged. It consists of a 2-step
* procedure, one of both steps consisting in sending an e-mail to the user thanks to the
* e-mail address of his/her account to give an authentication code to reset the password.
*/

require './libraries/Header.lib.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'passwordReset');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './model/User.class.php';
require './libraries/Mailing.lib.php';

$display = '';

/* Special code to cancel the procedure when at step 2 if explicitely asked by the user (this is
* signaled thanks to a unique $_GET variable). */

if(!empty($_GET['restart']) && $_GET['restart'] === 'ok')
{
   session_destroy();
   session_start();
}

// If the verification code has been generated
if(!empty($_SESSION['check1']) && !empty($_SESSION['check2']))
{
   $pseudo = Utils::secure($_SESSION['check2']);
   
   // Gets the account information. Cancels the procedure if an exception occurs.
   try
   {
      $user = new User($pseudo);
   }
   catch(Exception $e)
   {
      session_destroy();
      $tplInput = array('pseudo' => $pseudo, 'error' => '');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistentAccount';
      else
         $tplInput['error'] = 'dbError';
      $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
   }

   if(!empty($_POST['sent']))
   {
      $data = array('code' => Utils::secure($_POST['code']),
                    'newPwd' => Utils::secure($_POST['newPwd']));
      $recomputedHash = sha1($pseudo . $user->get('secret') . $data['code']);
      
      /*
       * Remark: the "recomputedHash" isn't the actual final hash. It's only an intermediate step 
       * before verifying the hash with password_verify().
       */
      
      // Deals with errors (empty fields, mismatching passwords, wrong code)
      $errors = '';
      if(strlen($data['code']) == 0 || strlen($data['newPwd']) == 0)
         $errors .= 'emptyFields|';
      if(!password_verify($recomputedHash, $_SESSION['check1'])) // bcrypt verification
         $errors .= 'wrongCode|';
         
      if(strlen($errors) > 0)
      {
         $data['errors'] = substr($errors, 0, -1);
         $display = TemplateEngine::parse('view/user/PasswordReset.form.ctpl', $data);
      }
      else
      {
         // Edits password and destroys $_SESSION used so far.
         try
         {
            $user->setPassword($data['newPwd']);
            $display = $display = TemplateEngine::parse('view/user/PasswordReset.success.ctpl');
            session_destroy();
         }
         catch(Exception $e)
         {
            $data['errors'] = 'dbError';
            $display = TemplateEngine::parse('view/user/PasswordReset.form.ctpl', $data);
         }
      }  
   }
   else
   {
      $display = TemplateEngine::parse('view/user/PasswordReset.form.ctpl');
   }
}
// Step 1 : sending the verification code to the user.
else
{
   if(!empty($_POST['sent']))
   {
      $pseudo = Utils::secure($_POST['pseudo']);
   
      if(strlen($pseudo) == 0)
      {
         $tplInput = array('pseudo' => $pseudo, 'error' => 'emptyField');
         $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
      }
      else
      {
         // Gets the account and generates the verification code (+ deals with errors)
         try
         {
            $user = new User($pseudo);
            
            // Password reset is unavailable if the account is not confirmed yet
            if($user->get('confirmation') !== 'DONE')
            {
               $tplInput = array('pseudo' => $pseudo, 'error' => 'notConfirmed');
               $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
            }
            // Password reset attempt is blocked if there were at 3 attempts recently (~24h)
            else if($user->areThereRecentPwdReset() && $user->get('pwd_reset_attempts') >= 3)
            {
               $tplInput = array('pseudo' => $pseudo, 'error' => 'tooManyAttempts');
               $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
            }
            else
            {
               $user->incPwdResetAttempts();
               $checkCode = substr(md5(uniqid(rand(), true)), 0, 20);
               $emailInput = array('pseudo' => $pseudo, 'code' => $checkCode);
               $emailContent = TemplateEngine::parse('view/user/CodeAuthentication.mail.ctpl', $emailInput);
               $emailTitle = 'Réinitialisation de votre mot de passe';
               if(!TemplateEngine::hasFailed($emailContent) && Mailing::send($user->get('email'), $emailTitle, $emailContent))
               {
                  /* 
                   * To keep the code in some manner while preventing attacks, it is hashed in the
                   * same manner as passwords and is stored in a $_SESSION. 
                   */
                  
                  $_SESSION['check1'] = password_hash(sha1($pseudo . $user->get('secret') . $checkCode), 
                                                      PASSWORD_DEFAULT, 
                                                      ['cost' => 12]);
                  $_SESSION['check2'] = $pseudo;
                  $display = TemplateEngine::parse('view/user/PasswordReset.form.ctpl');
               }
               else
               {
                  $tplInput = array('pseudo' => $pseudo, 'error' => 'emailFail');
                  $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
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
            $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl', $tplInput);
         }
      }
   }
   else
   {
      $display = TemplateEngine::parse('view/user/CodeAuthentication.form.ctpl');
   }
}

WebpageHandler::wrap($display, 'Réinitialiser mon mot de passe');
?>
