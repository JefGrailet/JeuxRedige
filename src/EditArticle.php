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
   echo $twig->render("error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => "notConnected",
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

$thumbnailMaxSize = 1048576;
$thumbnailRequirements = [
   "mimeTypes" => ["image/jpeg", "image/jpg"],
   "maxSize" => $thumbnailMaxSize,
];

$curlUpload = function ($file) use ($twig) {
   $useragent = $_SERVER['HTTP_USER_AGENT'];
   $strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';

   session_write_close();

   $curl = curl_init();
   curl_setopt($curl, CURLOPT_URL, $twig->getGlobals()["webRoot"] . '/ajax/CreateArticleThumbnail.php');
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

   // Forbidden access if the user's neither the author, neither an admin
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      http_response_code(401);
      echo $twig->render("error.html.twig", [
         "page_title" => "Erreur",
         "error_key" => "notYours",
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

   // Thumbnail
   $currentThumbnail = Buffer::getArticleThumbnail();
   if(file_exists(PathHandler::WWW_PATH().'upload/articles/'.$articleID.'/thumbnail.jpg'))
      $articleThumbnail = './upload/articles/'.$articleID.'/thumbnail.jpg';
   else if(strlen($currentThumbnail || "") > 0)
      $articleThumbnail = './'.substr($currentThumbnail, strlen(PathHandler::HTTP_PATH()));
   else
      $articleThumbnail = './default_article_thumbnail.jpg';

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

   $formErrorMessages = $twig->getGlobals()["errors_message"]["article"];
   if(!empty($_POST)) {
      $formErrorMessagesTriggered = [];

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
         $curlResult = $curlUpload($_FILES["thumbnail"]);
      }

      $MAX_INPUT_CHARS = 100;
      $newKeywords = $_POST['keywords'];

      if (!$isFormValid)
         array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
      if (!in_array($formInput['type'], array_keys(Utils::ARTICLES_CATEGORIES)))
         array_push($formErrorMessagesTriggered, $formErrorMessages["type"]["unknown"]);
      if (strlen($formInput['title']) > $MAX_INPUT_CHARS)
         array_push($formErrorMessagesTriggered, $formErrorMessages["title"]["tooLong"]);
      if (strlen($formInput['subtitle']) > $MAX_INPUT_CHARS)
         array_push($formErrorMessagesTriggered, $formErrorMessages["subtitle"]["tooLong"]);
      if (!is_array($newKeywords) || (count($newKeywords) == 1 && strlen($newKeywords[0]) == 0))
         array_push($formErrorMessagesTriggered, $formErrorMessages["keywords"]["empty"]);
      if (in_array($curlResult, array_keys($formErrorMessages["thumbnail"])))
         array_push($formErrorMessagesTriggered, $formErrorMessages["thumbnail"][$curlResult]);

      if (count($formErrorMessagesTriggered) == 0)
      {
         try
         {
            $article->update($formInput['title'], $formInput['subtitle'], $formInput['type']);
         }
         catch(Exception $e)
         {
            setcookie("flash_message", "article_error", time() + 1, "/");
            $currentURL = './EditArticle.php?id_article=' . $article->get('id_article');
            exit(header('Location:' . $currentURL));
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
         $currentURL = './EditArticle.php?id_article=' . $article->get('id_article');
         setcookie("flash_message", "article_updated", time() + 1, "/");
         exit(header('Location:' . $currentURL));
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
         "preview_url" => PathHandler::articleURL($article->getAll()),
         "segments" => $segments,
      ],
      "list_css_files" => [ "select2.min", "input_file", "badge", "article_edition"],
      "list_js_files" => [
         ["file" => "form_validation"],
         "upload",
         "select2.min",
         "select2.fr.min",
         "keywords_v2",
         "dynamic_article_button_label",
         "sortable.min",
         "sortable_list",
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
         "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
      ]
   ]);
   die();

   // else if(!empty($_POST['highlightThis']))
   // {
   //    $picture = Utils::secure($_POST['highlight']);

   //    if($picture !== $highlightFormInput['highlight'] && !file_exists(PathHandler::WWW_PATH().substr($picture, 2)))
   //       $highlightFormInput['errors'] = 'invalidHighlight';

   //    if(strlen($highlightFormInput['errors']) == 0)
   //    {
   //       if((isset($_POST['featured']) && !Utils::check($article->get('featured'))) || (!isset($_POST['featured']) && Utils::check($article->get('featured'))))
   //       {
   //          try
   //          {
   //             $res = $article->feature();
   //             if($res)
   //                $highlightFormInput['featured'] = 'checked';
   //             else
   //                $highlightFormInput['featured'] = '';
   //          }
   //          catch(Exception $e)
   //          {
   //             $highlightFormInput['errors'] = 'dbError';
   //             $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
   //             $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
   //             WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
   //          }
   //       }

   //       // Updates the highlight picture if edited
   //       if($highlightFormInput['highlight'] !== $picture || (strlen($article->getHighlight()) == 0 && $picture !== './default_article_highlight.jpg'))
   //       {
   //          $fileName = substr(strrchr($picture, '/'), 1);
   //          $highlightFormInput['highlight'] = './upload/articles/'.$article->get('id_article').'/highlight.jpg';
   //          Buffer::save('upload/articles/'.$article->get('id_article'), $fileName, 'highlight');
   //       }

   //       $highlightFormInput['success'] = 'yes';
   //       $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
   //       $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
   //       $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
   //       WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
   //    }
   //    else
   //    {
   //       $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
   //       $finalTplInput['highlighting'] = TemplateEngine::parse('view/user/HighlightArticle.form.ctpl', $highlightFormInput);
   //       $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
   //       WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
   //    }
   // }
   // else
   // {
   //    $finalTplInput['editionForm'] = TemplateEngine::parse('view/user/EditArticle.form.ctpl', $formComp);
   //    $finalTpl = TemplateEngine::parse('view/user/EditArticle.composite.ctpl', $finalTplInput);
   //    WebpageHandler::wrap($finalTpl, 'Editer l\'article "'.$article->get('title').'"', $dialogs);
   // }
}

echo $twig->render("error.html.twig", [
   "page_title" => "Erreur",
   "error_key" => "missingID",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Erreur",
      "description" => "Erreur",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

