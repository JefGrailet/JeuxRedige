<?php

/**
* Script to favourite a topic via AJAX. Much like Vote.php, the call of some methods will have
* no effect when repeated in order to preserve consistency.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Topic.class.php';
require '../model/User.class.php';

if(!empty($_POST['id_topic']) && preg_match('#^([0-9]+)$#', $_POST['id_topic']))
{
   $topicID = Utils::secure($_POST['id_topic']);
   
   if(!LoggedUser::isLoggedIn())
   {
      exit();
   }
   
   try
   {
      $topic = new Topic($topicID);
      $userView = $topic->getUserView();
      
      $favorited = Utils::check($userView['favorite']);
      if($favorited)
         $topic->unfavorite();
      else
         $topic->favorite();
      
      $icon = 'icon-general_star';
      if($favorited)
         $icon = 'icon-general_star_empty';
         
      header('Content-Type: text/html; charset=UTF-8');
      echo $icon;
   }
   catch(Exception $e)
   {
      exit();
   }
}

?>
