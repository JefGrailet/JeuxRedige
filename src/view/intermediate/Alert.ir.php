<?php

class AlertIR
{
   /*
   * Converts the array modelizing an alert into an intermediate representation, ready to be used 
   * in a template. The intermediate representation is a new array containing:
   *
   * -ID of the alert
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
      $output = array('ID' => $data['id_interaction'], 
      'avatar' => PathHandler::getAvatarSmall($data['author']), 
      'postURL' => PathHandler::HTTP_PATH().'Context.php?id_post='.$data['id_post'], 
      'author' => $data['author'], 
      'topicTitle' => $data['title'], 
      'topicURL' => PathHandler::HTTP_PATH().'/Topic.php?id_topic='.$data['id_topic'], 
      'date' => date('d/m/Y \à H\hi', Utils::toTimestamp($data['date_post'])), 
      'dateAlert' => date('d/m/Y \à H\hi', Utils::toTimestamp($data['date'])), 
      'motivation' => $data['motivation']);
      
      return $output;
   }
}

?>
