<?php

class ListThumbnailIR
{
   /*
   * Converts the array modelizing a list into an intermediate representation, ready to be used in
   * a template. The output is a new array containing (in order of "call" in the template):
   *
   * -The pseudonym of the author, if it should be showed
   * -The ID of the list
   * -The title of the list
   * -A string with HTML attributes to set additionnal style (like background) for the thumbnail
   * -The link to the list (URL)
   * 
   * @param mixed $list[]     The array with all the data about this list
   * @param bool $showAuthor  True if the author should be showed (true by default)
   * @param mixed[]           The intermediate representation
   */

   public static function process($list, $showAuthor = true)
   {
      $output = array('author' => '', 
      'listID' => $list['id_commentable'], 
      'title' => $list['title'], 
      'styleAndData' => '', 
      'URL' => PathHandler::listURL($list));
      
      if($showAuthor)
         $output['author'] = 'show||'.$list['pseudo'];
      
      $thumbnail = PathHandler::HTTP_PATH().'upload/commentables/'.$list['id_commentable'].'.jpg';
      $style = 'style="background: url(\''.$thumbnail.'\') no-repeat top center; margin: 0px 0px 6px 6px;"';
      $output['styleAndData'] = $style; // N.B.: could be made richer in the future, hence the IR
      
      return $output;
   }
}

?>
