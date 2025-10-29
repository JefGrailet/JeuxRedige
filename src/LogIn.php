<?php

/**
* Script allowing one user to log in with an account. After logging in, the user is redirected
* to the home page or the page he was consulting (if that page had a slight edit to do so).
*/

require './libraries/Header.lib.php';
require_once './libraries/core/Twig.config.php';

// Script is useless if user is already connected. A special message is displayed.
if(LoggedUser::isLoggedIn())
{
   $userPseudo = $twig->getGlobals()["userInfos"]["pseudo"];
   setcookie("flash_message_extra_data", json_encode(["pseudo" => $userPseudo]), time() + 1);
   setcookie("flash_message", "user_already_logged", time() + 1);
   header('Location: ./index.php');
}

require './model/User.class.php';

$data = array('banished' => '',
'pseudo' => '',
'pwd' => '',
'redirection' => '');

$formErrorMessagesTriggered = [];

if(!empty($_POST))
{
   $data['pseudo'] = Utils::secure($_POST['pseudo']);
   $data['pwd'] = Utils::secure($_POST['pwd']);
   $data['redirection'] = Utils::secure($_POST['redirection']);

   // Error : password and/or pseudo field is/are empty
   if(strlen($data['pseudo']) == 0 OR strlen($data['pwd']) == 0)
   {
      array_push($formErrorMessagesTriggered, "Vous devez remplir tous les champs");
   }
   else
   {
      // Gets the account, checks the password and logs in the user if the password is correct
      try
      {
         $user = new User($data['pseudo']);
         $hashPwd = sha1($user->get('pseudo').$user->get('secret').$data['pwd']);
         $banExpiration = Utils::toTimestamp($user->get('last_ban_expiration'));

         /*
          * Remark: the "hashPwd" isn't the hash actually (currently) stored in the DB. It's an
          * intermediate hash that used to be stored in the DB and which is further hashed with
          * bcrypt in order to ensure old hashes could be still used without asking users to give
          * again their passwords upon bringing the bcrypt solution. This solution has also a nice
          * twist: as sha1() always returns 40 characters long hashes and bcrypt truncates 60+
          * characters passwords, this allows users to potentially use any length of password.
          */

         if($user->get('confirmation') !== 'DONE')
         {
            array_push($formErrorMessagesTriggered, "Ce compte n'a pas encore été confirmé. Confirmez-le à l'aide du lien qui vous a été envoyé par e-mail");
         }
         else if(!password_verify($hashPwd, $user->get('password'))) // bcrypt verification
         {
            array_push($formErrorMessagesTriggered, "Les identifiants sont incorrects");
         }
         else if($banExpiration > Utils::SQLServerTime())
         {
            $banExpirationDate = date('d/m/Y à H:i:s', $banExpiration);
            array_push($formErrorMessagesTriggered, "Ce compte a été banni jusqu'au {$banExpirationDate}. La/les sanctions active(s) sont listées ci-contre");

            // Adding sentences
            // $sentences = $user->listSentences(false);
            // $sentencesStr = '';
            // for($i = 0; $i < count($sentences); $i++)
            // {
            //    $durationDays = $sentences[$i]['duration'] / (60 * 60 * 24);
            //    $dateStr = date('d/m/Y à H:i:s', Utils::toTimestamp($sentences[$i]['date']));
            //    $expiration = Utils::toTimestamp($sentences[$i]['date']) + $sentences[$i]['duration'];

            //    $sentenceTplInput = array('special' => '',
            //    'nbDays' => $durationDays,
            //    'date' => $dateStr,
            //    'banisher' => $sentences[$i]['judge'],
            //    'timestamp' => Utils::toTimestamp($sentences[$i]['date']),
            //    'motif' => $sentences[$i]['details']);
            //    $sentenceTpl = TemplateEngine::parse('view/user/Sentence.item.ctpl', $sentenceTplInput);

            //    if(!TemplateEngine::hasFailed($sentenceTpl))
            //       $sentencesStr .= $sentenceTpl;
            //    else
            //       WebpageHandler::wrap($sentenceTpl, 'Une erreur est survenue lors de la lecture des logs');
            // }
            // $data['banished'] .= '|'.$sentencesStr;

            // $display = TemplateEngine::parse('view/user/LogIn.form.ctpl', $data);
         }
         else
         {
            // Log in is successful; creates $_SESSION and $_COOKIES variables
            $_SESSION['pseudonym'] = $user->get('pseudo');
            $_SESSION['password'] = $user->get('password');

            /*
             * On the $_SESSION and $_COOKIE containing the passwords: the stored password is
             * given, because the result of bcrypt is random (salt is randomized) and re-creating
             * a bcrypt hash takes time on top of that. Using the stored bcrypt hash allows to:
             * -verify the user's logged with a string comparison (rather than password_verify()),
             * -avoid storing the weaker hash (sha1() hash) in the cookies of the user to use
             *  password_verify(), because if this hash is stolen, user's password could still be
             *  cracked and we want to avoid this.
             */

            if(array_key_exists('rememberMe', $_POST) && $_POST['rememberMe'] == 'on')
            {
               $expire = time() + (60 * 60 * 24 * 100);
               setcookie('pseudonym', $user->get('pseudo'), $expire);
               setcookie('password', $user->get('password'), $expire);
            }

            // Redirection and success message
            $redirect = './index.php';
            setcookie("flash_message", "user_logged", time() + 1);
            if(strlen($data['redirection']) > 0 && (substr($data['redirection'], 0, 7) === 'http://' || substr($data['redirection'], 0, 8) === 'https://'))
               $redirect = str_replace('&amp;', '&', $data['redirection']);
            header('Location:'.$redirect);
         }
      }
      // In case of exception, either we cannot reach the DB, either the account does not exist
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'does not exist') != FALSE)
         {
            array_push($formErrorMessagesTriggered, "Ce compte n'existe pas");
         }
         else
         {
            array_push($formErrorMessagesTriggered, "Une erreur inconnue s'est produite lors de la recherche de vos données. Réessayez plus tard");
         }
      }
   }
}

echo $twig->render("log-in.html.twig", [
   "page_title" => "Connexion",
   "form_error_messages_triggered" => $formErrorMessagesTriggered,
   "list_sentences" => [],
   "logInRedirection" => $twig->getGlobals()["webRoot"],
   "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Connexion - JeuxRédige",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);


