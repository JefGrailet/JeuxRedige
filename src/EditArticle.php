<?php

/*
* Script to edit an article, or rather its main structure. As long as the current user is the 
* author of the article, (s)he can edit it, no matter what are its current permissions.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/Buffer.lib.php';
require './model/Article.class.php';
require './model/Tag.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Thumbnail creation dialog
$dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$dialogs = '';
if(!TemplateEngine::hasFailed($dialogTpl))
   $dialogs = $dialogTpl;

$validTypes = array('review', 'preview', 'opinion'); // Valid types of articles
$typeChoices = 'review,Critique|preview,Aperçu|opinion,Humeur|chronicle,Chronique'; // Types of articles, formatted for <select>

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   $keywords = null;
   $segments = null;
   try
   {
      $article = new Article($articleID);
      $article->loadRelatedData();
      $keywords = $article->getKeywordsSimple();
      $segments = $article->getBufferedSegments();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingArticle';
      $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Article introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cet article n\'est pas le vôtre');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('article_edition');
   WebpageHandler::addJS('article_editor');
   WebpageHandler::addJS('keywords');
   WebpageHandler::changeContainer('blockSequence');
   
   // Edition form components (with current values)
   $formComp = array('success' => '', 
   'errors' => '',
   'ID' => $article->get('id_article'),
   'thumbnail' => '',
   'title' => $article->get('title'), 
   'subtitle' => $article->get('subtitle'), 
   'type' => $article->get('type').'||'.$typeChoices,
   'keywords' => '',
   'keywordsList' => '');
   
   // Thumbnail
   $currentThumbnail = Buffer::getArticleThumbnail();
   if(file_exists(PathHandler::WWW_PATH().'upload/articles/'.$articleID.'/thumbnail.jpg'))
      $formComp['thumbnail'] = './upload/articles/'.$articleID.'/thumbnail.jpg';
   else if(strlen($currentThumbnail) > 0)
      $formComp['thumbnail'] = './'.substr($currentThumbnail, strlen(PathHandler::HTTP_PATH()));
   else
      $formComp['thumbnail'] = './default_article_thumbnail.jpg';
   
   // Puts the keywords back into a single string
   $parsedKeywords = implode('|', $keywords);
   $formComp['keywords'] = $parsedKeywords;
   $formComp['keywordsList'] = Keywords::display($keywords);
   
   // Full template
   $finalTplInput = array('articleID' => $article->get('id_article'), 
   'editionForm' => '', 
   'segmentsList' => '', 
   'newSegmentButton' => !$article->isPublished() ? $article->get('id_article') : '', 
   'truePreviewButton' => '', 
   'publication' => '', 
   'highlighting' => '');
   
   $highlightFormInput = null;
   if($article->isPublished())
   {
      $finalTplInput['publication'] = 'published||'.$article->get('id_article').'|'.$article->get('views');
      
      // Highlighting form
      if(Utils::check(LoggedUser::$data['can_edit_all_posts']))
      {
         $highlightFormInput = array('success' => '', 
         'errors' => '', 
         'ID' => $article->get('id_article'), 
         'highlight' => '', 
         'featured' => Utils::check($article->get('featured')) ? 'checked' : '');
         
         $highlightImg = $article->getHighlight();
         $bufferedHighlight = Buffer::getHighlight();
         if(strlen($highlightImg) > 0)
            $highlightFormInput['highlight'] = $highlightImg;
         else if(strlen($bufferedHighlight) > 0)
            $highlightFormInput['highlight'] = './'.substr($bufferedHighlight, strlen(PathHandler::HTTP_PATH()));
         else
            $highlightFormInput['highlight'] = './default_article_highlight.jpg';
         
         $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
         
         // Highlight creation dialog
         $highlightTpl = TemplateEngine::parse('view/dialog/CustomHighlight.dialog.ctpl');
         if(!TemplateEngine::hasFailed($highlightTpl))
            $dialogs .= $highlightTpl;
      }
   }
   else if(count($segments) > 0)
   {
      $finalTplInput['publication'] = 'publish||'.$article->get('id_article');
   }
   else
   {
      $finalTplInput['publication'] = 'empty||'.$article->get('id_article');
   }
   
   // Lists segments
   if(count($segments) > 0)
   {
      require './view/intermediate/SegmentListItem.ir.php';
   
      $segmentsInput = array();
      for($i = 0; $i < count($segments); $i++)
      {
         $segmentIR = SegmentListItemIR::process($segments[$i], $article->isPublished());
         if($i == count($segments) - 1)
            $segmentIR['moveDown'] = '';
         array_push($segmentsInput, $segmentIR);
      }
      $segmentsOutput = TemplateEngine::parseMultiple('view/user/SegmentListItem.item.ctpl', $segmentsInput);
      
      $segmentsTpl = "<table id=\"segmentsList\">\n";
      for($i = 0; $i < count($segmentsOutput); $i++)
         $segmentsTpl .= $segmentsOutput[$i]."\n";
      $segmentsTpl .= "</table>\n";
      
      $finalTplInput['segmentsList'] = $segmentsTpl;
      if(!TemplateEngine::hasFailed($segmentsTpl))
         $finalTplInput['truePreviewButton'] = PathHandler::articleURL($article->getAll());
   }

   // New input only
   $formInput = array('thumbnail' => '',
   'title' => '', 
   'subtitle' => '', 
   'type' => '', 
   'keywords' => '');
   
   // Form treatment is similar to that of NewArticle.php
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
      $newKeywords = explode('|', $formInput['keywords']);
      
      // Various errors (title already used for alias, wrong genre, etc.)
      if(!$fullyCompleted)
         $formComp['errors'] .= 'emptyFields|';
      if(!in_array($formInput['type'], $validTypes))
         $formComp['errors'] .= 'invalidType|';
      if(strlen($formInput['title']) > 100 || strlen($formInput['subtitle']) > 100)
         $formComp['errors'] .= 'tooLongData|';
      if($formInput['thumbnail'] !== './default_article_thumbnail.jpg' && !file_exists(PathHandler::WWW_PATH().substr($formInput['thumbnail'], 2)))
         $formComp['errors'] .= 'invalidThumbnail|';
      if(count($newKeywords) == 1 && strlen($newKeywords[0]) == 0)
         $formComp['errors'] .= 'noKeywords|';
      
      if(strlen($formComp['errors']) == 0)
      {
         // Finally updates the article
         try
         {
            $article->update($formInput['title'], $formInput['subtitle'], $formInput['type']);
         }
         catch(Exception $e)
         {
            $formComp['errors'] = 'dbError';
            $formComp['thumbnail'] = $formInput['thumbnail'];
            $formComp['title'] = $formInput['title'];
            $formComp['subtitle'] = $formInput['subtitle'];
            $formComp['type'] = $formInput['type'].'||'.$typeChoices;
            $formComp['keywords'] = $formInput['keywords'];
            $formComp['keywordsList'] = Keywords::display($newKeywords);
         
            $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
            $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
            WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
         }
         
         // Updates the thumbnail if edited
         if($formInput['thumbnail'] !== $formComp['thumbnail'] || (strlen($article->getThumbnail()) == 0 && $formComp['thumbnail'] !== './default_article_thumbnail.jpg'))
         {
            $fileName = substr(strrchr($formInput['thumbnail'], '/'), 1);
            Buffer::save('upload/articles/'.$article->get('id_article'), $fileName, 'thumbnail');
         }
         
         // Updates the keywords
         $nbCommonKeywords = sizeof(Keywords::common($keywords, $newKeywords));
         $keywordsToDelete = Keywords::distinct($keywords, $newKeywords);
         $keywordsToAdd = Keywords::distinct($newKeywords, $keywords);
         
         // Deletes the keywords absent from the new string
         try
         {
            Tag::unmapArticle($article->get('id_article'), $keywordsToDelete);
         }
         catch(Exception $e) { } // No dedicated error printed for now
         
         // Adds the new keywords (maximum 10 - $nbCommonKeywords)
         for($j = 0; $j < count($keywordsToAdd) && $j < (10 - $nbCommonKeywords); $j++)
         {
            try
            {
               $tag = new Tag($keywordsToAdd[$j]);
               $tag->mapToArticle($article->get('id_article'));
            }
            catch(Exception $e)
            {
               continue;
            }
         }
         
         // Cleans the DB from tags that are no longer mapped to anything
         Tag::cleanOrphanTags();
         
         // Reloads page and notifies the user everything was updated
         $formComp['success'] = 'yes';
         if($formInput['thumbnail'] !== './default_article_thumbnail.jpg')
            $formComp['thumbnail'] = './upload/articles/'.$article->get('id_article').'/thumbnail.jpg';
         else
            $formComp['thumbnail'] = './default_article_thumbnail.jpg';
         $formComp['title'] = $formInput['title'];
         $formComp['subtitle'] = $formInput['subtitle'];
         $formComp['type'] = $formInput['type'].'||'.$typeChoices;
         $formComp['keywords'] = $formInput['keywords'];
         $formComp['keywordsList'] = Keywords::display($newKeywords);

         $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
         $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
         WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
      }
      else
      {
         $formComp['errors'] = substr($formComp['errors'], 0, -1);
         $formComp['thumbnail'] = $formInput['thumbnail'];
         $formComp['title'] = $formInput['title'];
         $formComp['subtitle'] = $formInput['subtitle'];
         $formComp['type'] = $formInput['type'].'||'.$typeChoices;
         $formComp['keywords'] = $formInput['keywords'];
         $formComp['keywordsList'] = Keywords::display($newKeywords);
         
         $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
         $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
         WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
      }
   }
   else if(!empty($_POST['highlightThis']))
   {
      $picture = Utils::secure($_POST['highlight']);
      
      if($picture !== $highlightFormInput['highlight'] && !file_exists(PathHandler::WWW_PATH().substr($picture, 2)))
         $highlightFormInput['errors'] = 'invalidHighlight';
      
      if(strlen($highlightFormInput['errors']) == 0)
      {
         if((isset($_POST['featured']) && !Utils::check($article->get('featured'))) || (!isset($_POST['featured']) && Utils::check($article->get('featured'))))
         {
            try
            {
               $res = $article->feature();
               if($res)
                  $highlightFormInput['featured'] = 'checked';
               else
                  $highlightFormInput['featured'] = '';
            }
            catch(Exception $e)
            {
               $highlightFormInput['errors'] = 'dbError';
               $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
               $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
               WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
            }
         }
         
         // Updates the highlight picture if edited
         if($highlightFormInput['highlight'] !== $picture || (strlen($article->getHighlight()) == 0 && $picture !== './default_article_highlight.jpg'))
         {
            $fileName = substr(strrchr($picture, '/'), 1);
            $highlightFormInput['highlight'] = './upload/articles/'.$article->get('id_article').'/highlight.jpg';
            Buffer::save('upload/articles/'.$article->get('id_article'), $fileName, 'highlight');
         }
         
         $highlightFormInput['success'] = 'yes';
         $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
         $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
         $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
         WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
      }
      else
      {
         $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
         $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
         $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
         WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
      }
   }
   else
   {
      $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
      $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
      WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>
