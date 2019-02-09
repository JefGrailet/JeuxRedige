<?php

/**
* Script to censor an archived post, if it featured inappropriate content (e.g. borderline videos 
* or images).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/PostHistory.class.php';

if(!empty($_POST['id_post']) && preg_match('#^([0-9]+)$#', $_POST['id_post']) &&
   !empty($_POST['version_num']) && preg_match('#^([0-9]+)$#', $_POST['version_num']))
{
   $idPost = Utils::secure($_POST['id_post']);
   $version = Utils::secure($_POST['version_num']);
   
   try
   {
      $post = new PostHistory(array($idPost, $version));
      $result = $post->censor();
      
      header('Content-Type: text/html; charset=UTF-8');
      echo $result;
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo -2;
   }
}

?>
