<?php

class GameHeaderIR
{
   /*
   * Converts the array modelizing a game into an intermediate representation, ready to be used in 
   * a  template. The intermediate representation is a new array containing:
   *
   * -The path to the (main) thumbnail of the game (path)
   * -The link to the game page (path)
   * -The title of the game
   * -The icons to edit the game, if the user is allowed to (HTML)
   * -The genre
   * -The developer
   * -The publisher
   * -The publication date
   * -A string detailing the hardware on which the game was released (already styled with <span>)
   * -A string giving the aliases used for the game (already formatted in HTML)
   *
   * @param mixed $game[]     The array with all the data about this game
   * @param string $aliase[]  The aliases for this game
   * @param mixed[]           The intermediate representation
   */

   public static function process($game, $aliases)
   {
      $output = array('thumbnail' => PathHandler::HTTP_PATH().'upload/games/'.PathHandler::formatForURL($game['tag']).'/thumbnail1.jpg',
      'link' => PathHandler::gameURL($game),
      'title' => $game['tag'],
      'editionIcons' => '',
      'genre' => $game['genre'],
      'developer' => $game['developer'],
      'publisher' => $game['publisher'],
      'publicationDate' => date('d/m/Y', Utils::toTimestamp($game['publication_date'])),
      'hardware' => '',
      'aliases' => '');

      $editionLink = '';
      if(LoggedUser::isLoggedIn())
      {
         /*
         if(Utils::check(LoggedUser::$data['can_edit_games']))
         {
         }
         */
         $editionLink .= ' &nbsp;<a href="EditGame.php?game='.urlencode($game['tag']).'"><i class="icon-general_edit" title="Editer ce jeu"></i></a>';
      }
      
      $output['editionIcons'] = $editionLink;
      
      $hardwareArr = explode('|', $game['hardware']);
      $hardwareString = '';
      for($i = 0; $i < count($hardwareArr); $i++)
      {
         if($i > 0)
            $hardwareString .= ' ';
         $hardwareString .= '<span class="hw_'.$hardwareArr[$i].'">'.$hardwareArr[$i].'</span>';
      }
      
      $output['hardware'] = $hardwareString;

      $aliasesString = '';
      if($aliases != NULL && is_array($aliases))
      {
         $aliasesString = '<strong>Alias:</strong> ';
         for($i = 0; $i < count($aliases); $i++)
         {
            if($i > 0)
               $aliasesString .= ', ';
            $aliasesString .= $aliases[$i];
         }
      }
      
      if($aliasesString !== '')
      {
         $output['hardware'] .= "<br/>\n";
      }
      
      $output['aliases'] = $aliasesString;
      return $output;
   }
}

?>
