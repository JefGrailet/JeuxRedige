<?php

/**
* Script allowing one user to log in with an account. After logging in, the user is redirected
* to the home page or the page he was consulting (if that page had a slight edit to do so).
*/

require './libraries/Header.lib.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $tplInput = array('pseudo' => LoggedUser::$data['pseudo'], 'operation' => 'logIn');
   $tplRes = TemplateEngine::parse('view/user/UnnecessaryOperation.ctpl', $tplInput);
   WebpageHandler::wrap($tplRes, 'Cette opération n\'est plus nécessaire');
}

require './model/User.class.php';

$display = '';

$data = array('errors' => '',
'banished' => '',
'pseudo' => '',
'pwd' => '',
'redirection' => '');

if(!empty($_POST['sent']))
{
   $data['pseudo'] = Utils::secure($_POST['pseudo']);
   $data['pwd'] = Utils::secure($_POST['pwd']);
   $data['redirection'] = Utils::secure($_POST['redirection']);
   
   // Error : password and/or pseudo field is/are empty
   if(strlen($data['pseudo']) == 0 OR strlen($data['pwd']) == 0)
   {
      $data['errors'] = 'emptyFields';
      $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
   }
   else
   {
      // Gets the account, checks the password and logs in the user if the password is correct
      try
      {
         $user = new User($data['pseudo']);
         $hashPwd = sha1($user->get('pseudo').$user->get('secret').$data['pwd']);
         $banExpiration = Utils::toTimestamp($user->get('last_ban_expiration'));
         
         if($user->get('confirmation') !== 'DONE')
         {
            $data['errors'] = 'notConfirmed';
            $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
         else if($user->get('password') !== $hashPwd)
         {
            $data['errors'] = 'wrongPwd';
            $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
         else if($banExpiration > Utils::SQLServerTime())
         {
            $data['banished'] = 'yes||'.date('d/m/Y à H:i:s', $banExpiration);
            
            // Adding sentences
            $sentences = $user->listSentences(false);
            $sentencesStr = '';
            for($i = 0; $i < count($sentences); $i++)
            {
               $durationDays = $sentences[$i]['duration'] / (60 * 60 * 24);
               $dateStr = date('d/m/Y à H:i:s', Utils::toTimestamp($sentences[$i]['date']));
               $expiration = Utils::toTimestamp($sentences[$i]['date']) + $sentences[$i]['duration'];
               
               $sentenceTplInput = array('special' => '',
               'nbDays' => $durationDays,
               'date' => $dateStr,
               'banisher' => $sentences[$i]['judge'],
               'timestamp' => Utils::toTimestamp($sentences[$i]['date']),
               'motif' => $sentences[$i]['details']);
               $sentenceTpl = TemplateEngine::parse('view/user/Sentence.item.ctpl', $sentenceTplInput);
               
               if(!TemplateEngine::hasFailed($sentenceTpl))
                  $sentencesStr .= $sentenceTpl;
               else
                  WebpageHandler::wrap($sentenceTpl, 'Une erreur est survenue lors de la lecture des logs');
            }
            $data['banished'] .= '|'.$sentencesStr;
            
            $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
         else
         {
            // Log in is successful; creates $_SESSION and $_COOKIES variables
            $_SESSION['pseudonym'] = $user->get('pseudo');
            $_SESSION['password'] = $user->get('password');
            
            if($_POST['rememberMe'] == 'on')
            {
               $expire = time() + (60 * 60 * 24 * 100);
               setcookie('pseudonym', $user->get('pseudo'), $expire);
               setcookie('password', $user->get('password'), $expire);
            }
            
            // Redirection and success message
            $redirect = './index.php';
            if(strlen($data['redirection']) > 0 && substr($data['redirection'], 0, 7) === 'http://')
               $redirect = str_replace('&amp;', '&', $data['redirection']);
            header('Location:'.$redirect);
            $display = TemplateEngine::parse('view/user/LogIn.success.ctpl', array('redirection' => $redirect));
         }
      }
      // In case of exception, either we cannot reach the DB, either the account does not exist
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'does not exist') != FALSE)
         {
            $data['errors'] = 'nonexistentAccount';
            $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
         else
         {
            $data['errors'] = 'dbError';
            $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
      }
   }
}
// Default form
else
{
   $display = TemplateEngine::parse('view/user/LogIn.form.ctpl');
}

WebpageHandler::wrap($display, 'Connexion');
?>
