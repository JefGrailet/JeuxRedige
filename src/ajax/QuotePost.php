<?php

/**
* Script to retrieve the unparsed content of some post for quotation, provided its ID.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/FormParsing.lib.php';
require '../model/Post.class.php';
require '../view/intermediate/QuoteMessage.ir.php';

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $postID = Utils::secure($_GET['id_post']);
   
   $post = null;
   try
   {
      $post = new Post($postID);
   }
   catch(Exception $e)
   {
      if($e->getMessage() !== 'Pin does not exist.')
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
   }
   
   $postArr = $post->getAll();
   $postArr['content'] = FormParsing::unparse($postArr['content']);
   $res = QuoteMessageIR::process($postArr);
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $res;
}

?>
