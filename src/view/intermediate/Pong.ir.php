<?php

class PongIR
{
   /*
   * Converts the array modelizing a pong into an intermediate representation, ready to be used in
   * a template. The intermediate representation is a new array containing:
   *
   * -ID of the pong within the thread (solely used for anchoring after posting a reply for now)
   * -Pseudonym, avatar and style of <h1> (can be empty) to advertise online status
   * -Date of the post as a text
   * -The content
   *
   * @param mixed   $pong[]  The post itself (obtained with method getAll() from Post class)
   * @param integer $ID      The ID of the message in its discussion
   * @return mixed[]         The intermediate representation
   */

   public static function process($pong, $ID)
   {
      $output = array('pongID' => $ID, 
      'authorPseudo' => '<a href="'.PathHandler::HTTP_PATH().'Posts.php?author='.$pong['author'].'" target="blank">'.$pong['author'].'</a>', 
      'authorAvatar' => PathHandler::getAvatar($pong['author']), 
      'authorStyle' => '',
      'date' => 'Le '.date('d/m/Y à H:i:s', Utils::toTimestamp($pong['date'])), 
      'content' => '');

      if(array_key_exists('online', $pong) && $pong['online'])
         $output['authorStyle'] = ' style="background-color: #38883f;" title="En ligne"';

      $output['content'] = '<p>
      '.$pong['message'].'
      </p>';
      
      // Strips empty <p></p> HTML tags
      $output['content'] = preg_replace('/(<p>([\s]+)<\/p>)/iU', '', $output['content']);
      
      return $output;
   }
}

?>
