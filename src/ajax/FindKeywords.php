<?php

/**
* Script to search for tags/keywords containing a given input string the user wrote in some input 
* text field. The script receives the "needle" as a $_POST value and returns HTML code consisting 
* of either keywords containing the needle (with a new line between each of them) either a single 
* line telling this keyword does not exist yet in the database.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Tag.class.php';

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
      $results = Tag::findTags($needle);
      $nbResults = count($results);
      if($nbResults > 0)
      {
         $output = '';
         $style = ' style="background-color: rgb(230,230,230);"'; // To visually select first element
         for($i = 0; $i < $nbResults; $i++)
         {
            if($i == 0)
               $output .= '<li'.$style.'><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:KeywordsLib.addKeyword(\'';
            else
               $output .= '<li><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:KeywordsLib.addKeyword(\'';
            $output .= addslashes($results[$i]).'\')">'.$results[$i].'</a></li>'."\n";
         }
         
         /*
          * Also allows to create new keywords if aliases/existing keywords are prefixes to some 
          * provided string (27/08/2023).
          */
         
         if(!empty($_POST['creation']))
         {
             $output .= '<li><a href="javascript:void(0)" data-kindex="'.$nbResults.'" ';
             $output .= 'data-new="yes" onclick="javascript:KeywordsLib.addKeyword(\'\')">';
             $output .= 'Créer un nouveau mot-clef (Clic/Enter)</li>'."\n";
         }
         
         header('Content-Type: text/html; charset=UTF-8');
         echo "<ul id=\"suggestionsList\" data-sugg=\"".$nbResults."\">\n".$output."</ul>\n";
      }
      else
      {
         $str = '';
         if(!empty($_POST['creation']))
         {
             $str = 'data-new="yes" onclick="javascript:KeywordsLib.addKeyword(\'\')">';
             $str .= 'Ce mot-clef n\'existe pas: créez-le ! (Clic/Enter)';
         }
         else
         {
             $str = 'data-new="blocked" onclick="javascript:KeywordsLib.closeSuggestions()">';
             $str .= 'Ce mot-clef n\'existe pas';
         }
         
         header('Content-Type: text/html; charset=UTF-8');
         echo '<ul id="suggestionsList" data-sugg="1">
         <li style="background-color: rgb(230,230,230);"><a href="javascript:void(0)" data-kindex="0"' .$str.'</a></li>
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
