<?php

/**
* Script to get a set of pongs from a private discussion, provided its ID, an offset and an 
* amount of pongs to retrieve.
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
   isset($_GET['offset']) && preg_match('#^([0-9]+)$#', $_GET['offset']) && 
   !empty($_GET['amount']) && preg_match('#^([0-9]+)$#', $_GET['amount']))
{
   $pingID = intval(Utils::secure($_GET['id_ping']));
   $offset = intval(Utils::secure($_GET['offset']));
   $amount = intval(Utils::secure($_GET['amount']));

   $resStr = '';
   try
   {
      $discussion = new PingPong($pingID);
      $pongs = $discussion->getPongs($offset, $amount);
      
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
      
      // Renders the posts
      $pongsTpl = TemplateEngine::parseMultiple('view/user/Pong.ctpl', $fullInput);
      if(!TemplateEngine::hasFailed($pongsTpl))
      {
         for($i = 0; $i < count($pongsTpl); $i++)
            $resStr .= $pongsTpl[$i];
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
