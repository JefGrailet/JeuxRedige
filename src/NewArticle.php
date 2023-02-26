<?php

/*
* Script to create a new article. Exclusive to registered users with advanced features enabled. 
* However, this is only valid for the creation of new articles: a user with previously existing 
* articles who lost his/her advanced features can still edit them.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/Buffer.lib.php';
require './model/Article.class.php';
require './model/Tag.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not granted advanced features
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Webpage settings
WebpageHandler::addCSS('article_edition');
WebpageHandler::addJS('article_editor');
WebpageHandler::addJS('keywords');
WebpageHandler::changeContainer('blockSequence');

// Thumbnail creation dialog (re-used; no need for a specific dialog for article thumbnails)
$dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$dialogs = '';
if(!TemplateEngine::hasFailed($dialogTpl))
   $dialogs = $dialogTpl;

$validTypes = array('review', 'preview', 'opinion', 'chronicle', 'guide'); // Valid types of articles
$typeChoices = 'review,Critique|preview,Aperçu|opinion,Humeur|chronicle,Chronique|guide,Guide'; // Types of articles, formatted for <select>

$currentThumbnail = Buffer::getArticleThumbnail();
$currentThumbnailValue = '';
if(strlen($currentThumbnail) == 0)
   $currentThumbnail = './default_article_thumbnail.jpg';
else
   $currentThumbnailValue = './'.substr($currentThumbnail, strlen(PathHandler::HTTP_PATH()));

// Form components
$formComp = array('errors' => '',
'thumbnailPath' => $currentThumbnail,
'title' => '', 
'subtitle' => '', 
'type' => 'review||'.$typeChoices, // Default
'keywords' => '', 
'thumbnail' => '', 
'keywordsList' => '');

// Input only (distinct from above, as items in select fields are not present)
$formInput = array('thumbnail' => $currentThumbnail,
'title' => '', 
'subtitle' => '', 
'type' => '', 
'keywords' => '');

// Form treatment starts here
if(!empty($_POST['sent']))
{
   $inputList = array_keys($formInput);
   $fullyCompleted = true;
   for($i = 0; $i < count($inputList); $i++)
   {
      $formInput[$inputList[$i]] = Utils::secure($_POST[$inputList[$i]]);
      if($formInput[$inputList[$i]] === '' && $inputList[$i] !== 'keywords')
         $fullyCompleted = false;
   }
   
   // Keywords
   $keywordsArr = explode('|', $formInput['keywords']);
   
   if(substr($formInput['thumbnail'], 0, strlen(PathHandler::HTTP_PATH())) === PathHandler::HTTP_PATH())
      $formInput['thumbnail'] = substr($formInput['thumbnail'], strlen(PathHandler::HTTP_PATH()));
   else if(substr($formInput['thumbnail'], 0, 2) === './')
      $formInput['thumbnail'] = substr($formInput['thumbnail'], 2);
   
   // Various errors (empty fields, etc.)
   if(!$fullyCompleted)
      $formComp['errors'] .= 'emptyFields|';
   if(!in_array($formInput['type'], $validTypes))
      $formComp['errors'] .= 'invalidType|';
   if(strlen($formInput['title']) > 100 || strlen($formInput['subtitle']) > 100)
      $formComp['errors'] .= 'tooLongData|';
   if($formInput['thumbnail'] === './default_article_thumbnail.jpg' || !file_exists(PathHandler::WWW_PATH().$formInput['thumbnail']))
      $formComp['errors'] .= 'invalidThumbnail|';
   if(count($keywordsArr) == 1 && strlen($keywordsArr[0]) == 0)
      $formComp['errors'] .= 'noKeywords|';
      
   if(strlen($formComp['errors']) == 0)
   {
      // Finally inserts the article (new error display in case of DB problem)
      $newArticle = null;
      try
      {
         $newArticle = Article::insert($formInput['title'], 
                                       $formInput['subtitle'], 
                                       $formInput['type']);
      }
      catch(Exception $e)
      {
         $formComp['errors'] = 'dbError';
         $formComp['thumbnailPath'] = PathHandler::HTTP_PATH().$formInput['thumbnail'];
         $formComp['thumbnail'] = $formInput['thumbnail'];
         $formComp['title'] = $formInput['title'];
         $formComp['subtitle'] = $formInput['subtitle'];
         $formComp['type'] = $formInput['type'].'||'.$typeChoices;
         $formComp['keywords'] = $formInput['keywords'];
         $formComp['keywordsList'] = Keywords::display($keywordsArr);
      
         $formTpl = TemplateEngine::parse('view/content/NewArticle.form.ctpl', $formComp);
         WebpageHandler::wrap($formTpl, 'Ajouter un jeu dans la base de données', $dialogs);
      }
   
      // Saves the thumbnail
      $fileName = substr(strrchr($formInput['thumbnail'], '/'), 1);
      Buffer::save('upload/articles/'.$newArticle->get('id_article'), $fileName, 'thumbnail');
   
      // Inserts keywords; we move to the next if an exception occurs while mapping the keywords
      for($i = 0; $i < count($keywordsArr) && $i < 10; $i++)
      {
         if(strlen($keywordsArr[$i]) == 0)
            continue;
      
         try
         {
            $tag = new Tag($keywordsArr[$i]);
            $tag->mapToArticle($newArticle->get('id_article'));
         }
         catch(Exception $e)
         {
            continue;
         }
      }
      
      // URL of the edition page of the new article + redirection to it
      $newArticleURL = './EditArticle.php?id_article='.$newArticle->get('id_article');
      header('Location:'.$newArticleURL);
      
      // Success page
      $tplInput = array('title' => $newArticle->get('title'), 'target' => $newArticleURL);
      $successPage = TemplateEngine::parse('view/user/NewArticle.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Créer un nouvel article');
   }
   else
   {
      $formComp['errors'] = substr($formComp['errors'], 0, -1);
      $formComp['thumbnail'] = $formInput['thumbnail'];
      $formComp['title'] = $formInput['title'];
      $formComp['subtitle'] = $formInput['subtitle'];
      $formComp['type'] = $formInput['type'].'||'.$typeChoices;
      $formComp['keywords'] = $formInput['keywords'];
      $formComp['keywordsList'] = Keywords::display($keywordsArr);
      
      $formTpl = TemplateEngine::parse('view/user/NewArticle.form.ctpl', $formComp);
      WebpageHandler::wrap($formTpl, 'Créer un nouvel article', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/user/NewArticle.form.ctpl', $formComp);
   WebpageHandler::wrap($formTpl, 'Créer un nouvel article', $dialogs);
}

?>
