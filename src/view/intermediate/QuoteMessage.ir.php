<?php

class QuoteMessageIR
{
   /*
   * Converts the array modelizing a post into a string ready to be used in a form to produce a 
   * new post quoting the former. Note that this function does not "unparse" the content of the 
   * post; it justs interpret some particular fields.
   *
   * @param mixed $post[]  The array modelizing the post
   * @return string        The same post, but prepared for quotation
   */

   public static function process($post)
   {
      $output = '!user['.$post['author'].']'."\n";
      $output .= $post['content'];
      
      $exploded = explode("\n", $output);
      for($i = 0; $i < count($exploded); $i++)
      {
         if($exploded[$i] !== '' && $exploded[$i] !== "\r")
            $exploded[$i] = '>'.$exploded[$i];
      }
      $output = implode("\n", $exploded);
      
      return $output;
   }
}

?>
