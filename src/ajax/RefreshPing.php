<?php

/**
* Script to get all pongs of a private discussion after a certain index. It is used in the context 
* of automatic refresh.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

require '../model/Ping.class.php';
require '../model/PingPong.class.php';
require '../model/User.class.php';
require '../libraries/MessageParsing.lib.php';
require '../view/intermediate/Pong.ir.php';

if(!empty($_GET['id_ping']) && preg_match('#^([0-9]+)$#', $_GET['id_ping']) && 
   !empty($_GET['offset']) && preg_match('#^([0-9]+)$#', $_GET['offset']) &&
   !empty($_GET['per_page']) && preg_match('#^([0-9]+)$#', $_GET['per_page']))
{
   $pingID = intval(Utils::secure($_GET['id_ping']));
   $offset = intval(Utils::secure($_GET['offset']));
   $perPage = intval(Utils::secure($_GET['per_page']));

   $resStr = '';
   try
   {
      $discussion = new PingPong($pingID);
      $pongs = $discussion->getPongs($offset, 5000);
      $nbNewPongs = count($pongs);
      
      /*
      * Note: 5000 looks silly, but this is actually recommended by MySQL doc (with an even 
      * ridiculously large number): https://dev.mysql.com/doc/refman/5.6/en/select.html
      */

      // Checks online status of pseudonyms in "author" field of the retrieved posts
      $online = null;
      try
      {
         $online = User::checkOnlineStatus(array($discussion->get('emitter'), $discussion->get('receiver')));
      }
      catch(Exception $e) {}
      
      // Formats the posts
      $fullInput = array();
      for($i = 0; $i < count($pongs); $i++)
      {
         if($online != null && in_array($pongs[$i]['author'], $online))
            $pongs[$i]['online'] = true;
         $pongs[$i]['message'] = MessageParsing::parse($pongs[$i]['message'], ($offset + $i + 1));
         
         $pongIR = PongIR::process($pongs[$i], ($offset + $i + 1));
         array_push($fullInput, $pongIR);
      }
      $fullInput = Utils::removeSeconds($fullInput);
      
      $pagingNeeded = false;
      $postCount = ($offset % $perPage);
      if($postCount == 0 || ($postCount + $nbNewPongs) > $perPage)
      {
         $resStr .= "New pages\n";
         $pagingNeeded = true;
      }
      
      // Renders the posts
      $pongsTpl = TemplateEngine::parseMultiple('view/user/Pong.ctpl', $fullInput);
      if(!TemplateEngine::hasFailed($pongsTpl))
      {
         $curPage = ceil($offset / $perPage);
         if($offset % $perPage == 0)
            $curPage++;
         if($pagingNeeded)
            $resStr .= "<div class=\"page\" data-page=\"".$curPage."\">\n";
         
         for($i = 0; $i < count($pongsTpl); $i++)
         {
            $resStr .= $pongsTpl[$i];
            $postCount++;
            
            if($postCount == $perPage && $pagingNeeded)
            {
               $resStr .= "</div>\n";
               if($i < (count($pongsTpl) - 1))
               {
                  $curPage++;
                  $resStr .= "<div class=\"page\" data-page=\"".$curPage."\">\n";
               }
               $postCount = 0;
            }
         }
         
         if($pagingNeeded)
            $resStr .= "</div>\n";
      }
      else
         $resStr = 'Template error';
   }
   catch(Exception $e)
   {
      if($e->getMessage() === 'No message has been found.')
         $resStr = 'No message';
      else
         $resStr = 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}
else
{
   header('Content-Type: text/html; charset=UTF-8');
   echo 'Bad arguments';
}

?>
