<?php

class EmoticonThumbnailIR
{
   /*
   * Converts the array modelizing an emoticon with the shortcut used by the current user (if any) 
   * into an intermediate representation, ready to be used in an actual template. The intermediate 
   * representation is a new array containing (in order of "call" in the template):
   *
   * -ID of the emoticon
   * -Name of the emoticon
   * -Icons for emoticon interaction (add to one's personal library, modify, unmap, etc.)
   * -Emoticon as an <img> tag (HTML)
   * -Suggested shortcut
   * -Shortcut chosen by the current user (if any)
   * -Uploader
   * -Date of upload (formatted)
   *
   * @param mixed $emoticon[]  The emoticon itself (obtained with get(My)Emoticons() static method)
   * @param mixed[]            The intermediate representation
   */

   public static function process($data)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      $output = array('ID' => $data['id_emoticon'], 
      'name' => $data['name'],
      'interaction' => '',
      'img' => '<img src="'.$webRootPath.'upload/emoticons/'.$data['file'].'" alt="'.$data['name'].'"/>',
      'shortcut' => $data['suggested_shortcut'],
      'userShortcut' => '',
      'uploader' => '', 
      'date' => date('d/m/Y \à H\hi', Utils::toTimestamp($data['upload_date'])));
      
      // Edition icons
      $editButton = '';
      $mapButton = ''; // Also used for unmap (+ and -)
      $deleteButton = ''; // Only for admins
      if(LoggedUser::isLoggedIn())
      {
         if($data['shortcut'] !== NULL)
         {
            $mapButton .= ' &nbsp;<i class="buttonUnmap icon-general_minus" data-id-emoticon="'.$data['id_emoticon'].'" title="Retirer de ma librairie"></i>'."\n";
         }
         else
         {
            $mapButton .= ' &nbsp;<i class="buttonMap icon-general_plus" data-id-emoticon="'.$data['id_emoticon'].'" ';
            $mapButton .= 'data-suggestion="'.$data['suggested_shortcut'].'" title="Ajouter à ma librairie"></i>'."\n";
         }
         
         if(Utils::check(LoggedUser::$data['can_edit_all_posts']) || LoggedUser::$data['pseudo'] === $data['uploader'])
            $editButton .= ' &nbsp;<i class="buttonEdit icon-general_edit" data-id-emoticon="'.$data['id_emoticon'].'" title="Modifier le nom/code"></i>'."\n";
         else if($data['shortcut'] !== NULL)
            $editButton .= ' &nbsp;<i class="buttonEditShortcut icon-general_edit" data-id-emoticon="'.$data['id_emoticon'].'" title="Modifier le code"></i>'."\n";
      
         if(Utils::check(LoggedUser::$data['can_delete']))
            $deleteButton .= ' &nbsp;<i class="buttonDelete icon-general_trash" data-id-emoticon="'.$data['id_emoticon'].'" title="Supprimer cette émoticône"></i>'."\n";
      }
      $output['interaction'] = $mapButton.$editButton.$deleteButton;
      
      if($data['shortcut'] !== NULL)
      {
         $output['userShortcut'] = 'yes||'.$data['shortcut'];
      }
      
      if(LoggedUser::$data['pseudo'] !== $data['uploader'])
      {
         $output['uploader'] = 'yes||'.$data['uploader'];
      }
      
      return $output;
   }
}

?>
