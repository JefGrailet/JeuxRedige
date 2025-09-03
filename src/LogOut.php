<?php

/**
* Script to log out a logged user.
*/

require './libraries/Header.lib.php';

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
   // Destroys all connection variables
   if(!empty($_COOKIE['pseudonym']) && !empty($_COOKIE['password']))
   {
      $expire = Utils::SQLServerTime() - 10000;
      setcookie('pseudonym', '', $expire);
      setcookie('password', '', $expire);
   }
   session_destroy();
   
   $tplInput = array('type' => 'logged', 'redirection' => $redirection);
   $display = TemplateEngine::parse('view/user/LogOut.ctpl', $tplInput);
}

header('Location:'.$redirection);
WebpageHandler::wrap($display, 'Déconnexion');
?>
