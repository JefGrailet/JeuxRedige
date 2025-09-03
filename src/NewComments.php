<?php

/**
* This script is a variant of NewTopic.php which allows one to create a new comments topic bound 
* to a "commentable" entry (small pieces of content which the comments are optional, e.g., 
* reviews).
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in...
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/content/NewComments.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
// ...and allowed to create new topics
else if(!Utils::check(LoggedUser::$data['can_create_topics']))
{
   $errorTplInput = array('error' => 'permission');
   $tpl = TemplateEngine::parse('view/content/NewComments.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas (encore) autorisé à créer des sujets');
}

require './model/Commentable.class.php';

$commentable = NULL;
$autoMessage = '';
$what = '';
if(!empty($_GET['id_content']) && preg_match('#^([0-9]+)$#', $_GET['id_content']))
{
   $getID = intval($_GET['id_content']);
   try
   {
      $what = Commentable::whatKind($getID);
      if($what === 'Trivia')
      {
         require './model/Trivia.class.php';
         require './view/intermediate/TriviaFirstReaction.ir.php';
         $commentable = new Trivia($getID);
         $autoMessage = TriviaFirstReactionIR::process($commentable->getAll());
      }
      else if($what === 'GamesList')
      {
         require './model/GamesList.class.php';
         require './view/intermediate/ListFirstReaction.ir.php';
         $commentable = new GamesList($getID);
         $autoMessage = ListFirstReactionIR::process($commentable->getAll());
      }
      else
      {
         if($what === 'Missing')
            $errorTplInput = array('error' => 'missingCommentable');
         else
            $errorTplInput = array('error' => 'corruptedCommentable');
         $tpl = TemplateEngine::parse('view/content/NewComments.fail.ctpl', $errorTplInput);
         WebpageHandler::wrap($tpl, 'Impossible de créer les commentaires');
      }
   }
   catch(Exception $e)
   {
      $errorTplInput = array('error' => 'dbErrorCommentable');
      $tpl = TemplateEngine::parse('view/content/NewComments.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible de créer les commentaires');
   }
}
else
{
   $errorTplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/NewComments.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible de créer les commentaires');
}

require './model/Topic.class.php';
require './model/Post.class.php';
require './model/Emoticon.class.php';
require './model/Tag.class.php';
require './libraries/FormParsing.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/Upload.lib.php';
require './libraries/Buffer.lib.php';

$isUserAuthor = (LoggedUser::$data['pseudo'] === $commentable->get('pseudo'));

// Webpage settings
WebpageHandler::addCSS('topic');
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('topic_medium');
WebpageHandler::addCSS('preview');
WebpageHandler::addJS('uploads');
if($isUserAuthor)
   WebpageHandler::addJS('keywords'); // Only author of the commentable can edit keywords
WebpageHandler::addJS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::changeContainer('topicContent');

// Various dialogs for formatting
$dialogs = '';
$fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
   $dialogs .= $fileUploadDialogTpl;
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

if($isUserAuthor)
{
   $thumbnailDialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
   if(!TemplateEngine::hasFailed($thumbnailDialogTpl))
      $dialogs .= $thumbnailDialogTpl;
}

// User's upload buffer
$uploadsList = Buffer::listContent();
$nbUploads = count($uploadsList[0]);

// Custom thumbnail is only possible if user's the author of the commentable
$customThumbnail = false;
$currentThumbnailPath = '';
$currentThumbnail = 'none';
if($isUserAuthor)
{
   $customThumbnail = true;
   $currentThumbnailPath = Buffer::getThumbnail();
   if(strlen($currentThumbnailPath) == 0)
      $currentThumbnailPath = './'.substr($commentable->getThumbnail(), strlen(PathHandler::HTTP_PATH()));
   else
      $currentThumbnail = './'.substr($currentThumbnailPath, strlen(PathHandler::HTTP_PATH()));
}
// Just for display with CommentsSettings.ctpl
else
{
   $currentThumbnailPath = './'.substr($commentable->getThumbnail(), strlen(PathHandler::HTTP_PATH()));
}

// Default title for the topic
$defaultTitle = 'Réactions: '.$commentable->get('title');
if(strlen($defaultTitle) > 50)
{
   $pos = strrpos(substr($defaultTitle, 0, 50), ' ');
   if($pos !== FALSE)
      $defaultTitle = substr($defaultTitle, 0, $pos)."...";
}

/*
* Topic settings are dealt by a separate part of the form which display depends on whether the
* user is the author of the commentable. If not, (s)he will only see a message stating that
* default settings will be used for the new topic.
*/

$defaultKeywords = $commentable->getKeywords();
$keywordsView = '';
if($isUserAuthor)
   $keywordsView = Keywords::display($defaultKeywords);
else
   $keywordsView = Keywords::displayPlain($defaultKeywords);

