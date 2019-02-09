<?php

/**
* This script deletes a archived ping of this user which the ID is given by $_POST. The 
* "challenge" of this script is not to delete the ping itself but to provide the right data such 
* that the display is accurately refreshed at the user's side (right amount of pages and such).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Ping.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_POST['id_ping']) && preg_match('#^([0-9]+)$#', $_POST['id_ping']))
{
   $pingToDelete = Utils::secure($_POST['id_ping']);

   $actualizedPings = 'None';
   $newPageBlock = 'No change';
   try
   {
      $ping = new Ping($pingToDelete);
      $amounts = $ping->countPingsBeforeAfter();
      $currentPage = floor($amounts[0] / WebpageHandler::$miscParams['posts_per_page']) + 1;
      $oldNbPages = ceil(($amounts[0] + $amounts[1] + 1) / WebpageHandler::$miscParams['posts_per_page']);
      $newNbPages = ceil(($amounts[0] + $amounts[1]) / WebpageHandler::$miscParams['posts_per_page']);
      $ping->deletePing();
      
      // Page amount changed
      if($oldNbPages != $newNbPages)
      {
         // There were pings after the deleted ping, therefore there is only one ping to load
         if($amounts[1] > 0)
         {
            $lastPingOnPage = Ping::getPings(($currentPage * WebpageHandler::$miscParams['posts_per_page']) - 1, 1);
            if($lastPingOnPage != NULL)
            {
               require '../view/intermediate/Ping.ir.php';
               
               $pingToAddArr = $lastPingOnPage[0];
               $pingToAddIR = PingIR::process($pingToAddArr);
               $actualizedPings = TemplateEngine::parse('view/user/Ping.ctpl', $pingToAddIR);
               if(TemplateEngine::hasFailed($actualizedPings))
                  $actualizedPings = 'Error';
            }
         }
         // Otherwise, this means the last ping on the page was deleted: a whole page is loaded (if there were pings before the deleted one)
         else if($amounts[0] > 0)
         {
            $currentPage--;
            
            $newPings = Ping::getPings((($currentPage - 1) * WebpageHandler::$miscParams['posts_per_page']), 
                                       WebpageHandler::$miscParams['posts_per_page']);
            
            if($newPings != NULL)
            {
               require '../view/intermediate/Ping.ir.php';
               
               // Format pings
               $formattedNewPings = array();
               for($i = 0; $i < count($newPings); $i++)
               {
                  $pingIR = PingIR::process($newPings[$i]);
                  array_push($formattedNewPings, $pingIR);
               }
               
               $pingsTpl = TemplateEngine::parseMultiple('view/user/Ping.ctpl', $formattedNewPings);
               if(!TemplateEngine::hasFailed($pingsTpl))
               {
                  $actualizedPings = '';
                  for($i = 0; $i < count($pingsTpl); $i++)
                     $actualizedPings .= $pingsTpl[$i];
               }
               else
                  $actualizedPings = 'Error';
            }
         }
         
         // Updated page amount
         if($newNbPages > 1)
         {
            $pagesTplInput = array('pageConfig' => '');
            $pagesTplInput['pageConfig'] .= WebpageHandler::$miscParams['posts_per_page'].'|'.($amounts[0] + $amounts[1]).'|'.$currentPage;
            $pagesTplInput['pageConfig'] .= '|./Pings.php?page=[]';
            $newPageBlock = TemplateEngine::parse('view/user/Pings.pages.ctpl', $pagesTplInput);
            if(TemplateEngine::hasFailed($newPageBlock))
               $newPageBlock = 'Error';
         }
         else
            $newPageBlock = 'Emptied';
      }
      // Page amount did not change: only one ping should be loaded.
      else
      {
         $lastPingOnPage = Ping::getPings(($currentPage * WebpageHandler::$miscParams['posts_per_page']) - 1, 1);
         if($lastPingOnPage != NULL)
         {
            require '../view/intermediate/Ping.ir.php';
            
            $pingToAddArr = $lastPingOnPage[0];
            $pingToAddIR = PingIR::process($pingToAddArr);
            $actualizedPings = TemplateEngine::parse('view/user/Ping.ctpl', $pingToAddIR);
            if(TemplateEngine::hasFailed($actualizedPings))
               $actualizedPings = 'Error';
         }
      }
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo $e->getMessage();
      exit();
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo 'OK'."\n\n";
   echo $newPageBlock."\n\n";
   echo $actualizedPings;
}
else
   exit();

?>
