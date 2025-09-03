<?php

/**
* Small script called through AJAX to switch the order between two items of a list. The operation 
* is allowed if and only if both segments belong to the same list and if the current user is the 
* author of the list.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}

require '../model/GamesList.class.php';
require '../model/ListItem.class.php';

if(!empty($_POST['id_list']) && preg_match('#^([0-9]+)$#', $_POST['id_list']) && 
   !empty($_POST['id_item1']) && preg_match('#^([0-9]+)$#', $_POST['id_item1']) && 
   !empty($_POST['id_item2']) && preg_match('#^([0-9]+)$#', $_POST['id_item2']))
{
   $listID = intval(Utils::secure($_POST['id_list']));
   $itemID_1 = intval(Utils::secure($_POST['id_item1']));
   $itemID_2 = intval(Utils::secure($_POST['id_item2']));
   
   $parentList = NULL;
   $item1 = NULL;
   $item2 = NULL;
   try
   {
      $parentList = new GamesList($listID);
      $items = $parentList->getItems();
      
      for($i = 0; $i < count($items); $i++)
      {
         if($items[$i]['id_item'] == $itemID_1)
            $item1 = new ListItem($items[$i]);
         else if($items[$i]['id_item'] == $itemID_2)
            $item2 = new ListItem($items[$i]);
      }
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
   }
   
   if($item1 != null && $item2 != null && $parentList->isMine())
   {
      try
      {
         Database::beginTransaction();
         
         $tmp = intval($item1->get('rank'));
         $item1->changeRank(intval($item2->get('rank')));
         $item2->changeRank($tmp);
         
         $parentList->update(); // To update the last edition date
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
      
      header('Content-Type: text/html; charset=UTF-8');
      echo 'OK';
   }
   else
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Missing items';
   }
}

?>