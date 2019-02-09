<?php

class GameLightIR
{
   /*
   * Converts the array modelizing a game into an intermediate representation, ready to be used in 
   * an actual template. This version is a lightened display that should only be displayed in a 
   * thumbnail pool. The output is a new array containing (in order of "call" in the template) :
   *
   * -The title of the game
   * -A string with HTML attributes to set additionnal style (like background) for the thumbnail
   * -The link to the game page (URL)
   * 
   * @param mixed $game[]  The array with all the data about this game
   * @param mixed[]        The intermediate representation
   */

   public static function process($game)
   {
      $output = array('title' => $game['tag'], 
      'styleAndData' => '', 
      'URL' => PathHandler::gameURL($game));
      
      $style = '';
      $thumbnail = PathHandler::HTTP_PATH.'upload/games/'.PathHandler::formatForURL($game['tag']).'/thumbnail1.jpg';
      $output['styleAndData'] = 'style="background: url(\''.$thumbnail.'\') no-repeat top center; margin: 0px 0px 6px 6px;"';
      
      return $output;
   }
}

?>
