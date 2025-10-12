<?php

/**
* Small script called through AJAX to switch the order between two segments of an article. The
* operation is allowed if and only if both segments belong to the same article and the currently
* logged user is the author.
*/

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   http_response_code(405);
   echo json_encode(["error" => "Method Not Allowed"]);
   exit();
}

require '../libraries/Header.lib.php';

if(!LoggedUser::isLoggedIn())
{
   http_response_code(401);
   echo json_encode(["error" => "Vous devez être connecté(e)"]);
   exit();
}

require '../model/Article.class.php';
require '../model/Segment.class.php';
require '../view/intermediate/SegmentListItem.ir.php';

$requestPayload = json_decode(file_get_contents('php://input'), true);

if(!empty($requestPayload['id_article']) && preg_match('#^([0-9]+)$#', $requestPayload['id_article']) &&
   !empty($requestPayload['id_segment1']) && preg_match('#^([0-9]+)$#', $requestPayload['id_segment1']) &&
   !empty($requestPayload['id_segment2']) && preg_match('#^([0-9]+)$#', $requestPayload['id_segment2']))
{
   $articleID = intval(Utils::secure($requestPayload['id_article']));
   $segment1 = intval(Utils::secure($requestPayload['id_segment1']));
   $segment2 = intval(Utils::secure($requestPayload['id_segment2']));

   $article = null;
   $segments = null;
   $segment1Arr = null;
   $segment2Arr = null;

   try
   {
      $article = new Article($articleID);
      $segments = $article->getSegments();


      for($i = 0; $i < count($segments); $i++)
      {
         if($segments[$i]['id_segment'] == $segment1)
            $segment1Arr = $segments[$i];
         else if($segments[$i]['id_segment'] == $segment2)
            $segment2Arr = $segments[$i];
      }
   }
   catch(Exception $e)
   {
      echo json_encode(["error" => "dbError"]);
      die();
   }

   if($segment1Arr != null && $segment2Arr != null && !$article->isPublished() && $article->isMine())
   {
      $seg1 = new Segment($segment1Arr);
      $seg2 = new Segment($segment2Arr);

      try
      {
         Database::beginTransaction();

         $tmp = intval($seg1->get('position'));
         $seg1->changePosition(intval($seg2->get('position')));
         $seg2->changePosition($tmp);

         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         echo json_encode(["error" => "dbError"]);
         die();
      }

      echo json_encode(["success" => ""]);
      die();
   }
   else
   {
      echo json_encode(["error" => "Missing data"]);
      die();
   }
}

echo json_encode(["error" => "Unknown"]);

