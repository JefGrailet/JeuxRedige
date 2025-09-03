<?php

/*
* Script to delete an article.
*
* TODO: send an alert to the author if the deletor is another user.
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/PublishArticle.fail.ctpl', $tplInput); // Same template can be re-used
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   try
   {
      $article = new Article($articleID);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingArticle';
      $tpl = TemplateEngine::parse('view/user/PublishArticle.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Article introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/user/PublishArticle.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cet article n\'est pas le vôtre');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('article_edition');
   WebpageHandler::addJS('article_editor');
   WebpageHandler::addJS('keywords');
   WebpageHandler::changeContainer('blockSequence');
   
   $formTplInput = array('errors' => '', 
   'articleID' => $article->get('id_article'), 
   'fullArticleTitle' => $article->get('title').' - '.$article->get('subtitle'), 
   'warning' => $article->isPublished() ? 'published' : 'nonpublished');
   
   if(!empty($_POST['delete']))
   {
      try
      {
         $article->delete();
      }
      catch(Exception $e)
      {
         $formTplInput['errors'] = 'dbError';
         $tpl = TemplateEngine::parse('view/user/DeleteArticle.form.ctpl', $formTplInput);
         WebpageHandler::wrap($tpl, 'Supprimer un article');
      }
      
      header('Location:./MyArticles.php');
      
      $tplInput = array('title' => $article->get('title').' - '.$article->get('subtitle'));
      $successPage = TemplateEngine::parse('view/user/DeleteArticle.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'L\'article "'.$article->get('title').'" a été supprimé');
   }
   else
   {
      $tpl = TemplateEngine::parse('view/user/DeleteArticle.form.ctpl', $formTplInput);
      WebpageHandler::wrap($tpl, 'Supprimer un article');
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/user/PublishArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>
