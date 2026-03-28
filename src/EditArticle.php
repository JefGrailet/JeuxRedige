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

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   echo $twig->render("errors/error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => "notConnected",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "description" => "Erreur",
         "full_title" => "",
      ]
   ]);

   die();
}

$thumbnailMaxSize = 1048576;
$thumbnailRequirements = [
   "mimeTypes" => ["image/jpeg", "image/jpg"],
   "maxSize" => $thumbnailMaxSize,
];

$curlUpload = function ($file, $pageName) use ($twig) {
   $useragent = $_SERVER['HTTP_USER_AGENT'];
   $strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';

   session_write_close();

   $curl = curl_init();

   curl_setopt($curl, CURLOPT_URL, $twig->getGlobals()["webRoot"] . "/ajax/$pageName.php");
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
   curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
   curl_setopt($curl, CURLOPT_COOKIE, $strCookie);

   $result = curl_exec($curl);

   return $result;
};

function coreDataProcess($payload) {
   $curlUpload = $payload["curlUpload"];
   $article = $payload["article"];
   $formErrorMessages = $payload["errorMessages"];
   $keywords = $payload["keywords"];

   $listErrorsTriggered = [];

   $formInput = [
      'title' => '',
      'subtitle' => '',
      'type' => '',
      'keywords' => '',
   ];
   $listInputsKey = array_keys($formInput);

   $isFormValid = true;
   for($i = 0; $i < count($listInputsKey); $i++)
   {
      $formInput[$listInputsKey[$i]] = is_string($_POST[$listInputsKey[$i]]) ? Utils::secure($_POST[$listInputsKey[$i]]) : $_POST[$listInputsKey[$i]];
      if($formInput[$listInputsKey[$i]] === '' && $listInputsKey[$i] !== 'keywords')
         $fullyCompleted = false;
   }

   $curlResult = null;
   if ($_FILES["thumbnail"]["size"] > 0) {
      $curlResult = $curlUpload($_FILES["thumbnail"], "CreateArticleThumbnail");
   }

   $MAX_INPUT_CHARS = 100;
   $newKeywords = $_POST['keywords'];

   if (!$isFormValid)
      array_push($listErrorsTriggered, $formErrorMessages["emptyFields"]);
   if (!in_array($formInput['type'], array_keys(Utils::ARTICLES_CATEGORIES)))
      array_push($listErrorsTriggered, $formErrorMessages["type"]["unknown"]);
   if (strlen($formInput['title']) > $MAX_INPUT_CHARS)
      array_push($listErrorsTriggered, $formErrorMessages["title"]["tooLong"]);
   if (strlen($formInput['subtitle']) > $MAX_INPUT_CHARS)
      array_push($listErrorsTriggered, $formErrorMessages["subtitle"]["tooLong"]);
   if (!is_array($newKeywords) || (count($newKeywords) == 1 && strlen($newKeywords[0]) == 0))
      array_push($listErrorsTriggered, $formErrorMessages["keywords"]["empty"]);
   if (in_array($curlResult, array_keys($formErrorMessages["thumbnail"])))
      array_push($listErrorsTriggered, $formErrorMessages["thumbnail"][$curlResult]);

   if (count($listErrorsTriggered) == 0)
   {
      try
      {
         $article->update($formInput['title'], $formInput['subtitle'], $formInput['type']);

         if ($curlResult) {
            $fileName = substr(strrchr($curlResult, '/'), 1);
            $dir = 'upload/articles/' . $article->get('id_article');
            if (!file_exists($dir)) {
               mkdir($dir, 0777, true);
            }
            Buffer::save($dir, $fileName, 'thumbnail');
         }
      }
      catch(Exception $e)
      {
         setcookie("flash_message", "article_error", time() + 1, "/");
         $currentURL = './EditArticle.php?id_article=' . $article->get('id_article');
         header('Location:' . $currentURL);
         exit;
      }

      $nbCommonKeywords = sizeof(Keywords::common($keywords, $newKeywords));
      $keywordsToDelete = Keywords::distinct($keywords, $newKeywords);
      $keywordsToAdd = Keywords::distinct($newKeywords, $keywords);

      try
      {
         Tag::unmapArticle($article->get('id_article'), $keywordsToDelete);
      } catch(Exception $e) {}

      for ($j = 0; $j < count($keywordsToAdd) && $j < (10 - $nbCommonKeywords); $j++) {
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

      Tag::cleanOrphanTags();
   }

   return $listErrorsTriggered;
}

function highlightDataProcess($payload) {
   $curlUpload = $payload["curlUpload"];
   $article = $payload["article"];
   $formErrorMessages = $payload["errorMessages"];

   $listErrorsTriggered = [];

   $curlResult = null;

   if ($_FILES["highlight"]["size"] > 0) {
      $curlResult = $curlUpload($_FILES["highlight"], "CreateArticleHighlight");
   }

   if (in_array($curlResult, array_keys($formErrorMessages["thumbnail"])))
      array_push($listErrorsTriggered, $formErrorMessages["thumbnail"][$curlResult]);

   if (count($listErrorsTriggered) == 0)
   {
      try {
         $article->setIsHighlighted($_POST["featured"]);
         // Updates the highlight picture if edited
         if($curlResult)
         {
            $fileName = substr(strrchr($curlResult, '/'), 1);
            Buffer::save('upload/articles/'.$article->get('id_article'), $fileName, 'highlight');
         }

      } catch (\Throwable $th) {
      }
   }

   return $listErrorsTriggered;
}

$formErrorMessagesTriggered = [];

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   $keywords = [];
   $segments = [];
   try
   {
      $article = new Article($articleID);
      $article->loadRelatedData();
      $keywords = $article->getKeywordsSimple();
      $segments = $article->getBufferedSegments() ?? [];
   }
   catch(Exception $e)
   {
      $errorKey = 'dbError';
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $errorKey = 'nonexistingArticle';
      http_response_code(404);
      echo $twig->render("errors/error.html.twig", [
         "page_title" => "Erreur",
         "error_key" => $errorKey,
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur",
            "description" => "Erreur",
            "full_title" => "",
         ]
      ]);

      die();
   }

   // Forbidden access if the user's neither the author, neither an admin
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      http_response_code(401);
      echo $twig->render("errors/error.html.twig", [
         "page_title" => "Erreur",
         "error_key" => "notYours",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur",
            "description" => "Erreur",
            "full_title" => "",
         ]
      ]);

      die();
   }

   // Thumbnail
   $currentThumbnail = Buffer::getArticleThumbnail();
   if(file_exists(PathHandler::WWW_PATH().'upload/articles/'.$articleID.'/thumbnail.jpg'))
      $articleThumbnail = './upload/articles/'.$articleID.'/thumbnail.jpg';
   else if(strlen($currentThumbnail || "") > 0)
      $articleThumbnail = './'.substr($currentThumbnail, strlen(PathHandler::HTTP_PATH()));
   else
      $articleThumbnail = './default_article_thumbnail.jpg';

   $currentHighlight = Buffer::getHighlight();
   if(file_exists(PathHandler::WWW_PATH().'upload/articles/'.$articleID.'/highlight.jpg'))
      $articleHighlight = './upload/articles/'.$articleID.'/highlight.jpg';
   else if(strlen($currentHighlight || "") > 0)
      $articleHighlight = './'.substr($currentHighlight, strlen(PathHandler::HTTP_PATH()));
   else
      $articleHighlight = './default_article_highlight.jpg';


   $formErrorMessages = $twig->getGlobals()["error_messages"]["article"];

   if (!empty($_POST))
   {
      $payload = [
         "article" => $article,
         "errorMessages" => $formErrorMessages,
         "curlUpload" => $curlUpload,
      ];

      if ($_POST['form_name'] === 'core')
      {
         $payload["keywords"] = $keywords;
         $formErrorMessagesTriggered = coreDataProcess($payload);

         if (count($formErrorMessagesTriggered) === 0) {
            setcookie("flash_message", "article_updated", time() + 1, "/");
         }
      } else if ($_POST['form_name'] === 'highlight')
      {
         $formErrorMessagesTriggered = highlightDataProcess($payload);
         if (count($formErrorMessagesTriggered) === 0) {
            $key = $_POST['featured'] === "yes" ? "article_highlighted" : "article_not_highlighted";
            setcookie("flash_message", $key, time() + 1, "/");
         }
      }

      if (count($formErrorMessagesTriggered) === 0)
      {
         $currentURL = './EditArticle.php?id_article=' . $article->get('id_article');

         header('Location: ' . $currentURL, true, 302);
         exit;
      }
   }

   echo $twig->render("add_edit_article.html.twig", [
      "page_title" => "Éditer \"{$article->get("title")}\"",
      "type" => "edit",
      "article" => [
         ...$article->getAll(),
         "is_published" => $article->isPublished(),
         "keywords" => $keywords,
         "thumbnail" => $articleThumbnail,
         "highlight" => $articleHighlight,
         "preview_url" => PathHandler::articleURL($article->getAll()),
         "segments" => $segments,
      ],
      "user_can_highlight" => Utils::check(LoggedUser::$data['can_edit_all_posts']),
      "list_css_files" => [ "select2.min", "input_file", "badge", "article_edition", "drag_and_drop_upload"],
      "list_js_files" => [
         ["file" => "form_validation"],
         "upload",
         "libs/select2.min",
         "libs/select2.fr.min",
         "keywords_v2",
         "dynamic_article_button_label",
         "libs/sortable.min",
         "sortable_list",
         "drag_n_drop_upload",
         "paste_clipboard_media",
         ["file" => "modals_page"],
      ],
      "form_error_messages" => $formErrorMessages,
      "form_error_messages_triggered" => $formErrorMessagesTriggered,
      "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
      "thumbnail_requirements" => [
         ...$thumbnailRequirements,
         "mimeTypes" => join(",", $thumbnailRequirements["mimeTypes"])
      ],
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Éditer \"{$article->get("title")}\"",
         "description" => "Éditer \"{$article->get("title")}\"",
         "full_title" => "",
      ]
   ]);
   die();
}

echo $twig->render("errors/error.html.twig", [
   "page_title" => "Erreur",
   "error_key" => "missingID",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Erreur",
      "description" => "Erreur",
      "full_title" => "",
   ]
]);

