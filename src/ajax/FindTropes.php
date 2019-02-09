<?php

/**
* Script to search for tropes containing a given input string the user wrote in some input text 
* field. The script receives the "needle" as a $_POST value and finds tropes which the title 
* contains the needle. The script returns HTML code consisting either of up to 5 tropes, with a 
* line between each of them, either a single line telling no trope was found. The main purpose of 
* this script is to be able to select a set of tropes in the database.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Trope.class.php';

if(isset($_POST['keyword']) && !empty($_POST['keyword']))
{
   $needle = Utils::secure($_POST['keyword']);
   $needle = str_replace('|', '', $needle); // Security, same for next line
   $needle = str_replace('"', '', $needle);
   
   if($needle === '')
   {
      exit();
   }
   
   try
   {
      $results = Trope::findTropes($needle);
      $nbResults = count($results);
      if($nbResults > 0)
      {
         $output = '';
         $style = ' style="background-color: rgb(230,230,230);"'; // To visually select first element
         for($i = 0; $i < $nbResults; $i++)
         {
            if($i == 0)
               $output .= '<li'.$style.'><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:TropesLib.addTrope(\'';
            else
               $output .= '<li><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:TropesLib.addTrope(\'';
            $output .= addslashes($results[$i]).'\')">'.$results[$i].'</a></li>'."\n";
         }
         header('Content-Type: text/html; charset=UTF-8');
         echo "<ul id=\"suggestionsList\" data-sugg=\"".$nbResults."\">\n".$output."</ul>\n";
      }
      else
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo '<ul id="suggestionsList" data-sugg="1">
         <li style="background-color: rgb(230,230,230);">Aucun code n\'a été trouvé</li>
         </ul>
         ';
      }
   }
   catch(Exception $e)
   {
      exit();
   }
}

?>