$topicSettings = array('title' => $defaultTitle, 
'thumbnailPath' => $currentThumbnailPath, 
'anonChecked' => '',
'uploadsChecked' => 'checked',
'keywordsList' => $keywordsView,
'keywords' => implode('|', $defaultKeywords),
'thumbnail' => $currentThumbnail);

// Array which serves both for template input and collecting $_POST values
$formData = array('commentableID' => $commentable->get('id_commentable'), 
'errors' => '', 
'settings' => '', // Will contain the template for the settings
'content' => '', 
'uploadOptions' => '', 
'uploadMessage' => 'newUpload', 
'uploadsView' => Buffer::render($uploadsList));

// Upload options input (if necessary)
$uploadDisplayChoice = '';
if(!empty($_POST['upload_display_policy']))
{
   $uploadDisplayChoice = Utils::secure($_POST['upload_display_policy']);
   $uploadOptionsStr = 'yes||';
   for($i = 0; $i < count(Utils::UPLOAD_OPTIONS['displayPolicies']); $i++)
   {
      if($i > 0)
         $uploadOptionsStr .= '|';
   
      if($uploadDisplayChoice === Utils::UPLOAD_OPTIONS['displayPolicies'][$i])
         $uploadOptionsStr .= ' selected="selected"';
      else
         $uploadOptionsStr .= 'null';
   }
   
   $formTplInput['uploadOptions'] = $uploadOptionsStr;
}
else if($nbUploads > 0)
{
   $formTplInput['uploadOptions'] = 'yes||null|null|null';
}

