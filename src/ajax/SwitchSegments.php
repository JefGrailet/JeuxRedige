<?php

/**
* Small script called through AJAX to switch the order between two segments of an article. The 
* operation is allowed if and only if both segments belong to the same article and the currently 
* logged user is the author.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

require '../model/Article.class.php';
require '../model/Segment.class.php';
require '../view/intermediate/SegmentListItem.ir.php';

if(!empty($_POST['id_article']) && preg_match('#^([0-9]+)$#', $_POST['id_article']) && 
   !empty($_POST['id_segment1']) && preg_match('#^([0-9]+)$#', $_POST['id_segment1']) && 
   !empty($_POST['id_segment2']) && preg_match('#^([0-9]+)$#', $_POST['id_segment2']))
{
   $articleID = intval(Utils::secure($_POST['id_article']));
   $segment1 = intval(Utils::secure($_POST['id_segment1']));
   $segment2 = intval(Utils::secure($_POST['id_segment2']));
   
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
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
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
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
      
      // Replies starts with "OK" then new segment lines
      $IR1 = SegmentListItemIR::process($seg2->getAll(), false);
      $IR2 = SegmentListItemIR::process($seg1->getAll(), false);
      
      if($seg2->get('position') == count($segments))
         $IR1['moveDown'] = '';
      else if($seg1->get('position') == count($segments))
         $IR2['moveDown'] = '';
      
      $newTpl1 = TemplateEngine::parse('view/user/SegmentListItem.item.ctpl', $IR1);
      $newTpl2 = TemplateEngine::parse('view/user/SegmentListItem.item.ctpl', $IR2);
      
      header('Content-Type: text/html; charset=UTF-8');
      echo "OK\n".$newTpl1."\nSplit\n".$newTpl2;
   }
   else
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Missing segments';
   }
}

?>