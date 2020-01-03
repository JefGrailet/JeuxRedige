<?php

class PinIR
{
   /*
   * Converts the array modelizing a pin into an intermediate representation, ready to be used in 
   * a template. The intermediate representation is a new array containing:
   *
   * -ID of the pin (id_post rather than id_interaction, actually)
   * -Avatar of the author of the pinned message
   * -URL of the pinned message
   * -Author of the pinned message
   * -Date of the pinned message
   * -Date of the pin
   * -Comment of the pin
   *
   * @param mixed $pin[]  The pin itself (obtained with getPins() static method)
   * @param mixed[]       The intermediate representation
   */

   public static function process($data)
   {
      $output = array('ID' => $data['id_post'], 
      'avatar' => PathHandler::getAvatarSmall($data['author']), 
      'postURL' => PathHandler::HTTP_PATH().'Context.php?id_post='.$data['id_post'], 
      'author' => $data['author'],
      'date' => date('d/m/Y \à H\hi', Utils::toTimestamp($data['date_post'])), 
      'datePin' => date('d/m/Y \à H\hi', Utils::toTimestamp($data['date'])), 
      'comment' => $data['comment']);
      
      return $output;
   }
}

?>
