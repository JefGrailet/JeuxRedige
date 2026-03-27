<?php

/*
* Script to display a full article, either published online (full access for everyone) or still 
* being written (access only with direct URL).
*/

require './libraries/Header.lib.php';
require './libraries/SegmentParsing.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';
require './model/rendering/ArticleRendering.class.php';

require_once getenv("DOCUMENT_ROOT") . '/libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Obtains game title and retrieves the corresponding entry
if (!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article'])) {
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   try {
      $article = new Article($articleID);
      $article->loadRelatedData();
      if ($article->isPublished()) {
         $article->getTopic();
         $article->incViews();
      }
   } catch (Exception $e) {
      echo $twig->render("errors/error.html.twig", [
         "error_title" => "Article non trouvé",
         "error_key" => "nonexistingArticle",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Article vide",
            "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
            "full_title" => "",
         ]
      ]);
      die();
   }

   // Redirects to right URL if $_GET['title'] does not match
   if (!empty($_GET['title'])) {
      $titleURL = Utils::secure($_GET['title']);
      $toFormat = $article->get('title') . ' ' . $article->get('subtitle');
      if (PathHandler::formatForURL($toFormat) !== $titleURL)
         header('Location:' . PathHandler::articleURL($article->getAll()));
      WebpageHandler::usingURLRewriting();
   }

   // No segment
   $segments = $article->getBufferedSegments() ?? [];

   $canCurrentUserEdit = false;
   if (LoggedUser::isLoggedIn()) {
      if ($article->get('pseudo') === LoggedUser::$data['pseudo'])
         $canCurrentUserEdit = true;
      else
         $canCurrentUserEdit = Utils::check(LoggedUser::$data['can_edit_all_posts']);
   }

   if (count($segments) == 0) {
      $editLink = "";
      if($canCurrentUserEdit)
      {
         $editLink = PathHandler::HTTP_PATH() . 'EditArticle.php?id_article=';
         $editLink .= $article->get('id_article');
      }
      echo $twig->render("errors/error.html.twig", [
         "error_title" => "Article vide",
         "error_key" => "noSegment",
         "error_title" => "Impossible d'afficher l'article",
         "edit_link" => $editLink,
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur : Article vide",
            "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
            "full_title" => "",
         ]
      ]);
      die();
   }

   // Restricted view
   if (!$article->isPublished()) {
      if ((!LoggedUser::isLoggedIn())) // || ($article->get('pseudo') !== LoggedUser::$data['pseudo'] && !Utils::check(LoggedUser::$data['can_edit_all_posts'])))
      {
         echo $twig->render("errors/error.html.twig", [
            "error_title" => "Erreur : Article vide",
            "error_key" => "restrictedAccess",
            "error_title" => "Article en accès restreint",
            "meta" => [
               ...$twig->getGlobals()["meta"],
               "title" => "Erreur : Article vide",
               "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
               "full_title" => "",
            ]
         ]);
         die();
      }
   }

   // Pre-selected segment
   $selectedSegment = 1;
   $pageSelected = 0;
   if (!empty($_GET['section'])) {
      $pageSelected = intval($_GET['section']) - 1;
      $getSection = intval(Utils::secure($_GET['section']));
      if ($getSection > 0 && $getSection <= count($segments))
         $selectedSegment = $getSection;
      else
         header('Location:' . PathHandler::articleURL($article->getAll()));
   }

   // Renders segments
   $pagesRendered = array();

   for ($i = 0; $i < count($segments); $i++) {
      array_push($pagesRendered, [
         'ID' => $segments[$i]['id_segment'], 
         'title' => $segments[$i]['title'], 
         'content' => Utils::cleanUp(SegmentParsing::parse($segments[$i]['content'], $i + 1)), 
         'header_URL' => ArticleRendering::getSegmentHeader(
            $article->get('id_article'), 
            $segments[$i]['id_segment']
         )
      ]);
   }

   // Fixes subtitle on first segment
   if ($segments[0]['title'] == NULL) {
      $pagesRendered[0]['title'] = $article->get('subtitle');
   }

   // Display
   $articleType = $twig->getGlobals()["list_categories"][$article->get('type')]["name"]["singular"];
   $title = "{$article->get('title')} ({$articleType})";
   if (count($segments) > 1) {
      if ($pageSelected > 0) {
         $truePageNumber = $pageSelected + 1;
         $title .= " - Page {$truePageNumber}";
      }
      else
         $title .= " - Sommaire";
   }

   $listPagesComputed = array_map(function ($page, $index) use ($article, $pageSelected) {
      $url = PathHandler::articleURL($article->getAll());
      $pageIndex = $index + 1;

      return array(
         ...$page,
         "url" => "{$url}page/{$pageIndex}",
         "is_active" => ($pageSelected + 1) === $pageIndex,
      );
   }, $pagesRendered, array_keys($pagesRendered));

   $editLinks = [];
   if($canCurrentUserEdit)
   {
      $idSegment = $pagesRendered[$pageSelected]["ID"];
      $idArticle = $article->get('id_article');
      $editLinks = [
         "page" => [
            "link" => PathHandler::HTTP_PATH() . 'EditSegment.php?id_segment='.$idSegment,
            "label" => "Éditer la page"
         ], 
         "article" => [
            "link" => PathHandler::HTTP_PATH() . 'EditArticle.php?id_article='.$idArticle,
            "label" => "Éditer l'article"
         ]
      ];
   }

   $prefixSearchLink = PathHandler::HTTP_PATH() . 'SearchArticles.php?keywords[]=';
   $listKeywords = array_map(function ($keyword) use ($prefixSearchLink) {
      $tag = trim($keyword["tag"]);
      return [
         "name" => $tag,
         "link" => $prefixSearchLink . urlencode($keyword["tag"]),
      ];
   }, $article->getKeywords());

   $lastUpdateTime = "";
   if($article->get('date_last_modifications') > $article->get('date_creation'))
      $lastUpdateTime = $article->get('date_last_modifications');

   echo $twig->render("article.html.twig", [
      "page_title" => $title,
      "current_category" => $article->get('type'),
      "is_article" => true,
      "article" => [
         "id" => $article->get('id_article'),
         "title" => $article->get('title'),
         "page" => [
            ...$pagesRendered[$pageSelected]
         ],
         "current_page" => $pageSelected,
         "segments" => $listPagesComputed,
         "published_time" => $article->get('date_creation'),
         "is_published" => $article->isPublished(),
         "last_update_time" => $lastUpdateTime,
         "edit_links" => $editLinks,
         "is_editable" => $canCurrentUserEdit,
         "list_keywords" => $listKeywords,
         "author" => [
            "pseudo" => $article->get('pseudo'),
            "avatar" => PathHandler::getAvatar($article->get('pseudo')),
         ],
      ],
      "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
      "list_css_files" => ["article", 'charter_' . $article->get('type'), "badge"],
      "list_js_files" => ["article", "article_v2", ["file" => "article_segment"],],
      "selectedLogo" => $article->get('type'),
      "current_category" => $article->get('type'),
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => $title,
         "description" => $article->get('subtitle'),
         "image" => $article->getThumbnail(),
         "full_title" => "",
         "author" => $article->get('pseudo'),
         "published_time" => $article->get('date_creation'),
         "tags" => Utils::ARTICLES_CATEGORIES[$article->get('type')][2],
      ]
   ]);
} else {
   echo $twig->render("article_fail.html.twig", [
      "page_title" => "Article vide",
      "error_key" => "missingID",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur - Article vide",
         "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
         "full_title" => "",
      ]
   ]);
}
