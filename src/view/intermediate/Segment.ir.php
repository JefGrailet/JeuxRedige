<?php

class SegmentIR
{
   /*
   * Converts the array modelizing a segment into an intermediate representation, ready to be used in 
   * an actual template. The intermediate representation is a new array containing:
   *
   * -ID of the segment
   * -Position of the segment in its parent article
   * -Display of the segment at page load
   * -Style of the header (might be a big image taking the whole width)
   * -Title of the segment
   * -Subtitle of the segment (left empty, only filled for first segment for now)
   * -Content of the segment
   *
   * @param mixed $data[]    The segment itself (obtained with method getAll() from Segment class)
   * @param bool $displayed  True if the segment should be visible at page load
   * @return mixed[]         The intermediate representation
   */

   public static function process($data, $displayed)
   {
      $output = array('ID' => $data['id_segment'], 
      'position' => $data['position'], 
      'displayState' => $displayed ? 'block':'none', 
      'headerStyle' => '',
      'title' => $data['title'], 
      'mainSubtitle' => '', 
      'content' => '');
      
      // Header style
      $headerFile = PathHandler::WWW_PATH().'upload/articles/'.$data['id_article'].'/'.$data['id_segment'].'/header.jpg';
      if(file_exists($headerFile))
      {
         $URL = PathHandler::HTTP_PATH().'upload/articles/'.$data['id_article'].'/'.$data['id_segment'].'/header.jpg';
         $output['headerStyle'] = 'class="segmentHeader"';
         $output['headerStyle'] .= ' style="background: url(\''.$URL.'\') no-repeat center;';
         $output['headerStyle'] .= ' padding-top: 16%;';
         $output['headerStyle'] .= ' background-size: cover;"';
      }
      else
      {
         $output['headerStyle'] = 'class="segmentHeaderPlain"';
      }
      
      // If content is ending with a div, do not end with "</p>"
      $contentEnd = '</p>';
      if(str_contains(substr($data['content'], -10), "</div>") !== FALSE)
         $contentEnd = '';
      
      $finalContent = "<p>\n".$data['content'];
      
      // If necessary, signals that the segment has been edited after publication
      $lastTimestamp = Utils::toTimestamp($data['date_last_modification']);
      if($lastTimestamp > 0)
      {
         // If ending with a closing </div>, a <p> should be there: no addition needed (19/01/24)
         if($contentEnd !== '')
            $finalContent .= "<br/>\n<br/>\n";
         $finalContent .= '<span style="color: grey;">Dernière modification le ';
         $finalContent .= date('d/m/Y à H:i', $lastTimestamp).'.</span>';
         if($contentEnd === '')
            $finalContent .= "\n".'</p>';
      }
      
      $finalContent .= $contentEnd;
      
      // Strips empty <p></p> HTML tags
      $finalContent = preg_replace('/(<p>([\s]+)<\/p>)/iU', '', $finalContent);
      
      $output['content'] = $finalContent;
      return $output;
   }
}

?>
