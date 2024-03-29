<?php

require_once PathHandler::WWW_PATH().'view/intermediate/Commentable.ir.php';

class ListIR
{
   /*
   * Converts a list item (provided as an array) into an intermediate representation.
   */

   private static function processItem($item, $parentList)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      $output = array('itemID' => $item['id_item'], 
      'rank' => $item['rank'], 
      'gameThumbnail' => '', 
      'title' => '', 
      'icons' => '', 
      'interactivity' => '', 
      'comment' => '');
      
      // Game thumbnail
      $thumbnailPath = $webRootPath.'upload/games/'.PathHandler::formatForURL($item['game']).'/thumbnail1.jpg';
      $gameArr = array('tag' => $item['game']); // To "cheat" the PathHandler::gameURL() function
      $output['gameThumbnail'] = '<div class="listedGame" style="background: url(\''.$thumbnailPath.'\') ';
      $output['gameThumbnail'] .= 'no-repeat top center;">'."\n";
      if(strlen($item['subtitle']) > 0)
         $output['gameThumbnail'] .= '<h1>'.$item['game'].'</h1>'."\n";
      if($parentList->get('ordering') == 'top')
         $output['gameThumbnail'] .= '<h2>'.$item['rank'].'</h2>'."\n";
      $output['gameThumbnail'] .= '<a href="'.PathHandler::gameURL($gameArr).'"><span></span></a>'."\n";
      $output['gameThumbnail'] .= '</div>'."\n";
      
      // Title
      if(strlen($item['subtitle']) > 0)
         $output['title'] = $item['subtitle'];
      else
         $output['title'] = $item['game'];
      
      // Icons for edition
      if($parentList->isMine())
      {
         $output['icons'] = ' &nbsp;<a href="'.$webRootPath.'EditListItem.php?id_item='.$item['id_item'].'" target="_blank">';
         $output['icons'] .= '<i class="icon-general_edit" title="Editer"></i></a>';
         
         if($item['rank'] > 1)
         {
            $output['interactivity'] = '<a href="javascript:void(0)" class="moveItemUp">';
            $output['interactivity'] .= '<i class="icon-general_up" title="Déplacer vers le haut"></i></a> ';
         }
         
         if($item['rank'] < count($parentList->getBufferedItems()))
         {
            $output['interactivity'] .= '<a href="javascript:void(0)" class="moveItemDown">';
            $output['interactivity'] .= '<i class="icon-general_down" title="Déplacer vers le bas"></i></a> ';
         }
         
         $output['interactivity'] .= '<a href="javascript:void(0)" class="deleteItem">';
         $output['interactivity'] .= '<i class="icon-general_trash" title="Supprimer"></i></a>';
      }
      else if(LoggedUser::isLoggedIn() && Utils::check(LoggedUser::$data['can_edit_all_posts']))
      {
         $output['icons'] = ' &nbsp;<a href="'.$webRootPath.'EditListItem.php?id_item='.$item['id_item'].'" target="_blank">';
         $output['icons'] .= '<i class="icon-general_edit" title="Editer"></i></a>';
      }
      
      // If comment is ending with a div, do not end with "</p>"
      $postEnd = '</p>';
      if(substr($item['comment'], -8) === "</div>\r\n")
         $postEnd = '';
      
      // Prepares the comment
      $output['comment'] = '<p>
      '.$item['comment'].'
      '.$postEnd;
      
      return $output;
   }

   /*
   * Converts a list (provided as an object) into an intermediate representation, ready to be used in 
   * an actual template. The intermediate representation is a new array containing (in order of 
   * "call" in the template):
   *
   * -Absolute path to the thumbnail (picture) of the list
   * -Title of the list
   * -Date of creation
   * -Date of the last update (if any)
   * -Author of the list (HTML, because it is also a link to his/her profile)
   * -Content of the list
   * -A string telling whether to display the buttons to edit/delete the list or add an item
   * -A string telling what the template should display regarding the comment thread
   *
   * @param GamesList $list   The list, as an object
   * @param mixed[]           The intermediate representation
   */

   public static function process($list)
   {
      // Gets topic DB line and metadata
      $data = $list->getAll();
      $items = $list->getBufferedItems();
      
      // Intermediate representation array
      $output = array('listID' => $data['id_commentable'],
      'title' => $data['title'], 
      'thumbnail' => $list->getThumbnail(),
      'creationDate' => date('d/m/Y à H\hi', Utils::toTimestamp($data['date_publication'])),
      'modificationDate' => '', 
      'user' => '<a href="'.PathHandler::userURL($data['pseudo']).'" target="_blank">'.$data['pseudo'].'</a>', 
      'description' => $data['description'], 
      'items' => '', 
      'listEdition' => '', 
      'interaction' => '', 
      'ratings' => '');
      
      if($data['date_last_edition'] !== '1970-01-01 00:00:00')
         $output['modificationDate'] = ' (mise à jour le '.date('d/m/Y à H\hi', Utils::toTimestamp($data['date_last_edition'])).')';
      
      // Renders list items, if any
      $output['items'] = '';
      $items = $list->getBufferedItems();
      if($items != NULL && count($items) > 0)
      {
         $fullInput = array();
         for($i = 0; $i < count($items); $i++)
         {
            $itemIR = self::processItem($items[$i], $list);
            array_push($fullInput, $itemIR);
         }
         
         $itemsTpl = TemplateEngine::parseMultiple('view/content/ListItem.ctpl', $fullInput);
         if(!TemplateEngine::hasFailed($itemsTpl))
         {
            for($i = 0; $i < count($itemsTpl); $i++)
               $output['items'] .= $itemsTpl[$i];
         }
         else
         {
            $output['items'] = '<p style="text-align: center;">Une erreur est survenue lors de l\'affichage de la liste.</p>';
         }
      }
      else
      {
         $output['items'] = '<p style="text-align: center;">Cette liste est actuellement vide.</p>';
      }
      
      // Checks the user can edit the list or not
      if($list->isMine() || (LoggedUser::isLoggedIn() && Utils::check(LoggedUser::$data['can_edit_all_posts'])))
         $output['listEdition'] = 'yes||'.$data['id_commentable'];
      
      // Checks there's a comment thread or not
      if($list->get('id_topic') != NULL)
      {
         $topic = $list->getBufferedTopic();
         $output['interaction'] = 'seeTopic||'.PathHandler::topicURL($topic).'|'.($topic['nb'] - 1);
      }
      else if(LoggedUser::isLoggedIn())
      {
         $output['interaction'] = 'createTopic||'.$data['id_commentable'];
      }
      
      // Ratings
      $output['ratings'] = CommentableIR::processRatings($list, false);
      $output = array_merge($output, CommentableIR::process($list));
      
      return $output;
   }
}

?>
