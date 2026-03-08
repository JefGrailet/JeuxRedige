<?php

/**
 * Script to display server errors
 */

require './libraries/Header.lib.php';

require_once './libraries/core/Twig.config.php';

if (http_response_code() === 404) {
   echo $twig->render("errors/server_error.html.twig", [
      "response_code" => 404,
   ]);
} else {
   echo $twig->render("errors/server_error.html.twig", [
      "response_code" => http_response_code(),
   ]);
}
