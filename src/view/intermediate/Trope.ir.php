<?php

class TropeIR
{
   /*
   * Converts an array modelizing a trope for thumbnails, in the same fashion as a game.
   *
   * @param mixed $trope[]  The array with all the data about this trope
   * @param bool  $editable Set to true if the trope can be edited/deleted here
   * @param bool  $asHover  Set to true if the trope will appear as a tooltip
   * @param mixed[]         The intermediate representation
   */

   public static function process($trope, $editable = false, $asHover = true)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      $output = array('title' => $trope['tag'], 
      'styleAndData' => '', 
      'styleBis' => '', 
      'URL' => '', // For later
      'editButton' => '',
      'textColor' => '', 
      'description' => '');
      
      // Checks color brightness to pick the right text color
      list($r, $g, $b) = sscanf($trope['color'], "#%02x%02x%02x");
      $brigthness = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
      $textColor = '#FFFFFF';
      if($brigthness > 191)
         $textColor = '#000000';
      $output['textColor'] = 'style="color: '.$textColor.';"';
      
      // Style of the block
      $style = '';
      if($asHover)
         $style = 'style="background-color: '.$trope['color'].'; display:none;"';
      else
         $style = 'style="background-color: '.$trope['color'].'; margin: 0px 0px 6px 6px;"';
      $output['styleAndData'] = $style;
      
      // Style of the title
      $newR = $r - 50;
      $newG = $g - 50;
      $newB = $b - 50;
      if($newR < 0) $newR = 0;
      if($newG < 0) $newG = 0;
      if($newB < 0) $newB = 0;
      $output['styleBis'] = 'style="background-color: rgb('.$newR.','.$newG.','.$newB.'); color: '.$textColor.';"';
      
      // Description (icon + text)
      $icon = $webRootPath.'upload/tropes/'.PathHandler::formatForURL($trope['tag']).'.png';
      $output['description'] = '<img src="'.$icon.'" alt="'.$trope['tag'].'" style="float: left;"/> ';
      $output['description'] .= $trope['description'];
      
      // Edit button (+ if one user can edit games, (s)he can also edit tropes)
      if(LoggedUser::isLoggedIn() /* && Utils::check(LoggedUser::$data['can_edit_games']) */ && !$asHover && $editable)
      {
         $editionURL = $webRootPath.'EditTrope.php?trope='.urlencode($trope['tag']);
         $output['editButton'] = 'yes|| <a href="'.$editionURL.'"><img src="'.$webRootPath.'res_icons/trope_edit.png" title="Editer" alt="Editer" /></a>';
         $output['editButton'] .= ' <img class="buttonDelete" src="'.$webRootPath.'res_icons/trope_delete.png" alt="Supprimer" ';
         $output['editButton'] .= 'data-trope="'.$trope['tag'].'" title="Supprimer ce code"/>';
      }
      
      return $output;
   }
}

?>
