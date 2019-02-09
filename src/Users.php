<?php

/*
* Script to display the list of users registered in the DB, in alphabetical order. This list 
* should be accessible only to authorized people. It should also be possible to select a user 
* with a small system relying on AJAX to look-up for a pseudonym using a small input string (just 
* like with keywords), but none of the code regarding this feature is handled by this script.
*/

require './libraries/Header.lib.php';
require './model/User.class.php';

WebpageHandler::$miscParams['message_size'] = 'default'; // Forces to use default avatar size
WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to lock/unlock
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$data['can_edit_users']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addJS('user_input');
WebpageHandler::noContainer();

// Gets the amount of users and the users in the current page.
$nbUsers = 0;
try
{
   $nbUsers = User::countUsers();

   if($nbUsers == 0)
   {
      $errorTplInput = array('error' => 'noUser');
      $tpl = TemplateEngine::parse('view/user/Users.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des utilisateurs');
   }
   
   $currentPage = 1;
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   $nbPages = ceil($nbUsers / $perPage);
   $firstUser = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstUser = ($getPage - 1) * $perPage;
      }
   }
   
   $users = User::getUsers($firstUser, $perPage);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/user/Users.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Liste des utilisateurs');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of avatars of each user (with their pseudonym, of course). */

// Avatars + pseudo
$avatars = '';
$fullInput = array();
for($i = 0; $i < count($users); $i++)
{
   $blockInput = array('avatar' => PathHandler::getAvatar($users[$i]['pseudo']),
   'formattedPseudo' => utf8_encode($users[$i]['pseudo']),
   'pseudo' => $users[$i]['pseudo']);
   
   array_push($fullInput, $blockInput);
}

if(count($fullInput) > 0)
{
   $userBlocks = TemplateEngine::parseMultiple('view/user/User.item.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($userBlocks))
   {
      $errorTplInput = array('error' => 'wrongTemplating');
      $tpl = TemplateEngine::parse('view/user/Users.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des utilisateurs');
   }
   
   $first = true;
   for($i = 0; $i < count($userBlocks); $i++)
   {
      if($first)
         $first = false;
      else
         $avatars .= ' ';
      $avatars .= $userBlocks[$i];
   }
}

// Final HTML code (with page configuration)
$pageConfig = $perPage.'|'.$nbUsers.'|'.$currentPage;
$pageConfig .= '|./Users.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'avatars' => $avatars);
$content = TemplateEngine::parse('view/user/Users.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Liste des utilisateurs');

?>
