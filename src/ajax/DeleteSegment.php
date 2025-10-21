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
      echo 'DB error';
      setcookie("flash_message", "page_deleted", time() + 1, "/");
   }

   if(!$article->isPublished() && $article->isMine() && $segment->get('id_article') == $article->get('id_article'))
   {
      try
      {
         $segment->delete();
      }
      catch(Exception $e)
      {
         echo 'DB error';
         setcookie("flash_message", "page_deleted", time() + 1, "/");
      }

      setcookie("flash_message", "page_deleted", time() + 1, "/");
   }
   else
   {
      echo 'Wrong segment';
      setcookie("flash_message", "page_deleted", time() + 1, "/");
   }

   $webRoot = substr(PathHandler::HTTP_PATH(), 0, -1);
   header("Location:{$webRoot}/EditArticle.php?id_article={$articleID}");
}
