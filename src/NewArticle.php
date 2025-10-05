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

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

$thumbnailMaxSize = 1048576;
$thumbnailRequirements = [
   "mimeTypes" => ["image/jpeg", "image/jpg"],
   "maxSize" => $thumbnailMaxSize,
];

$formErrorMessages = [
   "thumbnail" => [
      "tooBig" => "La taille de l'image uploadée ne peut excéder un mégaoctet. Veuillez réduire l'image ou utiliser une autre",
      "invalidFormat" => "Pour générer une image d'en-tête, vous devez utiliser une image au format .jp(e)g",
      "tooSmall" => "Vous devez sélectionner une image",
      "resizeError" => "Une erreur est survenue lors de la génération de l'avatar. Veuillez réessayer plus tard ou prévenir l'administrateur",
      "uploadError" => "Le téléchargement de l'image a échoué. Réessayez plus tard ou contactez l'administrateur",
      "notEnoughSpace" => "Nous sommes dans l'incapacité de télécharger l'intégralité de votre image pour le moment. Veuillez réessayer plus tard ou prévenez l'administrateur",
   ],
   "title" => [
      "tooLong" => "Le titre ne peut pas excéder 100 caractères, veuillez le réduire",
   ],
   "subtitle" => [
      "tooLong" => "Le sous-titre ne peut pas excéder 100 caractères, veuillez le réduire",
   ],
   "type" => [
      "unknown" => "Le type d'article choisi est invalide. Choisissez un des types proposés",
   ],
   "keywords" => [
      "empty" => "Vous devez préciser au moins un mot-clef",
      "limitReached" => "Vous ne pouvez pas mettre plus de 10 mots-clefs",
   ],
   "emptyFields" => "Vous devez remplir tous les champs",
   "dbError" => "Une erreur inconnue est survenue lors de la mise à jour. Contactez l'administrateur ou réessayez plus tard"
];

$formErrorMessagesTriggered = [];

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
// $dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$dialogs = '';
// if(!TemplateEngine::hasFailed($dialogTpl))
//    $dialogs = $dialogTpl;

$typeChoices = Utils::makeCategoryChoice(); // Types of articles formatted for <select>

$currentThumbnail = Buffer::getArticleThumbnail();
$currentThumbnailValue = '';
if(is_null($currentThumbnail))
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
if(!empty($_POST))
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
      array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
      // $formComp['errors'] .= 'emptyFields|';
   if(!in_array($formInput['type'], array_keys(Utils::ARTICLES_CATEGORIES)))
      array_push($formErrorMessagesTriggered, $formErrorMessages["type"]["unknown"]);
   if(strlen($formInput['title']) > 100)
      array_push($formErrorMessagesTriggered, $formErrorMessages["title"]["tooLong"]);
   if(strlen($formInput['subtitle']) > 100)
      array_push($formErrorMessagesTriggered, $formErrorMessages["subtitle"]["tooLong"]);
   // if($formInput['thumbnail'] === './default_article_thumbnail.jpg' || !file_exists(PathHandler::WWW_PATH().$formInput['thumbnail']))
   //    $formComp['errors'] .= 'invalidThumbnail|';
   if(count($keywordsArr) == 1 && strlen($keywordsArr[0]) == 0)
      array_push($formErrorMessagesTriggered, $formErrorMessages["keywords"]["empty"]);

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


echo $twig->render("new_article.html.twig", [
   "page_title" => "Créer un nouvel article",
   "list_css_files" => [ "select2.min", "input_file"],
   "list_js_files" => [["file" => "form_validation"], "upload", "select2.min", "select2.fr.min", "search_articles", "dynamic_article_button_label"],
   "form_error_messages" => $formErrorMessages,
   "form_error_messages_triggered" => $formErrorMessagesTriggered,
   "thumbnail_requirements" => [
      ...$thumbnailRequirements,
      "mimeTypes" => join(",", $thumbnailRequirements["mimeTypes"])
   ],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Créer un nouvel article",
      "description" => "Créer un nouvel article",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);


?>
