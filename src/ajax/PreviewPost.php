<?php

/**
* Small script called through AJAX in order to preview a message using format code.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/FormParsing.lib.php';
require '../libraries/MessageParsing.lib.php';
require '../model/Emoticon.class.php';

if(LoggedUser::isLoggedIn() && isset($_POST['message']) && !empty($_POST['author']) && !empty($_POST['rank']))
{
   $author = Utils::secure($_POST['author']);
   $tplInput = array('title' => 'Aperçu du nouveau message',
   'avatar' => PathHandler::getAvatar($author),
   'rank' => Utils::secure($_POST['rank']),
   'author' => $author,
   'content' => '');
   
   $parsedContent = Utils::secure($_POST['message']);
   try
   {
      $parsedContent = Emoticon::parseEmoticonsShortcuts($parsedContent);
   }
   catch(Exception $e)
   {
   }
   $parsedContent = MessageParsing::removeReferences(MessageParsing::parse(FormParsing::parse($parsedContent)));
   
   $tplInput['content'] = $parsedContent;
   
   $tpl = TemplateEngine::parse('view/content/PreviewPost.ctpl', $tplInput);
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $tpl;
}

?>
