<?php

/**
* This script marks a topic as fully seen by the current user. The ID of the topic is given by 
* $_POST.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Topic.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_POST['id_topic']))
{
   $topicToCheck = Utils::secure($_POST['id_topic']);
   
   $totalPosts = 0;
   try
   {
      $topic = new Topic($topicToCheck);
      $topic->setAllSeen();
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo $e->getMessage();
      exit();
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo 'OK '.$totalPosts;
}

?>
