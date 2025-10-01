<?php

/**
 * Script to display server errors
 */

require './libraries/Header.lib.php';

require_once './libraries/core/Twig.config.php';

if (http_response_code() === 404) {
   echo $twig->render("errors/404.html.twig");
} else {
   echo $twig->render("errors/error.html.twig", [
      "response_code" => http_response_code(),
   ]);
}
