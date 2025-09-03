<?php

/**
* This scripts performs the deletion of a commentable (any kind). The commentable can be deleted 
* by its author or by an authorized user if said content is problematic.
*
* TODO: send an alert to the author if the deletor is another user.
*/

require './libraries/Header.lib.php';
require './model/Commentable.class.php';

// Errors where the user is either not logged in, either not allowed to lock/unlock
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

$commentable = null;
$commentableTitle = '';
$commentableURL = '';
$commentableOut = ''; // Page towards which user is redirected after deletion
$relatedContent = ''; // For the "related_content" block in "DeleteContent.form.ctpl"
if(!empty($_GET['id_content']) && preg_match('#^([0-9]+)$#', $_GET['id_content']))
{
   $getID = intval(Utils::secure($_GET['id_content']));
   
   // Gets the content and checks what type it is
   try
   {
      $what = Commentable::whatKind($getID);
      if($what === 'Trivia')
      {
         require './model/Trivia.class.php';
         $commentable = new Trivia($getID);
         
         $commentableTitle = 'Anecdote pour le jeu '.$commentable->get('game').': '.$commentable->get('title');
         $commentableURL = PathHandler::triviaURL($commentable->getAll());
         $commentableOut = PathHandler::gameURL(array('tag' => $commentable->get('game')));
         if($commentable->get('id_topic') != NULL)
            $relatedContent = 'topic';
      }
      else if($what === 'GamesList')
      {
         require './model/GamesList.class.php';
         $commentable = new GamesList($getID);
         
         $commentableTitle = 'Liste: '.$commentable->get('title');
         $commentableURL = PathHandler::listURL($commentable->getAll());
         $commentableOut = './MyLists.php';
         if($commentable->get('id_topic') != NULL)
            $relatedContent = 'topic';
      }
      else if($what === 'Missing')
      {
         $errorTplInput = array('error' => 'missingContent');
         $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $errorTplInput);
         WebpageHandler::wrap($tpl, 'Impossible de supprimer ce contenu');
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';

      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Contenu introuvable');
   }
   
   if(!$commentable->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Vous ne pouvez pas supprimer ce contenu');
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Anecdote introuvable');
}

$tplInput = array('errors' => '', 
'contentID' => $commentable->get('id_commentable'), 
'contentURL' => $commentableURL, 
'contentTitle' => $commentableTitle, 
'related_content' => $relatedContent);

// Asks the user to confirm the deletion
if(!empty($_POST['delete']))
{
   $successTplInput = array('target' => $commentableOut);
   
   try
   {
      $commentable->delete();
   }
   catch(Exception $e)
   {
      $tplInput['errors'] = 'dbError';
      $tpl = TemplateEngine::parse('view/content/DeleteContent.form.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Supprimer un contenu');
   }
   
   // Redirects and displays some success page if redirection fails
   header('Location:'.$successTplInput['target']);
   $tpl = TemplateEngine::parse('view/content/DeleteContent.success.ctpl', $successTplInput);
   WebpageHandler::wrap($tpl, 'Contenu supprimé avec succès');
}
// Small form otherwise
else
{
   $tpl = TemplateEngine::parse('view/content/DeleteContent.form.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Supprimer un contenu');
}

?>
