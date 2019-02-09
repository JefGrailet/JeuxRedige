<?php

/*
* Script to create a segment, i.e., a page of a full article (which can be made of several 
* segments or a single segment). As long as the current user is the author of the article and that 
* this article is not published (yet), (s)he can create a new segment for it.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './libraries/Buffer.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to edit games
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditArticle.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   $nextPosition = 0;
   try
   {
      $article = new Article($articleID);
      $nextPosition = $article->nextSegmentPosition();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingArticle';
      $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Article introuvable');
   }
   
   // Can only create a new segment for one's own articles
   if(!$article->isMine())
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cet article n\'est pas le vôtre');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('article_edition');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('segment_editor');
   WebpageHandler::changeContainer('fullWidthSequence');
   
   // Dialogs
   $dialogs = '';
   if(Utils::check(LoggedUser::$data['can_upload']))
   {
      $headerDialogTpl = TemplateEngine::parse('view/dialog/CreateSegmentHeader.dialog.ctpl');
      if(!TemplateEngine::hasFailed($headerDialogTpl))
         $dialogs .= $headerDialogTpl;
      $fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
      if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
         $dialogs .= $fileUploadDialogTpl;
   }
   $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
   if(!TemplateEngine::hasFailed($formattingDialogsTpl))
      $dialogs .= $formattingDialogsTpl;
   $eFormattingDialogsTpl = TemplateEngine::parse('view/dialog/ExtendedFormatting.multiple.ctpl');
   if(!TemplateEngine::hasFailed($eFormattingDialogsTpl))
      $dialogs .= $eFormattingDialogsTpl;
   
   // Header details (default image or buffered image)
   $currentSegmentHeader = Buffer::getSegmentHeader();
   $currentHeaderValue = '';
   if(strlen($currentSegmentHeader) == 0)
      $currentSegmentHeader = './default_article_header.jpg';
   else
      $currentHeaderValue = './'.substr($currentSegmentHeader, strlen(PathHandler::HTTP_PATH));
   
   $formData = array('errors' => '', 
   'articleID' => $article->get('id_article'), 
   'fullArticleTitle' => $article->get('title').' - '.$article->get('subtitle'), 
   'headerPath' => $currentSegmentHeader, 
   'title' => '', 
   'noteFirstSegment' => ($nextPosition == 1) ? 'yes' : '', 
   'content' => '', 
   'header' => $currentHeaderValue, 
   'mediaMenu' => '');
   
   // Generates upload window view
   $nbUploads = 0; // Useful later
   if(Utils::check(LoggedUser::$data['can_upload']))
   {
      $uploadsList = Buffer::listContent();
      $nbUploads = count($uploadsList[0]);
   
      $uploadTplInput = array('uploadMessage' => 'newUpload', 'uploadsView' => Buffer::renderForSegment($uploadsList));
      $uploadTpl = TemplateEngine::parse('view/user/NewSegment.upload.ctpl', $uploadTplInput);

      if(!TemplateEngine::hasFailed($uploadTpl))
         $formData['mediaMenu'] = $uploadTpl;
   }
   else
   {
      $uploadTplInput = array('uploadMessage' => 'uploadRefused', 'uploadsView' => '');
      $uploadTpl = TemplateEngine::parse('view/user/NewSegment.upload.ctpl', $uploadTplInput);

      if(!TemplateEngine::hasFailed($uploadTpl))
         $formData['mediaMenu'] = $uploadTpl;
   }
   
   // Form treatment starts here
   if(!empty($_POST['sent']) || !empty($_POST['sentBis']))
   {
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['content'] = Utils::secure($_POST['message']);
      $formData['header'] = Utils::secure($_POST['header']);
      
      // Various possible errors
      if(strlen($formData['title']) == 0 && $nextPosition > 1)
         $formData['errors'] .= 'titleNeeded'; // No | because for now there can be only one error at once
      else if(strlen($formData['title']) > 100)
         $formData['errors'] .= 'titleTooLong';
      
      if(strlen($formData['errors']) == 0)
      {
         $newSeg = null;
         try
         {
            $newSeg = Segment::insert($article->get('id_article'), 
                                      $formData['title'], 
                                      $nextPosition, 
                                      FormParsing::parse($formData['content']));
         }
         catch(Exception $e)
         {
            $formData['errors'] = 'dbError';
            $formTpl = TemplateEngine::parse('view/user/NewSegment.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Créer un nouveau segment pour l\'article "'.$article->get('title').'"', $dialogs);
         }
         
         // Takes care of upload if allowed (and if using full form)
         $uploads = Buffer::listContent();
         if(count($uploads[0]) > 0)
         {
            $uploadsString = Buffer::saveInSegment($uploads, 
                                               $article->get('id_article'), 
                                               $newSeg->get('id_segment'));
            
            if(strlen($uploadsString) > 0)
            {
               try
               {
                  $modifiedContent = FormParsing::relocateInSegment($newSeg->get('content'), 
                                                                    $article->get('id_article'), 
                                                                    $newSeg->get('id_segment'));
                  
                  $newSeg->finalize('uploads:'.$uploadsString, $modifiedContent);
               }
               catch(Exception $e) {}
            }
         }
         
         // Saves new header
         if($formData['header'] !== '' && file_exists(PathHandler::WWW_PATH.substr($formData['header'], 2)))
         {
            $fileName = substr(strrchr($formData['header'], '/'), 1);
            Buffer::save('upload/articles/'.$article->get('id_article').'/'.$newSeg->get('id_segment'), $fileName, 'header');
         }
         
         // Specific redirection (if asked)
         if(!empty($_POST['sentBis']))
         {
            $updatedURL = PathHandler::articleURL($article->getAll(), $newSeg->get('position'));
            header('Location:'.$updatedURL);
         }
         // Default redirection
         else
         {
            $articleEditionURL = './EditArticle.php?id_article='.$article->get('id_article');
            header('Location:'.$articleEditionURL);
         }
         
         // Success page
         $tplInput = array('title' => $formData['title'] != NULL ? $formData['title'] : 'Sommaire', 
                           'target' => $articleEditionURL, 
                           'articleTitle' => $article->get('title'));
         $successPage = TemplateEngine::parse('view/user/NewSegment.success.ctpl', $tplInput);
         WebpageHandler::resetDisplay();
         WebpageHandler::wrap($successPage, 'Nouveau segment créé pour l\'article "'.$article->get('title').'"');
      }
      else
      {
         // $formData['errors'] = substr($formData['errors'], 0, -1);
         $formTpl = TemplateEngine::parse('view/user/NewSegment.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Créer un nouveau segment pour l\'article "'.$article->get('title').'"', $dialogs);
      }
   }
   else
   {
      $formTpl = TemplateEngine::parse('view/user/NewSegment.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Créer un nouveau segment pour l\'article "'.$article->get('title').'"', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingArticleID');
   $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>