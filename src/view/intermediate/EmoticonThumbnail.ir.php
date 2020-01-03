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
            $mapButton .= ' &nbsp;<img class="buttonUnmap" src="'.$webRootPath.'res_icons/title_unmap.png" alt="Retirer" ';
            $mapButton .= 'data-id-emoticon="'.$data['id_emoticon'].'" title="Retirer de ma librairie"/>'."\n";
         }
         else
         {
            $mapButton .= ' &nbsp;<img class="buttonMap" src="'.$webRootPath.'res_icons/title_map.png" alt="Ajouter" ';
            $mapButton .= 'data-id-emoticon="'.$data['id_emoticon'].'" data-suggestion="'.$data['suggested_shortcut'].'" ';
            $mapButton .= 'title="Ajouter à ma librairie"/>'."\n";
         }
         
         if(Utils::check(LoggedUser::$data['can_edit_all_posts']) || LoggedUser::$data['pseudo'] === $data['uploader'])
         {
            $editButton .= ' &nbsp;<img class="buttonEdit" src="'.$webRootPath.'res_icons/title_edit.png" alt="Modifier" ';
            $editButton .= 'data-id-emoticon="'.$data['id_emoticon'].'" title="Modifier le nom/code"/>'."\n";
         }
         else if($data['shortcut'] !== NULL)
         {
            $editButton .= ' &nbsp;<img class="buttonEditShortcut" src="'.$webRootPath.'res_icons/title_edit.png" alt="Modifier" ';
            $editButton .= 'data-id-emoticon="'.$data['id_emoticon'].'" title="Modifier le code"/>'."\n";
         }
      
         if(Utils::check(LoggedUser::$data['can_delete']))
         {
            $deleteButton .= ' &nbsp;<img class="buttonDelete" src="'.$webRootPath.'res_icons/title_delete.png" alt="Supprimer" ';
            $deleteButton .= 'data-id-emoticon="'.$data['id_emoticon'].'" title="Supprimer cette émoticône"/>'."\n";
         }
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
