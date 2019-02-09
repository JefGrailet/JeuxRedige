<?php

/**
* Script to retrieve the top 5 tropes associated to a game which the title is provided as input. 
* Nothing is sent back if the game is missing or has no associated trope at all.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Game.class.php';

if(isset($_POST['game']) && !empty($_POST['game']))
{
   $needle = Utils::secure($_POST['game']);
   $needle = str_replace('|', '', $needle); // Security, same for next line
   $needle = str_replace('"', '', $needle);
   
   if($needle === '')
   {
      exit();
   }
   
   try
   {
      $game = new Game($needle);
      $tropes = $game->getTropes();
      
      if(count($tropes) == 0)
      {
         exit();
      }
      
      $res = '';
      for($i = 0; $i < count($tropes); $i++)
      {
         if($i > 0)
            $res .= '|';
         $res .= $tropes[$i]['tag'];
      }
      
      header('Content-Type: text/html; charset=UTF-8');
      echo $res;
   }
   catch(Exception $e)
   {
      exit();
   }
}

?>
