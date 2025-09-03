<?php

/**
* Script to map an user with an emoticon via AJAX.
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

if(!empty($_POST['id_emoticon']) && preg_match('#^([0-9]+)$#', $_POST['id_emoticon']) && !empty($_POST['shortcut']))
{
   $emoticonID = Utils::secure($_POST['id_emoticon']);
   $gotShortcut = Utils::secure($_POST['shortcut']);

   $resStr = '';
   if(!Emoticon::hasGoodFormat($gotShortcut))
   {
      $resStr = 'bad shortcut';
   }
   else
   {
      try
      {
         $emoticon = new Emoticon($emoticonID);
         $res = $emoticon->mapTo(LoggedUser::$data['pseudo'], $gotShortcut);
         if($res)
         {
            /*
             * If something changed, a replacing icon (-) is produced to update the calling page. 
             * The new button is directly produced here; there is no view for the sake of 
             * simplicity.
             */
            
            $resStr .= '<i class="buttonUnmap icon-general_minus" data-id-emoticon="'.$emoticonID.'" title="Retirer de ma librairie"></i>'; 
            
            /*
             * We also add a new edit button. Depending on whether the user created the emoticon 
             * or not, the code slightly changes.
             */
            
            if($emoticon->get('uploader') === LoggedUser::$data['pseudo'] || Utils::check(LoggedUser::$data['can_edit_all_posts']))
               $resStr .= ' &nbsp;<i class="buttonEdit icon-general_plus" data-id-emoticon="'.$emoticonID.'" title="Modifier le nom/code"></i>';
            else
               $resStr .= ' &nbsp;<i class="buttonEditShortcut icon-general_edit" data-id-emoticon="'.$emoticonID.'" title="Modifier le code"></i>';
            
            // Same goes for delete button (only for some users)
            if(Utils::check(LoggedUser::$data['can_edit_all_posts']))
               $resStr .= ' &nbsp;<i class="buttonDelete icon-general_trash" data-id-emoticon="'.$emoticonID.'" title="Supprimer cette émoticône"></i>';
            
            $resStr .= "\n";
         }
      }
      catch(Exception $e)
      {
         if(strstr($e->getMessage(), 'uplicat') != FALSE)
            $resStr = 'duplicate shortcut';
         else
            $resStr = 'DB error';
      }
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>
