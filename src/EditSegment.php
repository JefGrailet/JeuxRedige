<?php

/*
* Script to edit a segment, i.e., a page of a full article (which can be made of several segments
* or a single segment). As long as the current user is the author of the article, (s)he can edit
* any of its segments.
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

if(!empty($_GET['id_segment']) && preg_match('#^([0-9]+)$#', $_GET['id_segment']))
{
   $segmentID = intval(Utils::secure($_GET['id_segment']));
   $segment = null;
   $article = null;
   try
   {
      $segment = new Segment($segmentID);
      $article = new Article($segment->get('id_article'));
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      $errorPageTitle = '';
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
      {
         if(strstr($e->getMessage(), 'Segment') != FALSE)
         {
            $tplInput['error'] = 'nonexistingSegment';
            $errorPageTitle = 'Segment introuvable';
         }
         else
         {
            $tplInput['error'] = 'nonexistingArticle';
            $errorPageTitle = 'Article introuvable';
         }
      }
      $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, $errorPageTitle);
   }

   // Forbidden access if the user's neither the author, neither an admin
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cet article n\'est pas le vôtre');
   }

   // Webpage settings
   WebpageHandler::addCSS('preview');
   WebpageHandler::addCSS('article_edition'); // Put here to override some values of preview.css
   WebpageHandler::addJS('preview');
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

   $formData = array('success' => '',
   'errors' => '',
   'segmentID' => $segment->get('id_segment'),
   'articleID' => $article->get('id_article'),
   'fullArticleTitle' => $article->get('title').' - '.$article->get('subtitle'),
   'headerPath' => '',
   'title' => $segment->get('title'),
   'noteFirstSegment' => ($segment->get('position') == 1) ? 'yes' : '',
   'content' => FormParsing::unparse($segment->get('content')),
   'adminEdit' => (!$article->isMine() && Utils::check(LoggedUser::$data['can_edit_all_posts'])) ? 'yes||' : '',
   'header' => '',
   'mediaMenu' => '');

   // Header path
   $curHeader = $segment->getHeader();
   $bufferedHeader = Buffer::getSegmentHeader();
   if($curHeader !== "")
      $formData['headerPath'] = $curHeader;
   else if($bufferedHeader !== "")
   {
      $formData['headerPath'] = $bufferedHeader;
      $formData['header'] = './'.substr($bufferedHeader, strlen(PathHandler::HTTP_PATH()));
   }
   else
      $formData['headerPath'] = './default_article_header.jpg';

   // Takes care of previous uploads of this segment
   $attachArr = array(); // Empty array
   if ($segment->get('attachment') !== NULL && strlen($segment->get('attachment')) > 0)
      $attachArr = explode('|', $segment->get('attachment'));
   $existingUploads = '';
   $nbExistingUploads = 0;
   for($i = 0; $i < count($attachArr); $i++)
   {
      if(substr($attachArr[$i], 0, 7) === 'uploads')
      {
         $splitted = explode(':', $attachArr[$i]);
         $uploads = explode(',', $splitted[1]);
         $nbExistingUploads = count($uploads);

         if($nbExistingUploads == 0)
            break;

         $rendered = Buffer::renderForSegment(Buffer::listMiniatures($uploads),
                                          $article->get('id_article'),
                                          $segment->get('id_segment'));

         $existingUploads = 'yes||'.(Utils::UPLOAD_OPTIONS['bufferLimit'] - $nbExistingUploads).'|'.$rendered;

         break;
      }
   }

   // Generates upload window view
   $nbUploads = 0; // Useful later
   if(Utils::check(LoggedUser::$data['can_upload']))
   {
      $uploadsList = Buffer::listContent();
      $nbUploads = count($uploadsList[0]);

      $uploadTplInput = array('previousUploads' => $existingUploads,
                              'uploadMessage' => 'newUpload',
                              'uploadsView' => Buffer::renderForSegment($uploadsList));
      $uploadTpl = TemplateEngine::parse('view/user/EditSegment.upload.ctpl', $uploadTplInput);

      if(!TemplateEngine::hasFailed($uploadTpl))
         $formData['mediaMenu'] = $uploadTpl;
   }
   else
   {
      $uploadTplInput = array('previousUploads' => $existingUploads,
                              'uploadMessage' => 'uploadRefused',
                              'uploadsView' => '');
      $uploadTpl = TemplateEngine::parse('view/user/EditSegment.upload.ctpl', $uploadTplInput);

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
      if(strlen($formData['title']) == 0 && $segment->get('position') > 1)
         $formData['errors'] .= 'titleNeeded'; // No | because for now there can be only one error at once
      else if(strlen($formData['title']) > 100)
         $formData['errors'] .= 'titleTooLong';

      // Option for admins to not record date of editionForm
      $doNotRecordDate = false;
      if(Utils::check(LoggedUser::$data['can_edit_all_posts']) && isset($_POST['do_not_record']))
      {
         $doNotRecordDate = true;
         $formData['adminEdit'] = 'yes|| checked';
      }

      if(strlen($formData['errors']) == 0)
      {
         Database::beginTransaction();
         try
         {
            // Update of the message itself
            $segment->update($formData['title'],
                             FormParsing::parse($formData['content']),
                             ($article->isPublished() && !$doNotRecordDate));

            if(!$doNotRecordDate)
                $article->recordDate($segment->get('date_last_modification'));

             // New uploads
            $uploads = Buffer::listContent();
            if(count($uploads[0]) > 0 && $nbExistingUploads < Utils::UPLOAD_OPTIONS['bufferLimit'])
            {
               $modifiedContent = FormParsing::relocateInSegment($segment->get('content'),
                                                                 $article->get('id_article'),
                                                                 $segment->get('id_segment'));

               $maxUploads = Utils::UPLOAD_OPTIONS['bufferLimit'] - $nbExistingUploads;
               $newUploadsString = Buffer::saveInSegment($uploads,
                                                     $article->get('id_article'),
                                                     $segment->get('id_segment'),
                                                     $maxUploads);

               $newUploadsFull = '';
               if(strlen($newUploadsString) > 0 && $nbExistingUploads > 0)
               {
                  for($i = 0; $i < count($attachArr); $i++)
                  {
                     if(substr($attachArr[$i], 0, 7) === 'uploads')
                     {
                        $attachArr[$i] .= ','.$newUploadsString;
                        $newUploadsFull = explode(',', substr($attachArr[$i], 8));
                        break;
                     }
                  }

                  $segment->finalize(implode('|', $attachArr), $modifiedContent);
               }
               else if(strlen($newUploadsString) > 0)
               {
                  array_push($attachArr, 'uploads:'.$newUploadsString);
                  $newUploadsFull = explode(',', $newUploadsString);

                  $segment->finalize(implode('|', $attachArr), $modifiedContent);
               }

               // Updates the form with respect to uploads
               $newRendered = Buffer::renderForSegment(Buffer::listMiniatures($newUploadsFull),
                                                   $article->get('id_article'),
                                                   $segment->get('id_segment'));

               $newExisting = 'yes||'.(Utils::UPLOAD_OPTIONS['bufferLimit'] - count($newUploadsFull)).'|'.$newRendered;

               if(Utils::check(LoggedUser::$data['can_upload']))
               {
                  $uploadsList = Buffer::listContent();
                  $uploadTplInput = array('previousUploads' => $newExisting,
                                          'uploadMessage' => 'newUpload',
                                          'uploadsView' => Buffer::renderForSegment($uploadsList));
                  $uploadTpl = TemplateEngine::parse('view/user/EditSegment.upload.ctpl', $uploadTplInput);

                  if(!TemplateEngine::hasFailed($uploadTpl))
                     $formData['mediaMenu'] = $uploadTpl;
               }
               else
               {
                  $uploadTplInput = array('previousUploads' => $newExisting,
                                          'uploadMessage' => 'uploadRefused',
                                          'uploadsView' => '');
                  $uploadTpl = TemplateEngine::parse('view/user/EditSegment.upload.ctpl', $uploadTplInput);

                  if(!TemplateEngine::hasFailed($uploadTpl))
                     $formData['mediaMenu'] = $uploadTpl;
               }

               $formData['content'] = FormParsing::relocateInSegment($formData['content'],
                                                                     $article->get('id_article'),
                                                                     $segment->get('id_segment'));
            }
            Database::commit();
         }
         catch(Exception $e)
         {
            Database::rollback();
            $formData['error'] = 'dbError';
            $formTpl = TemplateEngine::parse('view/user/EditSegment.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Modifier une page de l\'article "'.$article->get('title').'"', $dialogs);
         }

         // Saves new header
         if($formData['header'] !== '' && file_exists(PathHandler::WWW_PATH().substr($formData['header'], 2)))
         {
            $fileName = substr(strrchr($formData['header'], '/'), 1);
            Buffer::save('upload/articles/'.$article->get('id_article').'/'.$segment->get('id_segment'), $fileName, 'header');
            $formData['headerPath'] = $segment->getHeader();
         }

         // Redirection (if asked)
         if(!empty($_POST['sentBis']))
         {
            $updatedURL = PathHandler::articleURL($article->getAll(), $segment->get('position'));
            header('Location:'.$updatedURL);
         }

         // Success page
         $formData['success'] = 'yes';
         $formTpl = TemplateEngine::parse('view/user/EditSegment.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier une page de l\'article "'.$article->get('title').'"', $dialogs);
      }
      else
      {
         // $formData['errors'] = substr($formData['errors'], 0, -1);
         $formTpl = TemplateEngine::parse('view/user/EditSegment.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier une page de l\'article "'.$article->get('title').'"', $dialogs);
      }
   }
   else
   {
      $formTpl = TemplateEngine::parse('view/user/EditSegment.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier une page de l\'article "'.$article->get('title').'"', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingSegmentID');
   $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>
