<?php

/**
* Script to unmap an user from an emoticon via AJAX. Much like Vote.php, the call of some methods 
* will have no effect when repeated for the sake of consistency.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Emoticon.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_POST['id_emoticon']) && preg_match('#^([0-9]+)$#', $_POST['id_emoticon']))
{
   $emoticonID = Utils::secure($_POST['id_emoticon']);

   $resStr = '';
   try
   {
      $emoticon = new Emoticon($emoticonID);
      $res = $emoticon->unmapTo(LoggedUser::$data['pseudo']);
      if($res)
      {
         /*
          * If something changed, a replacing icon (+) is produced to update the calling page. The 
          * new button is directly produced here; there is no view for the sake of simplicity.
          */
         
         $shortcut = $emoticon->get('suggested_shortcut');
         $resStr .= '<i class="buttonMap icon-general_plus" data-id-emoticon="'.$emoticonID.'" data-suggestion="'.$shortcut.'" title="Ajouter à ma librairie"></i>';
         
         /*
          * We also provide along the edit button if necessary, because it is easier to check that 
          * it should be maintained here than in the JS file. The edit button remains either if:
          * -the user has uploaded that emoticon.
          * -the user has a special rank ('can_edit_all_posts' set to 'yes') to do that.
          */
         
         if($emoticon->get('uploader') === LoggedUser::$data['pseudo'] || Utils::check(LoggedUser::$data['can_edit_all_posts']))
            $resStr .= ' &nbsp;<i class="buttonEdit icon-general_edit" data-id-emoticon="'.$emoticonID.'" title="Modifier le nom/code"></i>';
         
         // Same goes for delete button (only for some users)
         if(Utils::check(LoggedUser::$data['can_edit_all_posts']))
            $resStr .= ' &nbsp;<i class="buttonDelete icon-general_trash" data-id-emoticon="'.$emoticonID.'" title="Supprimer cette émoticône"></i>';
         
         $resStr .= "\n";
      }
   }
   catch(Exception $e)
   {
      // Nothing happens
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>
