<?php

class ListFirstReactionIR
{
   /*
   * Converts the array modelizing a list into a string to be formatted which corresponds to the 
   * first message that should be displayed in a topic containing reactions to that list.
   *
   * @param mixed $list[]  The array modelizing the list
   * @return string        The first message of the topic containing reactions for this content
   */

   public static function process($list)
   {
      $output = '[g]Liste: '.$list['title'].'[/g]'."\n";
      $output .= "\n";
      $output .= $list['description']."\n";
      $output .= "\n";
      $output .= "[url=".PathHandler::listURL($list)."]Consulter la liste[/url]";
      
      return $output;
   }
}

?>
