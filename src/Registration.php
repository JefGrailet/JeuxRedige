<?php

/**
* Script to register a new account.
*/

require './libraries/Header.lib.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'registration');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './libraries/Mailing.lib.php';
require './model/User.class.php';

$display = '';

try
{
   User::cleanAccountRequests();
}
catch(Exception $e) {}

if(!empty($_POST['sent']))
{
   $data = array('pseudo' => Utils::secure($_POST['pseudo']),
                 'email' => Utils::secure($_POST['email']),
                 'pwd' => Utils::secure($_POST['newPwd']), 
                 'presentation' => Utils::secure($_POST['presentation']));
   
   // Checks that all input fields have been filled
   $cond = true;
   $keys = array_keys($data);
   for($i = 0; $i < 3; $i++)
      if(strlen($data[$keys[$i]]) == 0)
         $cond = false;
   
   // Testing several possible errors (labels are self-explanatory)
   $errors = '';
   if(!$cond)
      $errors .= 'emptyFields|';
   if(strlen($data['pseudo']) > 20 || strlen($data['email']) > 60 || strlen($data['pwd']) > 20)
      $errors .= 'dataTooBig|';
   if(!preg_match('!^[a-zA-Z0-9_-]{3,20}$!', $data['pseudo']))
      $errors .= 'badPseudo|';
   
   try
   {
      if(User::isPseudoUsed($data['pseudo']))
         $errors .= 'pseudoAlreadyUsed|';
      if(User::isEmailUsed($data['email']))
         $errors .= 'emailAlreadyUsed|';
   }
   catch(Exception $e)
   {
      $errors .= 'dbError|';
   }
   
   // Displaying the errors (last | in $errors is removed)
   if($errors !== '')
   {
      $data['errors'] = substr($errors, 0, -1);
      $display = TemplateEngine::parse('view/user/Registration.form.ctpl', $data);
   }
   // At this point, the provided data is considered to be valid
   else
   {
      $withPresentation = false;
      if(strlen($data['presentation']) > 0)
      {
         $withPresentation = true;
         Database::beginTransaction();
      }
      
      try
      {
         $user = User::insert($data['pseudo'], $data['email'], $data['pwd']);
         $confirmKey = $user->get('confirmation');
         $confirmLink = PathHandler::HTTP_PATH.'Confirmation.php?pseudo='.$data['pseudo'].'&key='.$confirmKey;
         $emailInput = array('pseudo' => $data['pseudo'], 'confirmLink' => $confirmLink);
         $emailContent = TemplateEngine::parse('view/user/Registration.mail.ctpl', $emailInput);
         $tplInput = array('mail' => 'mailFail');
         if(!TemplateEngine::hasFailed($emailContent))
         {
            if(Mailing::send($data['email'], 'Inscription sur Project AG', $emailContent))
               $tplInput['mail'] = 'mailSuccess';
         }
         $display = TemplateEngine::parse('view/user/Registration.success.ctpl', $tplInput);
         
         if($withPresentation)
         {
            $user->registerPresentation($data['presentation']);
            Database::commit();
         }
      }
      // Fail while creating new account : check the error provided by SQL
      catch(Exception $e)
      {
         if($withPresentation)
            Database::rollback();
         
         if(strstr($e->getMessage(), 'for key \'PRIMARY\'') != FALSE)
            $data['errors'] = 'pseudoAlreadyUsed';
         else if(strstr($e->getMessage(), 'for key \'email\'') != FALSE)
            $data['errors'] = 'emailAlreadyUsed';
         else
            $data['errors'] = 'dbError';
         $display = TemplateEngine::parse('view/user/Registration.form.ctpl', $data);
      }
   }
}
else
{
   $display = TemplateEngine::parse('view/user/Registration.form.ctpl');
}

WebpageHandler::wrap($display, 'Inscription');
?>
