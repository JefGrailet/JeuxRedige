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
            
            $resStr .= '<img class="buttonUnmap" src="'.PathHandler::HTTP_PATH().'res_icons/title_unmap.png" alt="Retirer" ';
            $resStr .= 'data-id-emoticon="'.$emoticonID.'" ';
            $resStr .= 'title="Retirer de ma librairie"/>'; 
            
            /*
             * We also add a new edit button. Depending on whether the user created the emoticon 
             * or not, the code slightly changes.
             */
            
            if($emoticon->get('uploader') === LoggedUser::$data['pseudo'] || Utils::check(LoggedUser::$data['can_edit_all_posts']))
            {
               $resStr .= ' &nbsp;<img class="buttonEdit" src="'.PathHandler::HTTP_PATH().'res_icons/title_edit.png" alt="Modifier" ';
               $resStr .= 'data-id-emoticon="'.$emoticonID.'" title="Modifier le nom/code"/>';
            }
            else
            {
               $resStr .= ' &nbsp;<img class="buttonEditShortcut" src="'.PathHandler::HTTP_PATH().'res_icons/title_edit.png" alt="Modifier" ';
               $resStr .= 'data-id-emoticon="'.$emoticonID.'" title="Modifier le code"/>';
            }
            
            // Same goes for delete button (only for some users)
            if(Utils::check(LoggedUser::$data['can_edit_all_posts']))
            {
               $resStr .= ' &nbsp;<img class="buttonDelete" src="'.PathHandler::HTTP_PATH().'res_icons/title_delete.png" alt="Supprimer" ';
               $resStr .= 'data-id-emoticon="'.$emoticonID.'" title="Supprimer cette émoticône"/>';
            }
            
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
