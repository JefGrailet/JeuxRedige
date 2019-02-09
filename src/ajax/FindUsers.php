<?php

/**
* Script to search for users whose pseudo contains a certain string input by the user while he or 
* she writes down a full pseudonym or part of it. The script receives a "needle" as a $_POST value 
* and returns HTML code consisting of either pseudonyms containing the needle (with a new line 
* between each of them) either a single line telling no user could be found in the database.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/User.class.php';

if(isset($_POST['needle']) && !empty($_POST['needle']))
{
   $needle = Utils::secure($_POST['needle']);
   $needle = str_replace('|', '', $needle);
   $needle = str_replace('"', '', $needle);
   
   if($needle === '')
   {
      exit();
   }
   
   try
   {
      $results = User::findUsers($needle);
      $nbResults = count($results);
      if($nbResults > 0)
      {
         $output = '';
         $style = ' style="background-color: rgb(230,230,230);"'; // To visually select first element
         for($i = 0; $i < $nbResults; $i++)
         {
            if($i == 0)
               $output .= '<li'.$style.'><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:UsersLookUpLib.selectUser(\'';
            else
               $output .= '<li><a href="javascript:void(0)" data-kindex="'.$i.'" onclick="javascript:UsersLookUpLib.selectUser(\'';
            $output .= addslashes($results[$i]).'\')">'.$results[$i].'</a></li>'."\n";
         }
         header('Content-Type: text/html; charset=UTF-8');
         echo "<ul id=\"usersList\" data-sugg=\"".$nbResults."\">\n".$output."</ul>\n";
      }
      else
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo '<ul id="usersList" data-sugg="1">
         <li style="background-color: rgb(230,230,230);"><a href="javascript:void(0)" data-kindex="0" ';
         echo 'onclick="javascript:selectUser(\'\')">Aucun utilisateur n\'a été trouvé... (Enter pour fermer)</a></li>
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
