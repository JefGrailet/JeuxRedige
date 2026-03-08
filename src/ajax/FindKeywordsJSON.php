<?php

/**
* Script to search for tags/keywords containing a given input string the user wrote in some input
* text field. The script receives the "needle" as a $_POST value and returns HTML code consisting
* of either keywords containing the needle (with a new line between each of them) either a single
* line telling this keyword does not exist yet in the database.
*/


require '../libraries/Header.lib.php';
require '../model/Tag.class.php';


header('Content-Type: application/json; charset=utf-8');


if(isset($_GET['keyword']) && !empty($_GET['keyword']))
{
   $needle = Utils::secure($_GET['keyword']);
   $needle = str_replace('|', '', $needle); // Security, same for next line
   $needle = str_replace('"', '', $needle);

   if($needle === '')
   {
      echo json_encode([]);

      return;
   }

   try
   {
      $results = Tag::findTags($needle);
      $nbResults = count($results);
      sort($results);
      echo json_encode($results);
   }
   catch(Exception $e)
   {
      echo json_encode([]);
   }
}
