<?php

/**
* Small script called through AJAX to delete a list item. This can only be carried out if the 
* current user is logged and the author of the list.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/GamesList.class.php';
require '../model/ListItem.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

if(!empty($_POST['id_item']) && preg_match('#^([0-9]+)$#', $_POST['id_item']))
{
   $itemID = intval(Utils::secure($_POST['id_item']));
   
   $item = null;
   $parentList = null;
   try
   {
      $item = new ListItem($itemID);
      $parentList = new GamesList($item->get('id_commentable'));
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
   }
   
   if($parentList->isMine())
   {
      try
      {
         $item->delete();
      }
      catch(Exception $e)
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
      
      header('Content-Type: text/html; charset=UTF-8');
      echo 'OK';
   }
   else
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Wrong item';
   }
}

?>