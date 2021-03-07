<?php

/**
* This script is designed to allow a logged in user to create a new topic; this includes the title,
* the first message, some options (can anonymous users post ?) and thumbnail selection/creation.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in...
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('problem' => 'login');
   $tpl = TemplateEngine::parse('view/content/NewTopic.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
// ...and allowed to create new topics
else if(!Utils::check(LoggedUser::$data['can_create_topics']))
{
   $errorTplInput = array('problem' => 'permission');
   $tpl = TemplateEngine::parse('view/content/NewTopic.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas (encore) autorisé à créer des sujets');
}

require './model/Topic.class.php';
require './model/Post.class.php';
require './model/Emoticon.class.php';
require './model/Tag.class.php';
require './libraries/FormParsing.lib.php';
require './libraries/Keywords.lib.php';
require './libraries/Buffer.lib.php';

// Webpage settings
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('topic_medium');
else
   WebpageHandler::addCSS('topic');
WebpageHandler::addJS('uploads'); // Custom thumbnail creation enabled
WebpageHandler::addJS('keywords');
WebpageHandler::addJS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::changeContainer('topicContent');

// Various dialogs
$dialogs = '';
$thumbnailDialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($thumbnailDialogTpl))
   $dialogs .= $thumbnailDialogTpl;
if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
   $dialogs .= $fileUploadDialogTpl;
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

// Uploads list (uploads are always activated when creating a topic)
$uploadsList = Buffer::listContent();
$nbUploads = count($uploadsList[0]);

$currentThumbnailPath = Buffer::getThumbnail();
$currentThumbnail = 'none';
if(strlen($currentThumbnailPath) == 0)
   $currentThumbnailPath = './defaultthumbnail.jpg';
else
   $currentThumbnail = './'.substr($currentThumbnailPath, strlen(PathHandler::HTTP_PATH()));

// Array which serves both for template input and collecting $_POST values
$formData = array('previewPseudo' => LoggedUser::$data['used_pseudo'],
'previewRank' => LoggedUser::rank(),
'errors' => '',
'title' => '',
'thumbnailPath' => $currentThumbnailPath,
'content' => '',
'uploadOptions' => '',
'anonChecked' => '',
'uploadsChecked' => 'checked',
'keywordsList' => '',
'keywords' => '',
'thumbnail' => $currentThumbnail,
'uploadMessage' => 'newUpload',
'uploadsView' => Buffer::render($uploadsList));

// Initial keywords provided through the URL
if(!empty($_GET['tags']))
{
   $roughTags = Utils::secure(urldecode($_GET['tags']));
   $tags = explode('|', $roughTags);
   
   // Length of the keywords is checked to ensure their length is conform
   $finalTags = array();
   for($i = 0; $i < count($tags); $i++)
   {
      $len = strlen($tags[$i]);
      if($len <= 50 && $len > 0)
         array_push($finalTags, $tags[$i]);
   }
   
   if(count($finalTags) > 0)
   {
      $formData['keywords'] = implode('|', $finalTags);
      $formData['keywordsList'] = Keywords::display($finalTags);
   }
}

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
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['content'] = Utils::secure($_POST['message']);
   $formData['thumbnail'] = Utils::secure($_POST['thumbnail']);
   $formData['keywords'] = Utils::secure($_POST['keywords']);
   
   $keywordsArr = explode('|', $formData['keywords']);
   $formData['keywordsList'] = Keywords::display($keywordsArr);
   
   // Checkboxes for the general configuration of the thread
   $anonPosting = false;
   if(isset($_POST['anon_posting']))
   {
      $anonPosting = true;
      $formData['anonChecked'] = 'checked';
   }
   
   $enableUploads = false;
   if(isset($_POST['enable_uploads']))
   {
      $enableUploads = true;
      $formData['uploadsChecked'] = 'checked';
   }
   else
      $formData['uploadsChecked'] = '';
   
   $thumbnail = 'none'; // Value that will be input in Topic::insert()
   if($formData['thumbnail'] !== 'none' && file_exists(PathHandler::WWW_PATH().substr($formData['thumbnail'], 2)))
   {
      $formData['thumbnailPath'] = $formData['thumbnail'];
      $thumbnail = 'CUSTOM';
   }
   
   // Gets the delay between current time and the lattest topic created by this user
   $delay = 3600;
   try
   {
      $delay = Topic::getUserDelay();
   }
   catch(Exception $e)
   {
      // Nothing, so far
   }
   
   // Errors (missing title/content and/or title too long)
   if(strlen($formData['title']) == 0 OR strlen($formData['content']) == 0)
      $formData['errors'] .= 'emptyFields|';
   else if(strlen($formData['title']) > 125)
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
      try
      {
         $parsedContent = Emoticon::parseEmoticonsShortcuts($formData['content']);
         $parsedContent = FormParsing::parse($parsedContent);
      
         $newTopic = Topic::insert($formData['title'], 
                                   $thumbnail, 
                                   1, 
                                   $anonPosting, 
                                   $enableUploads);
         
         $newPost = Post::insert($newTopic->get('id_topic'), $parsedContent);
         $newTopic->update($newPost->getAll());
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewTopic.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet', $dialogs);
      }
      
      // Saves the thumbnail (up to now, the script only checked its existence)
      if($thumbnail === 'CUSTOM')
      {
         $fileName = substr(strrchr($formData['thumbnail'], '/'), 1);
         Buffer::save('upload/topics/'.$newTopic->get('id_topic'), $fileName, 'thumbnail');
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
      
      // Redirection
      $newTopicURL = PathHandler::topicURL($newTopic->getAll());
      header('Location:'.$newTopicURL);
      
      // Success page
      $tplInput = array('target' => $newTopicURL);
      $successPage = TemplateEngine::parse('view/content/NewTopic.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Créer un nouveau sujet');
   }
   else
   {
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewTopic.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewTopic.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Créer un nouveau sujet', $dialogs);
}
   
?>
