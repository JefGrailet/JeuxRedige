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
if(!LoggedUser::isLoggedIn() || !Utils::check(LoggedUser::$fullData['advanced_features']))
{
   http_response_code(401);
   $errorKey = !LoggedUser::isLoggedIn() ? "notConnected" : "forbiddenAccess";

   echo $twig->render("error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => $errorKey,
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "description" => "Erreur",
         "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
      ]
   ]);

   die();
}

// Webpage settings
WebpageHandler::addCSS('article_edition');
WebpageHandler::addJS('article_editor');
WebpageHandler::addJS('keywords');
WebpageHandler::changeContainer('blockSequence');

// Thumbnail creation dialog (re-used; no need for a specific dialog for article thumbnails)
// $dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
$dialogs = '';


$currentThumbnail = Buffer::getArticleThumbnail();
$currentThumbnailValue = '';
if(is_null($currentThumbnail))
   $currentThumbnail = './default_article_thumbnail.jpg';
else
   $currentThumbnailValue = './'.substr($currentThumbnail, strlen(PathHandler::HTTP_PATH()));


// Input only (distinct from above, as items in select fields are not present)
$formInput = [
   'title' => '',
   'subtitle' => '',
   'type' => '',
   'keywords' => '',
];

$MAX_INPUT_CHARS = 100;

$curlUpload = function ($file) use ($twig) {
   $useragent = $_SERVER['HTTP_USER_AGENT'];
   $strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';

   session_write_close();

   $curl = curl_init();
   curl_setopt($curl, CURLOPT_URL, $twig->getGlobals()["webRoot"] . '/ajax/CreateArticleThumbnail.php?mode=no_path');
   curl_setopt($curl, CURLOPT_VERBOSE, 1);

   curl_setopt($curl, CURLOPT_POST, true);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

   curl_setopt(
      $curl,
      CURLOPT_POSTFIELDS,
      [
         'image' => new CurlFile(
            $file["tmp_name"],
            $file["type"],
            $file["name"],
         ),
      ]);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($curl,CURLOPT_USERAGENT, $useragent);
   curl_setopt($curl, CURLOPT_COOKIE, $strCookie);

   $result = curl_exec($curl);
   curl_close($curl);

   return $result;
};

// Form treatment starts here
if(!empty($_POST))
{
   $listInputsKey = array_keys($formInput);
   // Keywords
   $listKeywords = $_POST['keywords'];

   $fullyCompleted = true;
   for($i = 0; $i < count($listInputsKey); $i++)
   {
      $formInput[$listInputsKey[$i]] = is_string($_POST[$listInputsKey[$i]]) ? Utils::secure($_POST[$listInputsKey[$i]]) : $_POST[$listInputsKey[$i]];
      if($formInput[$listInputsKey[$i]] === '' && $listInputsKey[$i] !== 'keywords')
         $fullyCompleted = false;
   }

   $curlResult = $curlUpload($_FILES["thumbnail"]);

   // die();

   // if(substr($formInput['thumbnail'], 0, strlen(PathHandler::HTTP_PATH())) === PathHandler::HTTP_PATH())
   //    $formInput['thumbnail'] = substr($formInput['thumbnail'], strlen(PathHandler::HTTP_PATH()));
   // else if(substr($formInput['thumbnail'], 0, 2) === './')
   //    $formInput['thumbnail'] = substr($formInput['thumbnail'], 2);

   // Various errors (empty fields, etc.)
   if (!$fullyCompleted)
      array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
   if (!in_array($formInput['type'], array_keys(Utils::ARTICLES_CATEGORIES)))
      array_push($formErrorMessagesTriggered, $formErrorMessages["type"]["unknown"]);
   if (strlen($formInput['title']) > $MAX_INPUT_CHARS)
      array_push($formErrorMessagesTriggered, $formErrorMessages["title"]["tooLong"]);
   if (strlen($formInput['subtitle']) > $MAX_INPUT_CHARS)
      array_push($formErrorMessagesTriggered, $formErrorMessages["subtitle"]["tooLong"]);
   if (is_array($listKeywords) || (count($listKeywords) == 1 && strlen($listKeywords[0]) == 0))
      array_push($formErrorMessagesTriggered, $formErrorMessages["keywords"]["empty"]);
   if (in_array($curlResult, array_keys($formErrorMessages["thumbnail"])))
      array_push($formErrorMessagesTriggered, $formErrorMessages["thumbnail"][$curlResult]);

   if (count($formErrorMessagesTriggered) == 0)
   {
      // Finally inserts the article (new error display in case of DB problem)
      $newArticle = null;
      try
      {
         $newArticle = Article::insert(
            $formInput['title'],
            $formInput['subtitle'],
            $formInput['type']
         );
         // Saves the thumbnail
         $fileName = substr(strrchr($curlResult, '/'), 1);
         Buffer::save('upload/articles/'.$newArticle->get('id_article'), $fileName, 'thumbnail');

         // Inserts keywords; we move to the next if an exception occurs while mapping the keywords
         for ($i = 0; $i < count($listKeywords) && $i < 10; $i++)
         {
            if(strlen($listKeywords[$i]) == 0)
               continue;

            try
            {
               $tag = new Tag($listKeywords[$i]);
               $tag->mapToArticle($newArticle->get('id_article'));
            }
            catch(Exception $e)
            {
               continue;
            }
         }

         $newArticleURL = './EditArticle.php?id_article='.$newArticle->get('id_article');
         setcookie("flash_message", "article_created", time() + 1);
         exit(header('Location:'.$newArticleURL));
      }
      catch(Exception $e)
      {
         array_push($formErrorMessagesTriggered, $formErrorMessages["dbError"]);
         // $formComp['errors'] = 'dbError';
         // $formComp['thumbnailPath'] = PathHandler::HTTP_PATH().$formInput['thumbnail'];
         // $formComp['thumbnail'] = $formInput['thumbnail'];
         // $formComp['title'] = $formInput['title'];
         // $formComp['subtitle'] = $formInput['subtitle'];
         // $formComp['type'] = $formInput['type'].'||'.$typeChoices;
         // $formComp['keywords'] = $formInput['keywords'];
         // $formComp['keywordsList'] = Keywords::display($keywordsArr);

         // $formTpl = TemplateEngine::parse('view/content/NewArticle.form.ctpl', $formComp);
         // WebpageHandler::wrap($formTpl, 'Ajouter un jeu dans la base de données', $dialogs);
      }

      // URL of the edition page of the new article + redirection to it


      // Success page
      // $tplInput = array('title' => $newArticle->get('title'), 'target' => $newArticleURL);
      // $successPage = TemplateEngine::parse('view/user/NewArticle.success.ctpl', $tplInput);
      // WebpageHandler::resetDisplay();
      // WebpageHandler::wrap($successPage, 'Créer un nouvel article');
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

echo $twig->render("add_edit_article.html.twig", [
   "page_title" => "Créer un nouvel article",
   "type" => "add",
   "list_css_files" => [ "select2.min", "input_file"],
   "list_js_files" => [["file" => "form_validation"], "upload", "select2.min", "select2.fr.min", "keywords_v2", "dynamic_article_button_label"],
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

