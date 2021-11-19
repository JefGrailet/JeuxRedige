<?php

/**
* "About" page; simply displays a small text about the website. The inclusion of the header 
* library activates all default features for a user.
*/

require './libraries/Header.lib.php';

WebpageHandler::addCSS('about');
WebpageHandler::noContainer();

WebpageHandler::wrap(TemplateEngine::parse('view/content/About.ctpl'), 'À propos');

?>
