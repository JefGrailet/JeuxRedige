<?php

/**
* Small script called through AJAX to delete a segment from an article. This can only be carried 
* out if the current user is logged, author of the article and the article itself is not published 
* yet.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Article.class.php';
require '../model/Segment.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

if(!empty($_POST['id_article']) && preg_match('#^([0-9]+)$#', $_POST['id_article']) && 
   !empty($_POST['id_segment']) && preg_match('#^([0-9]+)$#', $_POST['id_segment']))
{
   $articleID = intval(Utils::secure($_POST['id_article']));
   $segmentID = intval(Utils::secure($_POST['id_segment']));
   
   $article = null;
   $segment = null;
   try
   {
      $article = new Article($articleID);
      $segment = new Segment($segmentID);
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
   }
   
   if(!$article->isPublished() && $article->isMine() && $segment->get('id_article') == $article->get('id_article'))
   {
      try
      {
         $segment->delete();
      }
      catch(Exception $e)
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
      
      header('Content-Type: text/html; charset=UTF-8');
      echo 'OK';
   }
   else
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Wrong segment';
   }
}

?>