if(!empty($_POST['sent']))
{
   $formData['content'] = Utils::secure($_POST['message']);
   
   // Settings of the topic
   $anonPosting = false;
   $enableUploads = true;
   if($isUserAuthor)
   {
      $topicSettings['title'] = Utils::secure($_POST['title']);
      $topicSettings['thumbnail'] = Utils::secure($_POST['thumbnail']);
      $topicSettings['keywords'] = Utils::secure($_POST['keywords']);
      
      // Checkboxes for the general configuration of the thread
      if(isset($_POST['anon_posting']))
      {
         $anonPosting = true;
         $topicSettings['anonChecked'] = 'checked';
      }
      else
         $topicSettings['anonChecked'] = '';
      
      if(isset($_POST['enable_uploads']))
      {
         $enableUploads = true;
         $topicSettings['uploadsChecked'] = 'checked';
      }
      else
      {
         $enableUploads = false;
         $topicSettings['uploadsChecked'] = '';
      }
   }
   
   $keywordsArr = explode('|', $topicSettings['keywords']);
   $topicSettings['keywordsList'] = Keywords::display($keywordsArr);
   
   $thumbnail = '';
   if($customThumbnail)
   {
      if($topicSettings['thumbnail'] !== 'none' && file_exists(PathHandler::WWW_PATH().substr($topicSettings['thumbnail'], 2)))
      {
         $topicSettings['thumbnailPath'] = $topicSettings['thumbnail'];
         $thumbnail = 'CUSTOM';
      }
   }
   
   // Gets the delay between current time and the lattest topic created by this user
   $delay = 3600;
   try
   {
      $delay = Topic::getUserDelay();
   }
   catch(Exception $e) { }
   
   // Errors (missing title/content and/or title too long)
   if(strlen($topicSettings['title']) == 0 OR strlen($formData['content']) == 0)
      $formData['errors'] .= 'emptyFields|';
   else if(strlen($topicSettings['title']) > 50)
      $formData['errors'] .= 'titleTooLong|';
   if(count($keywordsArr) == 1 && strlen($keywordsArr[0]) == 0)
      $formData['errors'] .= 'noKeywords|';
   
   // User created a new topic less than 30 minutes ago
   if($delay < WebpageHandler::$miscParams['consecutive_topics_delay'])
      $formData['errors'] .= 'tooManyTopics|';
   
   if(strlen($formData['errors']) == 0)
   {
      // Creates the topic; everything is done with a single transaction
      Database::beginTransaction();
      $newTopic = null;
      try
      {
         // Message written by the user
         $parsedContent = Emoticon::parseEmoticonsShortcuts($formData['content']);
         $parsedContent = FormParsing::parse($parsedContent);
         
         // Creates the topic
         $newTopic = Topic::autoInsert($topicSettings['title'], 
                                       'CUSTOM', 
                                       1, 
                                       $anonPosting, 
                                       $enableUploads, 
                                       $commentable->get('pseudo'));
         
         // Inserts automatic message and the user's message before updating
         $autoPost = Post::autoInsert($newTopic->get('id_topic'), FormParsing::parse($autoMessage), $commentable->get('pseudo'));
         $newPost = Post::insert($newTopic->get('id_topic'), $parsedContent);
         $newTopic->update($newPost->getAll());
         $commentable->bindTopic($newTopic->get('id_topic'));
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewComments.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet', $dialogs);
      }
      
      // Saves the (custom) thumbnail
      if($thumbnail === 'CUSTOM' && $customThumbnail)
      {
         $fileName = substr(strrchr($topicSettings['thumbnail'], '/'), 1);
         Buffer::save('upload/topics/'.$newTopic->get('id_topic'), $fileName, 'thumbnail');
      }
      // Automatic thumbnail
      else
      {
         $toResize = array('name' => 'thumbnail.jpg',
         'tmp_name' => $commentable->getThumbnail(true));
         Upload::storeResizedPicture($toResize, 'upload/topics/'.$newTopic->get('id_topic'), 260, 162);
      }
      
      // Inserts keywords; we move to the next if an exception occurs while mapping the keywords
      for($i = 0; $i < count($keywordsArr) && $i < 10; $i++)
      {
         if(strlen($keywordsArr[$i]) == 0)
            continue;
      
         try
         {
            $tag = new Tag($keywordsArr[$i]);
            $tag->mapToTopic($newTopic->get('id_topic'));
         }
         catch(Exception $e)
         {
            continue;
         }
      }
      
      // Saves uploads
      if($enableUploads)
      {
         $uploads = Buffer::listContent();
         if(count($uploads[0]) > 0)
         {
            $uploadsString = Buffer::saveInTopic($uploads, 
                                        $newTopic->get('id_topic'), 
                                        $newPost->get('id_post'));
            
            if(strlen($uploadsString) > 0)
            {
               try
               {
                  $uploadPrefix = 'uploads';
                        
                  // Display policy chosen by the user
                  if(strlen($uploadDisplayChoice) > 0)
                  {
                     if(in_array($uploadDisplayChoice, Utils::UPLOAD_OPTIONS['displayPolicies']))
                        $uploadPrefix .= '_'.$uploadDisplayChoice;
                  }
               
                  $modifiedContent = FormParsing::relocate($newPost->get('content'), 
                                                           $newTopic->get('id_topic'), 
                                                           $newPost->get('id_post'));
               
                  $newPost->finalize($uploadPrefix.':'.$uploadsString, $modifiedContent);
               }
               catch(Exception $e) {}
            }
         }
      }
      
      // URLs for the new topic
      $editTopicURL = './EditTopic.php?id_topic='.$newTopic->get('id_topic');
      $newTopicURL = PathHandler::topicURL($newTopic->getAll());
      
      // Notification sent to the author of the content if (s)he didn't comment him-/herself
      if(!$isUserAuthor)
      {
         require './model/Notification.class.php';
         
         try
         {
            // Title
            $notificationTitle = 'Votre ';
            if($what === 'Trivia')
               $notificationTitle .= 'anecdote';
            else if($what === 'GamesList')
               $notificationTitle .= 'liste';
            $notificationTitle .= ' a été commenté';
            if($what === 'Trivia' || $what === 'GamesList')
               $notificationTitle .= 'e';
            
            // Full title in the message
            $fullTitle = '';
            switch($what)
            {
               case 'Trivia':
                  $fullTitle = 'anecdote <em>'.$commentable->get('title').'</em> pour le jeu '.$commentable->get('game');
                  break;
               
               case 'GamesList':
                  $fullTitle = 'liste <em>'.$commentable->get('title').'</em>';
                  break;
               
               // Shouldn't occur
               default:
                  break;
            }
            
            $notificationInput = array('user' => LoggedUser::$data['used_pseudo'], 
                                       'title' => $fullTitle, 
                                       'editLink' => $editTopicURL,
                                       'topicLink' => $newTopicURL);
            $notificationMsg = TemplateEngine::parse('view/user/NewComments.notification.ctpl', $notificationInput);
            if(!TemplateEngine::hasFailed($notificationMsg))
            {
               Notification::insert($commentable->get('pseudo'), $notificationTitle, $notificationMsg);
            }
         }
         catch(Exception $e) {}
      }
      
      // Redirection
      header('Location:'.$newTopicURL);
      
      // Success page
      $tplInput = array('target' => $newTopicURL);
      $successPage = TemplateEngine::parse('view/content/NewTopic.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Créer un nouveau sujet de réactions');
   }
   else
   {
      if($isUserAuthor)
         $formData['settings'] = TemplateEngine::parse('view/content/CommentsSettings.subform.ctpl', $topicSettings);
      else
         $formData['settings'] = TemplateEngine::parse('view/content/CommentsSettings.ctpl', $topicSettings);
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewComments.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet de réactions', $dialogs);
   }
}
else
{
   if($isUserAuthor)
      $formData['settings'] = TemplateEngine::parse('view/content/CommentsSettings.subform.ctpl', $topicSettings);
   else
      $formData['settings'] = TemplateEngine::parse('view/content/CommentsSettings.ctpl', $topicSettings);
   $formTpl = TemplateEngine::parse('view/content/NewComments.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet de réactions', $dialogs);
}
   
?>
