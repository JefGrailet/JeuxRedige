<?php

/**
* This script displays the published articles and last messages of some user. It also provides
* some general information.
*/

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/User.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';
require './view/intermediate/Post.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Retrieves user's data if possible; stops and displays appropriate error message otherwise
$user = null;


// Prepares the list of sentences for that user

$getUserString = Utils::secure($_GET['user']);

try
{
   $user = new User($getUserString);
}
catch(Exception $e)
{
}

$userListArticles = $user->getArticles();

$userListArticlesComputed = array_map(function ($article)  {
   return array(
      ...$article,
      "link" => ArticleThumbnailIR::getLink($article),
      "date_time" => ArticleThumbnailIR::getDateTime($article),
      "thumbnail" => ArticleThumbnailIR::getThumbnail($article),
   );
}, $userListArticles);

$userComputed = [
   ...$user->getAll(),
   "avatar" => PathHandler::getAvatar($user->get('pseudo')),
   "list_articles" => $userListArticlesComputed,
];


echo $twig->render("user-profile.html.twig", [
   "list_css_files" => ["user_profile", "topic"],
   "list_js_files" => ["topic_interaction"],
   "page_title" => "A propos de {$userComputed["pseudo"]}",
   "user" => $userComputed,
   "selectedLogo" => $twig->getGlobals()["current_category"],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "À propos - JeuxRédige",
      "description" => "JeuxRédige est, comme son nom l'indique, un site web qui a été conçu comme un support d'écriture pour parler de jeu vidéo de manière générale. Vous pourrez y trouver des critiques, des chroniques ou même des guides sur les jeux vidéo récents comme anciens, avec à l'occasion quelques billets d'humeur, toujours en rapport avec la culture des jeux.",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);
