<?php

/**
* Small script called through AJAX in order to preview a segment after parsing format code.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/FormParsing.lib.php';
require '../libraries/SegmentParsing.lib.php';

if(LoggedUser::isLoggedIn() && isset($_POST['message']))
{   
   $parsedContent = SegmentParsing::parse(FormParsing::parse(Utils::secure($_POST['message'])));
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $parsedContent;
}

?>
