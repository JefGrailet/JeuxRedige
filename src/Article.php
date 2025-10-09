<?php

/*
* Script to display a full article, whether it has been published online (full access for
* everyone) or it is still being written (in this case, only the author can view it).
*/

require './libraries/Header.lib.php';
require './libraries/SegmentParsing.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';
require './view/intermediate/Article.ir.php';
require './view/intermediate/Segment.ir.php';
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Obtains game title and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   try
   {
      $article = new Article($articleID);
      $article->loadRelatedData();
      if($article->isPublished())
      {
         $article->getTopic();
         $article->incViews();
      }
   }
   catch(Exception $e)
   {
      echo $twig->render("error.html.twig", [
         "error_title" => "Article non trouvé",
         "error_key" => "nonexistingArticle",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Article vide",
            "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
            "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
      ]);
      die();
   }

   // Redirects to right URL if $_GET['title'] does not match

   if(!empty($_GET['title']))
   {
      $titleURL = Utils::secure($_GET['title']);
      if(PathHandler::formatForURL($article->get('title').' '.$article->get('subtitle')) !== $titleURL)
         header('Location:'.PathHandler::articleURL($article->getAll()));

      WebpageHandler::usingURLRewriting();
   }

   // No segment
   $segments = $article->getBufferedSegments() ?? [];

   $isAuthorIsCurrentUser = false;
   if (LoggedUser::isLoggedIn()) {
      $isAuthorIsCurrentUser = $twig->getGlobals()["userInfos"]["pseudo"] === $article->get('pseudo');
   }

   if(count($segments) == 0)
   {
      echo $twig->render("error.html.twig", [
         "error_title" => "Article vide",
         "error_key" => "noSegment",
         "error_title" => "Impossible d'afficher l'article",
         "edit_link" => $isAuthorIsCurrentUser ? ArticleThumbnailIR::getLink($article->getAll(), true) : "",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur : Article vide",
            "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
            "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
      ]);
      die();
   }

   // Restricted view
   if(!$article->isPublished())
   {
      if((!LoggedUser::isLoggedIn())) // || ($article->get('pseudo') !== LoggedUser::$data['pseudo'] && !Utils::check(LoggedUser::$data['can_edit_all_posts'])))
      {
         echo $twig->render("error.html.twig", [
            "error_title" => "Erreur : Article vide",
            "error_key" => "restrictedAccess",
            "error_title" => "Article en accès restreint",
            "meta" => [
               ...$twig->getGlobals()["meta"],
               "title" => "Erreur : Article vide",
               "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
               "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
               "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
               "full_title" => "",
            ]
         ]);
         die();
      }
   }

   // Pre-selected segment
   $selectedSegment = 1;
   $pageSelected = 0;
   if(!empty($_GET['section']))
   {
      $pageSelected = intval($_GET['section']) - 1;
      $getSection = intval(Utils::secure($_GET['section']));
      if($getSection > 0 && $getSection <= count($segments))
         $selectedSegment = $getSection;
      else
         header('Location:'.PathHandler::articleURL($article->getAll()));
   }

   // Generates all useful data for article display
   $articleIR = ArticleIR::process($article, $selectedSegment);

   // Renders segments
   $fullInput = array();

   for($i = 0; $i < count($segments); $i++)
   {
      $segments[$i]['content'] = SegmentParsing::parse($segments[$i]['content'], $i + 1);
      $segmentIR = SegmentIR::process($segments[$i], (($i + 1) == $selectedSegment));
      array_push($fullInput, $segmentIR);
   }

   // Fixes title/subtitle on first segment
   if($segments[0]['title'] == NULL)
   {
      $fullInput[0]['title'] = $article->get('title');
      $fullInput[0]['mainSubtitle'] = 'yes||'.$article->get('subtitle');
   }

   // print_r($article->getAll());
   $segmentsTpl = TemplateEngine::parseMultiple('view/content/Segment.ctpl', $fullInput);
   $segmentsStr = '';
   if(!TemplateEngine::hasFailed($segmentsTpl))
   {
      for($i = 0; $i < count($segmentsTpl); $i++)
         $segmentsStr .= $segmentsTpl[$i];
   }
   else
      WebpageHandler::wrap($segmentsTpl, 'Une erreur est survenue lors de la lecture des pages');

   // Display
   $articleType = $twig->getGlobals()["list_categories"][$article->get('type')]["name"]["singular"];
   $title = "{$article->get('title')} ({$articleType})";
   if (count($segments) > 1) {
      $title .= " - Page {$pageSelected}";
   }

   $listPagesComputed = array_map(function ($page, $index) use ($article, $pageSelected)  {
      $url = PathHandler::articleURL($article->getAll());
      $pageIndex = $index + 1;

      return array(
         ...$page,
         "url" => "{$url}page/{$pageIndex}",
         "is_active" => ($pageSelected + 1) === $pageIndex,
      );
   }, $fullInput, array_keys($fullInput));

   $currentThumbnail = Buffer::getArticleThumbnail();
   // print_r(file_exists(PathHandler::HTTP_PATH().'upload/articles/'.$article->get('id_article').'/'. $article->getBufferedSegments()[0]["id_segment"] .'/header.jpg') ? "true" : "false");
   // print_r(PathHandler::HTTP_PATH().'upload/articles/'.$article->get('id_article').'/'. $article->getBufferedSegments()[0]["id_segment"] .'/header.jpg');

   $isAuthorIsCurrentUser = false;
   if (LoggedUser::isLoggedIn()) {
      $isAuthorIsCurrentUser = $twig->getGlobals()["userInfos"]["pseudo"] === $article->get('pseudo');
   }

   echo $twig->render("article.html.twig", [
      "page_title" => $title,
      "current_category" => $article->get('type'),
      "is_article" => true,
      "article" => [
         "id" => $article->get('id_article'),
         // "header_img" => $currentThumbnail,
         "header_img" => PathHandler::HTTP_PATH().'upload/articles/'.$article->get('id_article').'/'. $article->getBufferedSegments()[0]["id_segment"] .'/thumbnail.jpg',
         "title" => $fullInput[0]['title'],
         "subtitle" => $article->get('subtitle'),
         "content" => $fullInput[$pageSelected]['content'],
         "current_page" => $pageSelected,
         "segments" => $listPagesComputed,
         "published_time" => $article->get('date_creation'),
         "is_published" => $article->isPublished(),
         "last_update_time" => $article->get('date_last_modifications') > $article->get('date_creation') ? $article->get('date_last_modifications') : "",
         "edit_link" => $isAuthorIsCurrentUser ? ArticleThumbnailIR::getLink($article->getAll(), true) : "",
         "is_my_article" => $isAuthorIsCurrentUser,
         "author" => [
            "pseudo" => $article->get('pseudo'),
            "avatar" => PathHandler::getAvatar($article->get('pseudo')),
         ],
      ],
      "list_css_files" => ["article", 'charter_'.$article->get('type'), "badge"],
      "list_js_files" => ["article", "article_content_margin"],
      "selectedLogo" => $article->get('type'),
      "articleColor" => $twig->getGlobals()["list_categories"][$article->get('type')]["color"],
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => $title,
         "description" => $article->get('subtitle'),
         "image" => $article->getThumbnail(),
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
         "author" => $article->get('pseudo'),
         "published_time" => $article->get('date_creation'),
         "tags" => Utils::ARTICLES_CATEGORIES[$article->get('type')][2],
      ]
   ]);
}
else
{
   echo $twig->render("article_fail.html.twig", [
         "page_title" => "Article vide",
         "error_key" => "missingID",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Article vide",
            "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
            "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
   ]);
}
?>
