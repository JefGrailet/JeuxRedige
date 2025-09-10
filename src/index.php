<?php

/**
 * Home page.
 */

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './model/Topic.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';
require './view/intermediate/TopicThumbnail.ir.php';

require_once './vendor/autoload.php';

WebpageHandler::redirectionAtLoggingIn();

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

$loader = new \Twig\Loader\FilesystemLoader('./views');

$twig = new \Twig\Environment($loader, [
   'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

$function = new \Twig\TwigFunction('cancel', function ($msg = "Une erreur est survenue") {
    throw new \Twig\Error\Error("Process cancelled with msg: $msg");
});
$twig->addFunction($function);

$twig->addGlobal("webRoot", PathHandler::HTTP_PATH());
$twig->addGlobal("configJS", "");
$twig->addGlobal("extJS", PathHandler::JS_EXTENSION());
$twig->addGlobal("JSFiles", "");
$twig->addGlobal("autoJS", "");
$twig->addGlobal("articles_categories", [
   "review" => [
      "name" => "Critique",
   ],
   "preview" => [
      "name" => "Aperçu",
   ],
   "opinion" => [
      "name" => "Humeur",
   ],
   "chronicle" => [
      "name" => "Chronique",
   ],
   "guide" => [
      "name" => "Guide",
   ],
   "misc" => [
      "name" => "Hors-Jeu",
   ],
]);
$twig->addGlobal("meta", [
   "title" => "JeuxRédige",
   "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
   "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
   "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
   "full_title" => "",
]);
$twig->addGlobal("dialogs", "");
$twig->addGlobal("selectedLogo", "default");
$twig->addGlobal("userSide", "default");
$twig->addGlobal("is_user_logged", LoggedUser::isLoggedIn());
if (LoggedUser::isLoggedIn()) {
   $twig->addGlobal("userInfos", array(
      'avatar' => PathHandler::getAvatarMedium(LoggedUser::$data['used_pseudo']),
      'pseudo' => LoggedUser::$data['pseudo'],
   ));
}
$twig->addGlobal("renderTime", number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 5, '.', ""));

// Gets the last featured articles
$articles = null;
try {
   $articles = Article::getFeaturedArticles(8);
} catch (Exception $e) {
   $errorTplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/content/Index.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre le contenu');
}

if ($articles == NULL) {
   $errorTplInput = array('error' => 'noContent');
   $tpl = TemplateEngine::parse('view/content/Index.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre le contenu');
}


$NB_MAX_ARTICLES_HIGHLIGHTED = 2;

$listArticlesComputed = array_map(function ($article, $idx) use ($NB_MAX_ARTICLES_HIGHLIGHTED) {
   return array(
      ...$article,
      "is_highlighted" => $idx < $NB_MAX_ARTICLES_HIGHLIGHTED,
      "link" => ArticleThumbnailIR::getLink($article),
      "date_time" => ArticleThumbnailIR::getDateTime($article),
      "thumbnail" => ArticleThumbnailIR::getThumbnail($article),
   );
}, $articles, array_keys($articles));

$listCSSFiles = ["pool"];

echo $twig->render("index.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => ["pool"],
   "meta" => [
      "title" => "JeuxRédige",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);
