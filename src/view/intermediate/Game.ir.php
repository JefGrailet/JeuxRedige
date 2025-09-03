<?php

class GameIR
{
   /*
   * Converts the array modelizing a game into an intermediate representation, ready to be used 
   * inan actual template for a game thumbnail. Such thumbnail can be used as a tooltip over a 
   * game tag or in a thumbnail pool. In both cases, the output is a new array containing (in 
   * order of "call" in the template):
   *
   * -A string with HTML attributes to set additionnal CSS style and data for JS scripts
   * -The link to the game page (URL)
   * -The title of the game
   * -The genre
   * -The publisher
   * -The developer
   * -The publication date
   * -A string detailing the hardware on which the game was released (already styled with <span>)
   *
   * @param mixed $game[]   The array with all the data about this game
   * @param bool  $asHover  Set to true if the game will appear as a tooltip (default)
   * @param mixed[]         The intermediate representation
   */

   public static function process($game, $asHover = true)
   {
      $output = array('title' => $game['tag'], 
      'styleAndData' => '', 
      'URL' => PathHandler::gameURL($game), 
      'fullTitle' => $game['tag'],
      'genre' => $game['genre'], 
      'publisher' => $game['publisher'], 
      'developer' => $game['developer'], 
      'publicationDate' => date('d/m/Y', Utils::toTimestamp($game['publication_date'])), 
      'hardware' => '', 
      'bottomLine' => '');
      
      if(strlen($output['title']) > 50)
      {
         $output['fullTitle'] = '<span title="'.$output['title'].'">'.Utils::shortenTitle($output['title']).'</span>';
      }
      
      $style = '';
      $thumbnail = PathHandler::HTTP_PATH().'upload/games/'.PathHandler::formatForURL($game['tag']).'/thumbnail1.jpg';
      if($asHover)
         $style = 'style="background: url(\''.$thumbnail.'\') no-repeat top center; display:none;"';
      else
         $style = 'style="background: url(\''.$thumbnail.'\') no-repeat top center; margin: 0px 0px 6px 6px;"';
      $output['styleAndData'] = $style;
      
      $hardwareArr = explode('|', $game['hardware']);
      $hardwareString = '';
      for($i = 0; $i < count($hardwareArr); $i++)
      {
         if($i > 0)
            $hardwareString .= ' ';
         $hardwareString .= '<span class="hw_'.$hardwareArr[$i].'">'.$hardwareArr[$i].'</span>';
      }
      
      $output['hardware'] = $hardwareString;

      // Bottom line
      if(LoggedUser::isLoggedIn() && /* Utils::check(LoggedUser::$data['can_edit_games']) && */ !$asHover)
      {
         $editionURL = PathHandler::HTTP_PATH().'EditGame.php?game='.urlencode($game['tag']);
         $output['bottomLine'] = 'yes||<p><a href="'.$editionURL.'">Editer</a></p>';
      }
      
      return $output;
   }
}

?>
