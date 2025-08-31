<?php

/**
 * Library designed to handle keywords in general, i.e., to display them as HTML code (with the 
 * right JS code to interact with them) and to compare sets of keywords together.
 */

class Keywords
{
   /**
    * Turns an array of keywords into HTML code displaying them, without any interaction.
    *
    * @param string $arr[]       An array of keywords to display
    * @return string             HTML code displaying the keywords
    */

   public static function displayPlain($arr)
   {
      if($arr == NULL || count($arr) == 0 || (count($arr) == 1 && $arr[0] === ''))
         return '';
      
      $output = '<strong>Mots-clefs:</strong> ';
      for($i = 0; $i < count($arr); $i++)
      {
         if($i > 0)
            $output .= ', ';
         $output .= $arr[$i];
      }
      $output .= "<br/>\n<br/>\n";
      
      return $output;
   }

   /**
    * Turns an array of keywords into HTML code displaying them with delete buttons. In addition 
    * to the array of keywords, one must provide the JavaScript function to call for deletion.
    *
    * @param string $arr[]       An array of keywords to display
    * @param string $jsFunction  The JS function to call for deletion
    * @return string             HTML code displaying the keywords (with delete buttons)
    */

   private static function displayGeneric($arr, $jsFunction)
   {
      if($arr == NULL || count($arr) == 0 || (count($arr) == 1 && $arr[0] === ''))
         return '';

      $output = '';
      for($i = 0; $i < count($arr); $i++)
      {
         if(strlen($arr[$i]) == 0) // Just in case
            continue;
         
         if($i > 0)
            $output .= ' ';
         $output .= $arr[$i];
         $output .=' <a onclick="javascript:'.$jsFunction.'(\''.addslashes($arr[$i]).'\')" ';
         $output .= 'class="deleteKeyword">';
         $output .= '<i class="icon-general_trash" title="Supprimer"></i>';
         $output .= '</a>';
      }
      $output .= " <br/>\n<br/>\n";
      
      return $output;
   }

   /**
    * Turns an array of keywords into HTML code displaying them with delete buttons.
    *
    * @param string $arr[]  An array of keywords to display
    * @return string        HTML code displaying the keywords with delete button
    */

   public static function display($arr)
   {
      return self::displayGeneric($arr, 'KeywordsLib.removeKeyword');
   }
   
   /**
    * Turns an array of aliases into HTML code displaying them with delete buttons.
    *
    * @param string $arr[]  An array of keywords to display
    * @return string        HTML code displaying the keywords with delete button
    */
   
   public static function displayAliases($arr)
   {
      return self::displayGeneric($arr, 'GameEditorLib.removeAlias');
   }

   /**
    * Lists the common keywords between two sets, given as arrays. The result is an array as well.
    *
    * @param string $arr1[]  The first set of keywords
    * @param string $arr2[]  The second set of keywords
    * @return string[]       The common keywords
    */

   public static function common($arr1, $arr2)
   {
      $listKeywords = array();
      if ($arr1 == null || $arr2 == null)
         return $listKeywords;
      
      for($cur = 0; $cur < count($arr1); $cur++)
      {
         if(in_array($arr1[$cur], $arr2))
            array_push($listKeywords, $arr1[$cur]);
      }
      return $listKeywords;
   }

   /**
    * In a similar fashion, lists the elements of a first set that are absent from a second set.
    *
    * @param string $arr1[]  The first set of keywords
    * @param string $arr2[]  The second set of keywords
    * @return string[]       The keywords present in $arr1 but absent from $arr2
    */

   public static function distinct($arr1, $arr2)
   {
      $listKeywords = array();
      if($arr2 == null && $arr1 != null)
      {
         for($cur = 0; $cur < count($arr1); $cur++)
            if (strlen($arr1[$cur]) > 0)
               array_push($listKeywords, $arr1[$cur]);
      }
      else if($arr1 != null)
      {
         for($cur = 0; $cur < count($arr1); $cur++)
            if(strlen($arr1[$cur]) > 0 && !in_array($arr1[$cur], $arr2))
               array_push($listKeywords, $arr1[$cur]);
      }
      return $listKeywords;
   }
}

?>
