<?php

/**
 * This script displays the published articles and last messages of some user. It also provides
 * some general information.
 */

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/User.class.php';
require './model/rendering/ArticleRendering.class.php';
require './view/intermediate/Post.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Retrieves user's data if possible; stops and displays appropriate error message otherwise
$user = null;
$userListArticles = null;

$getUserString = Utils::secure($_GET['user']);
$isCurrentUser = false;

try {
   $user = new User($getUserString);

   if (LoggedUser::isLoggedIn()) {
      $currentUser = new User(LoggedUser::$fullData);
      $isCurrentUser = $currentUser->get("pseudo") === $user->get("pseudo");
   }
} catch (Exception $e) {
   // TODO
}

$userListArticlesComputed = array();
try
{
   $userListArticles = $user->getArticles();
   $userListArticlesComputed = array_map(function ($article) {
      return array(
         ...$article,
         "link" => PathHandler::articleURL($article),
         "date_time" => Utils::timeToString($article["date_publication"]),
         "thumbnail" => ArticleRendering::getThumbnail($article["id_article"]),
      );
   }, $userListArticles);
} catch (Exception $e) {
   if (!str_contains($e->getMessage(), "No article has been found"))
   {
      echo $twig->render("errors/error.html.twig", [
         "error_key" => "dbError",
      ]);
      die();
   }
}

$userComputed = [
   ...$user->getAll(),
   // "last_connection" => Utils::toTimestamp($user->get('last_connection')),
   // "registration_date" => Utils::toTimestamp($user->get('registration_date')),
   "avatar" => PathHandler::getAvatar($user->get('pseudo')),
   'banned' => (Utils::toTimestamp($user->get('last_ban_expiration')) > Utils::SQLServerTime()),
   "list_articles" => $userListArticlesComputed,
];

$userListPosts = [];

try {
   $userListPosts = $user->getPosts(0, 5, true);

   $userListPostsComputed = array_map(function ($post) {
      $content = MessageParsing::parse($post['content']);
      $content =  MessageParsing::removeReferences($content);

      return array(
         ...$post,
         "content" => $content,
      );
   }, $userListPosts, array_keys($userListPosts));
} catch (Exception $e) {
}

// print_r($userListPosts);


echo $twig->render("user-profile.html.twig", [
   "list_css_files" => ["user_profile", "topic"],
   "list_js_files" => ["topic_interaction"],
   "page_title" => "A propos de {$userComputed["pseudo"]}",
   "user" => $userComputed,
   "is_current_user" => $isCurrentUser,
   "selectedLogo" => $twig->getGlobals()["current_category"],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "À propos - JeuxRédige",
      "description" => "JeuxRédige est, comme son nom l'indique, un site web qui a été conçu comme un support d'écriture pour parler de jeu vidéo de manière générale. Vous pourrez y trouver des critiques, des chroniques ou même des guides sur les jeux vidéo récents comme anciens, avec à l'occasion quelques billets d'humeur, toujours en rapport avec la culture des jeux.",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);
