<?php

class EditMessageIR
{
   /*
   * Converts the array modelizing a post into an intermediate representation, ready to be used in
   * a template displaying a form to edit that post. This helps to interpret some data (like 
   * reports) which are necessary for moderation.
   *
   * N.B.: does not "unparse" the content of the post; it justs interpret some particular fields.
   *
   * @param mixed $post[]      The array modelizing the post
   * @param bool $editingSelf  Set to true if the user is editing him- or herself
   * @return string            The part about reports as HTML code (for now)
   */

   public static function process($post, $editingSelf)
   {
      $output = '';
      
      if($post['bad_score'] > 0)
      {
         $output .= '<strong>N.B. :</strong> ce message a un score d\'alerte de '.$post['bad_score'].'.';
         if($post['bad_score'] >= 10)
            $output .= ' Par conséquent, il est masqué lors de la lecture du sujet.';
         $output .= '<br/>
         <br/>
         ';
         
         if(!$editingSelf)
         {
            $output .= '<input type="checkbox" name="cancelReports"/> 
            <label for="cancelReports">Annuler les alertes enregistrées pour ce message</label><br/>
            <br/>
            ';
         }
      }
      
      // Additionnal checkbox to force the script to NOT save the original message in the history
      if(!$editingSelf)
      {
         $output .= '<input type="checkbox" name="noSaveInHistory"/> 
         <label for="noSaveInHistory">Ne pas sauvegarder la version originale dans l\'historique</label><br/>
         <br/>
         ';
      }
      
      return $output;
   }
}

?>
