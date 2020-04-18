<?php

class PingIR
{
   /*
   * Converts the array modelizing a ping into an intermediate representation, ready to be used in 
   * a template. The intermediate representation is a new array containing:
   *
   * -ID of the ping
   * -Emitter's avatar
   * -Color of the top part
   * -Title of the ping (+ date)
   * -Button to mark the ping has viewed (HTML or empty if already marked as viewed)
   * -Message of the ping
   *
   * @param mixed $ping[]      The ping itself (obtained with method getAll() from Post class)
   * @return mixed[]           The intermediate representation
   */

   public static function process($ping)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      $otherParty = $ping['emitter'];
      if($otherParty === LoggedUser::$data['pseudo'])
         $otherParty = $ping['receiver'];
      
      $otherPartyWithURL = $otherParty;
      if($ping['ping_type'] === 'ping pong')
         $otherPartyWithURL = '<a href="'.$webRootPath.'Posts.php?author='.$otherParty.'">'.$otherParty.'</a>';

      $output = array('pingID' => $ping['id_ping'], 
      'otherPartyAvatar' => PathHandler::getAvatar($otherParty), 
      'otherPartyPseudo' => $otherPartyWithURL, 
      'topColor' => '', 
      'pingTitle' => '', 
      'interactions' => '',
      'message' => '');
      
      // Color of the top part and title
      switch($ping['ping_type'])
      {
         case 'notification':
            if(!Utils::check($ping['viewed']))
               $output['topColor'] = '#21D11B';
            else
               $output['topColor'] = '#47A544';
            
            $output['pingTitle'] = '<strong>'.$ping['title'].'</strong> - ';
            $output['pingTitle'] .= date('d/m/Y à H:i:s', Utils::toTimestamp($ping['emission_date']));
            break;
         
         case 'ping pong':
            if(!Utils::check($ping['viewed']))
               $output['topColor'] = '#0080FF';
            else
               $output['topColor'] = '#0B6FC6';
            
            $output['pingTitle'] = '<strong>'.$ping['title'].'</strong> - ';
            if($ping['state'] !== 'archived')
            {
               $output['pingTitle'] .= 'Dernier message le ';
               $output['pingTitle'] .= date('d/m/Y à H:i:s', Utils::toTimestamp($ping['last_update']));
            }
            else
            {
               $output['pingTitle'] .= 'Discussion close le ';
               $output['pingTitle'] .= date('d/m/Y à H:i:s', Utils::toTimestamp($ping['last_update']));
            }
            // $output['pingTitle'] .= ', début le ';
            // $output['pingTitle'] .= date('d/m/Y à H:i:s', Utils::toTimestamp($ping['emission_date']));
            break;
         
         case 'friendship request':
            if(!Utils::check($ping['viewed']))
               $output['topColor'] = '#F33E3E';
            else
               $output['topColor'] = '#C43333';
            
            // TODO: title
            
            break;
      }

      // Interactive icons
      if(!Utils::check($ping['viewed']) && $ping['ping_type'] === 'ping pong')
         $output['interactions'] .= '<i class="check icon-general_checked" data-ping="'.$ping['id_ping'].'" title="Marquer comme lu"></i>';
      
      if($ping['state'] == 'archived')
         $output['interactions'] .= '<i class="delete icon-general_trash" data-ping="'.$ping['id_ping'].'" title="Supprimer"></i>';
      
      // Case of discussion: displays the last message of the opposite party.
      if($ping['ping_type'] === 'ping pong')
      {
         // Decomposes the message
         $prevMessage = $ping['message'];
         $emitterMessage = '';
         $receiverMessage = '';
         $limitBlock = strpos($prevMessage, ']');
         if($limitBlock != FALSE)
         {
            $lengthEmitterMessage = intval(substr($prevMessage, 1, $limitBlock - 1));
            $emitterMessage = substr($prevMessage, $limitBlock + 1, $lengthEmitterMessage);
            $receiverMessage = substr($prevMessage, $limitBlock + 1 + $lengthEmitterMessage);
         }
         else
            $emitterMessage = $prevMessage;
         
         $messageToDisplay = '';
         if($otherParty === $ping['receiver'])
         {
            if(strlen($receiverMessage) == 0)
               $messageToDisplay = $otherParty.' n\'a pas encore répondu à votre/vos message(s).';
            else
               $messageToDisplay = $receiverMessage;
         }
         else
         {
            $messageToDisplay = $emitterMessage;
         }
         
         $output['message'] = '<p>
         '.$messageToDisplay;
         if(substr($messageToDisplay, -8) !== "</div>\r\n")
         {
            $output['message'] .= "<br/>\n<br/>\n";
            $output['message'] .= '<a href="'.$webRootPath.'PrivateDiscussion.php?id_ping='.$ping['id_ping'].'">Cliquez ici pour ';
            $output['message'] .= 'développer la discussion</a></p>';
         }
         else
         {
            $output['message'] .= '<p><a href="'.$webRootPath.'PrivateDiscussion.php?id_ping='.$ping['id_ping'].'">Cliquez ici pour ';
            $output['message'] .= 'développer la discussion</a></p>';
         }
         $output['message'] .= "\n";
      }
      // Otherwise: just displays the whole message field.
      else
      {
         $output['message'] = '<p>
         '.$ping['message'];
         if(substr($output['message'], -8) !== "</div>\r\n")
            $output['message'] .= '</p>';
         $output['message'] .= "\n";
      }
      
      return $output;
   }
}

?>
