<?php

require_once getenv("DOCUMENT_ROOT") . '/vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(getenv("DOCUMENT_ROOT") . '/views');

$twig = new \Twig\Environment($loader, [
   'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());
$twig->getExtension(\Twig\Extension\CoreExtension::class)->setTimezone('Europe/Paris');
$twig->addGlobal("webRoot", substr(PathHandler::HTTP_PATH(), 0, -1));
$twig->addGlobal("extJS", PathHandler::JS_EXTENSION());
$twig->addGlobal("JSFiles", "");
$twig->addGlobal("autoJS", "");
$twig->addGlobal("list_categories", [
   "review" => [
      "name" => [
         "singular" => "Critique",
         "plural" => "Critiques",
      ],
      "color" => "#cd301e",
      "is_female" => true,
      "empty_message" => "aucune critique",
   ],
   "preview" => [
      "name" => [
         "singular" => "Aperçu",
         "plural" => "Aperçus",
      ],
      "color" => "#34b1e5",
      "is_female" => false,
      "empty_message" => "aucun aperçu",
   ],
   "opinion" => [
      "name" => [
         "singular" => "Humeur",
         "plural" => "Humeurs",
      ],
      "color" => "#91c148",
      "is_female" => false,
      "empty_message" => "aucune humeur",
   ],
   "chronicle" => [
      "name" => [
         "singular" => "Chronique",
         "plural" => "Chroniques",
      ],
      "color" => "#99368b",
      "is_female" => true,
      "empty_message" => "aucune chronique",
   ],
   "guide" => [
      "name" => [
         "singular" => "Guide",
         "plural" => "Guides",
      ],
      "color" => "#dd9302",
      "is_female" => false,
      "empty_message" => "aucun guide",
   ],
   "misc" => [
      "name" => [
         "singular" => "Hors-jeu",
         "plural" => "Hors-jeu",
      ],
      "color" => "#969696",
      "is_female" => false,
      "empty_message" => "aucun hors-jeu",
   ],
]);
$twig->addGlobal("meta", [
   "title" => "JeuxRédige",
   "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
   "image" => $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
   "url" => $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
   "full_title" => "",
]);
$twig->addGlobal("page_title", "Site de critiques de jeux vidéo");
$twig->addGlobal("userSide", "default");
$twig->addGlobal("logo_chargement", PathHandler::HTTP_PATH() . "logos/JeuxRedige_chargement.png");
$twig->addGlobal("no_custom_logo", false);
$twig->addGlobal("is_user_logged", LoggedUser::isLoggedIn());
if (LoggedUser::isLoggedIn()) {
   $twig->addGlobal("userInfos", array(
      "avatar" => PathHandler::getAvatarMedium(LoggedUser::$data['used_pseudo']),
      "pseudo" => LoggedUser::$data['pseudo'],
      "is_admin" => LoggedUser::$data['function_pseudo'] !== NULL && strlen(LoggedUser::$data['function_pseudo']) > 0 && LoggedUser::$data['function_name'] !== 'alumnus',
      "is_using_admin_account" => LoggedUser::$data['function_pseudo'] === LoggedUser::$data['used_pseudo'],
      "alt_pseudo" => LoggedUser::$data['function_pseudo'],
      "is_admin" => LoggedUser::$data['function_name'] === 'administrator',
   ));
}
$twig->addGlobal("renderTime", number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 5, '.', ""));
parse_str($_SERVER["QUERY_STRING"], $queryString);
$twig->addGlobal("query_string", $queryString);
$twig->addGlobal("current_category", $twig->getGlobals()["query_string"]["article_category"] ?? "default");
$twig->addGlobal("selectedLogo", empty($twig->getGlobals()["current_category"]) ? "default" : $twig->getGlobals()["current_category"]);
$twig->addGlobal("base_js_files", ["toggle_input_visibility", ["file" => "form_validation"]]);
$twig->addGlobal("errors_message", [
   "article" => [
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
         "empty" => "Vous devez entrer un titre",
      ],
      "subtitle" => [
         "tooLong" => "Le sous-titre ne peut pas excéder 100 caractères, veuillez le réduire",
         "empty" => "Vous devez entrer un sous-titre",
      ],
      "type" => [
         "unknown" => "Le type d'article choisi est invalide. Choisissez un des types proposés",
         "empty" => "Vous devez choisir un type d'article",
      ],
      "keywords" => [
         "empty" => "Vous devez préciser au moins un mot-clef",
         "limitReached" => "Vous ne pouvez pas mettre plus de 10 mots-clefs",
      ],
      "emptyFields" => "Vous devez remplir tous les champs",
      "dbError" => "Une erreur inconnue est survenue lors de la mise à jour. Contactez l'administrateur ou réessayez plus tard"
   ],
   "user_account" => [
         "avatar" => [
            "tooBig" => "La taille de l'image uploadée ne peut excéder un mégaoctet. Veuillez réduire l'image ou utiliser une autre",
            "notJPEG" => "Pour générer un avatar, vous devez utiliser une image au format JPEG/JPG",
            "tooSmall" => "Vous devez sélectionner une image",
            "resizeError" => "Une erreur est survenue lors de la génération de l'avatar. Veuillez réessayer plus tard ou prévenir l'administrateur",
            "uploadError" => "Le téléchargement de l'image a échoué. Réessayez plus tard ou contactez l'administrateur",
            "notEnoughSpace" => "Nous sommes dans l'incapacité de télécharger l'intégralité de votre image pour le moment. Veuillez réessayer plus tard ou prévenez l'administrateur",
         ],
         "preferences" => [
            "incorrectInput" => "Les valeurs entrées ne sont pas valides. Veuillez les modifier conformément à ce que le formulaire stipule",
         ],
         "email" => [
            "wrongCurrentPwd" => "Le mot de passe que vous avez entré est incorrect",
            "emailTooLong" => "La nouvelle adresse est anormalement longue (maximum 60 caractères)",
            "alreadyUsed" => "Vous utilisez déjà l'adresse que vous venez d'entrer",
            "usedBySomeoneElse" => "Cette nouvelle adresse est déjà utilisée pour un autre compte",
         ],
         "emptyFields" => "Vous devez remplir tous les champs",
         "dbError" => "Une erreur inconnue est survenue lors de la mise à jour. Contactez l'administrateur ou réessayez plus tard",
   ],
   "page" => [
      "header" => [
         "badDimensions" => "L'image d'en-tête doit avoir une largeur supérieure ou égale à 1920 pixels, avec une hauteur d'au moins 30% la largeur",
         "invalidFormat" => "L'image d'en-tête doit être au format .jp(e)g",
         "uploadError" => "Le téléchargement de l'image a échoué. Réessayez plus tard ou contactez l'administrateur",
         "notEnoughSpace" => "Nous sommes dans l'incapacité de télécharger l'intégralité de votre image pour le moment. Veuillez réessayer plus tard ou prévenez l'administrateur",
         "tooBig" => "La taille de l'image uploadée ne peut excéder un mégaoctet. Veuillez réduire l'image ou utiliser une autre",
      ],
      "content" => [
         "empty" => "Vous devez mettre du contenu pour votre page",
      ],
      "title" => [
         "empty" => "Cette page, n'étant pas la première page de votre article, vous devez mettre un titre",
      ],
   ],
   "emptyFields" => "Vous devez remplir tous les champs",
]);

$filter = new \Twig\TwigFilter('since_days', function ($charset) {
   $today = new DateTime(date('Y-m-d'));
   $startDate = new DateTime($charset);

   $diffDays = $today->diff($startDate)->days;

   return number_format($diffDays, 0, ',', ' ');
});

$twig->addFilter($filter);
