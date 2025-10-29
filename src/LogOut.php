<?php

/**
* Script to log out a logged user.
*/

require './libraries/Header.lib.php';
require_once './libraries/core/Twig.config.php';

$display = '';

// Dealing with the redirection variable
$redirection = './index.php';
if(!empty($_GET['redirection']))
{
   $urlRedirection = Utils::secure($_GET['redirection']);
   if(substr($urlRedirection, 0, 8) == "https://")
      $redirection = str_replace('amp;', '&', $urlRedirection);
}

// If user was not logged, a special message will be displayed.
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('type' => 'notLogged', 'redirection' => $redirection);
   $display = TemplateEngine::parse('view/user/LogOut.ctpl', $tplInput);
}
else
{
   $userPseudo = $twig->getGlobals()["userInfos"]["pseudo"];
   // Destroys all connection variables
   if(!empty($_COOKIE['pseudonym']) && !empty($_COOKIE['password']))
   {
      $expire = Utils::SQLServerTime() - 10000;
      setcookie('pseudonym', '', $expire);
      setcookie('password', '', $expire);
   }

   session_destroy();
   if (isset($userPseudo)) {
      setcookie("flash_message_extra_data", json_encode(["pseudo" => $userPseudo]), time() + 1);
   }
}

setcookie("flash_message", "user_disconnected", time() + 1);
header('Location:' . $redirection);
