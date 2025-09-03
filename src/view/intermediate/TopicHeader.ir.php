<?php

// Includes two other IR's which can otherwise be used individually
require './view/intermediate/Game.ir.php';

class TopicHeaderIR
{
   /*
   * Generates a pseudo-random colour (as RGB value) based on a SHA1 hash of some string.
   *
   * @param string   $input  The string from which the pseudo-random colour is generated
   * @return string          The colour as a RGB value
   */

   private static function stringToColour($input)
   {
      $hash = sha1($input);
      
      $rgb = array(0, 0, 0);
      $rgb[0] = abs(hexdec(substr($hash, 0, 5))) % 128;
      $rgb[1] = abs(hexdec(substr($hash, 5, 5))) % 128;
      $rgb[2] = abs(hexdec(substr($hash, 10, 5))) % 128;
      
      // Dominant component is reinforced
      $dominant = 0;
      $index = 0;
      for($i = 0; $i < 3; $i++)
      {
         if($rgb[$i] > $dominant)
         {
            $dominant = $rgb[$i];
            $index = $i;
         }
      }
      $rgb[$index] += 50;
      return $rgb[0].','.$rgb[1].','.$rgb[2];
   }

   /*
   * Converts the array modelizing a topic (and related data) into an intermediate representation, 
   * ready to be used in an actual template for a topic header. The intermediate representation is 
   * a new array containing (in order of "call" in the template):
   *
   * -Absolute path to the thumbnail (picture) of the topic
   * -Link to the topic (URL)
   * -Title of the topic
   * -Suffix to the title (to indicate if we are in: uploads page, popular posts page, etc.)
   * -Menu of the topic ((un)popular posts, uploads, delete/edit...) (HTML)
   * -Icons describing the content of the topic (e.g. can anons post ? Is it a review ?) (HTML)
   * -Author of the topic (HTML, because his/her pseudo can has several colors)
   * -Keywords of that topic (HTML)
   * -Game entries matching the keywords, if any (HTML)
   *
   * For some fields, the function requires metadata from the topic. When this metada is not 
   * available, default values will be used.
   *
   * @param Topic $topic      The topic object
   * @param string $location  Page where the header will be used; useful for title suffix (optional)
   * @param mixed[]           The intermediate representation
   */

   public static function process($topic, $location = '')
   {
      // Gets topic DB line and metadata
      $data = $topic->getAll();
      $keywords = $topic->getBufferedKeywords();
      $featuredPosts = $topic->getBufferedFeaturedPosts();
      $userView = $topic->getBufferedView();
      $withPins = $topic->getNbPins() > 0;
      
      $favorited = false;
      if($userView != NULL && Utils::check($userView['favorite']))
         $favorited = true;
      
      // Intermediate representation array
      $output = array('menu' => '', 
      'link' => PathHandler::topicURL($data),
      'title' => $data['title'],
      'contentIcons' => '',
      'thumbnail' => PathHandler::getTopicThumbnail($data['thumbnail'], $data['id_topic']),
      'keywords' => '', 
      'games' => '');

      // Checks that the user is able to edit the topic itself (title, keywords, etc.)
      $canEdit = false;
      if(LoggedUser::isLoggedIn())
      {
         if(!Utils::check($data['is_locked']) && Utils::check(LoggedUser::$data['can_edit_all_posts']) || (Utils::check(LoggedUser::$data['can_create_topics']) &&
            ($data['author'] === LoggedUser::$data['pseudo'] || $data['author'] === LoggedUser::$data['used_pseudo'])))
         {
            $canEdit = true;
            $editionButton = '<a href="EditTopic.php?id_topic='.$data['id_topic'].'">Editer ce sujet</a>'."\n";
         }
      }
      
      // Topic menu
      $menuItems = array();
      $menuItemsLabels = array('edition', 'uploads', 'popular', 'unpopular', 'pins', 'lastMessage');
      if($featuredPosts['withUploads'] > 0 || $featuredPosts['popular'] > 0 || $featuredPosts['unpopular'] > 0 || $withPins || $canEdit)
      {
         if($featuredPosts['withUploads'] > 0)
            $menuItems['uploads'] = '<a href="Uploads.php?id_topic='.$data['id_topic'].'"><i class="icon-topic_uploads" alt="Uploads"></i>Galerie des uploads</a>';
         if($featuredPosts['popular'] > 0)
            $menuItems['popular'] = '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'"><i class="icon-topic_popular" alt="Populaires"></i>Messages populaires</a>';
         if($featuredPosts['unpopular'] > 0)
            $menuItems['unpopular'] = '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'&section=unpopular"><i class="icon-topic_unpopular" alt="Impopulaires"></i>Messages impopulaires</a>';
         if($withPins)
            $menuItems['pins'] = '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'&section=pinned"><i class="icon-general_pin" alt="Mes messages épinglés"></i>Messages épinglés</a>';
         if($canEdit)
            $menuItems['edition'] = '<a href="EditTopic.php?id_topic='.$data['id_topic'].'"><i class="icon-general_edit" alt="Editer"></i>Editer ce sujet</a>';
      }
      $menuItems['lastMessage'] = '<a href="LastPost.php?id_topic='.$data['id_topic'].'"><i class="icon-general_leave" alt="Dernier message"></i>Aller au dernier message</a>';
      
      $output['menu'] = '';
      $addIndent = false;
      for($i = 0; $i < count($menuItemsLabels); $i++)
      {
         if(!array_key_exists($menuItemsLabels[$i], $menuItems))
            continue;
         if($addIndent)
            $output['menu'] .= '      ';
         else
            $addIndent = true;
         if($location == $menuItemsLabels[$i])
         {
            $minusLink = substr($menuItems[$menuItemsLabels[$i]], 0, -4);
            $tagEnd = strpos($minusLink, '"><i');
            if($tagEnd == false)
               continue;
            $minusLink = substr($minusLink, $tagEnd + 2);
            $output['menu'] .= '<span class="highlighted">'.$minusLink.'</span>'."\n";
         }
         else 
         {
            $output['menu'] .= $menuItems[$menuItemsLabels[$i]]."\n";
         }
      }
      
      // Content and moderation icons
      $anonPostingIcon = '';
      $favoriteButton = '';
      $lockingButton = ''; // Can be a single icon
      $deletionButton = '';
      if(LoggedUser::isLoggedIn())
      {
         if($favorited)
            $favoriteButton = '<i id="buttonFavourite" class="icon-general_star" alt="Enlever" data-id-topic="'.$data['id_topic'].'" title="Enlever des favoris"></i>'."\n";
         else
            $favoriteButton = '<i id="buttonFavourite" class="icon-general_star_empty" alt="Ajouter" data-id-topic="'.$data['id_topic'].'" title="Ajouter aux favoris"></i>'."\n";
      
         if(Utils::check(LoggedUser::$data['can_delete']))
            $deletionButton = '<i id="buttonDelete" class="icon-general_trash" alt="Supprimer" title="Supprimer ce sujet"></i>'."\n";
      
         if(Utils::check($data['is_locked']))
         {
            if(Utils::check(LoggedUser::$data['can_lock']))
               $lockingButton .= '<i id="buttonLock" class="icon-topic_unlock" alt="Déverrouiller" title="Déverrouiller ce sujet"></i>'."\n";
            else
               $lockingButton = '<i class="icon-topic_locked" alt="Verrouillé" title="Ce sujet est verrouillé"></i>'."\n";
         }
         else if(Utils::check(LoggedUser::$data['can_lock']))
            $lockingButton = '<i id="buttonLock" class="icon-topic_lock" alt="Verrouiller" title="Verrouiller ce sujet"></i>'."\n";
      }
      else if(Utils::check($data['is_locked']))
      {
         $lockingLink = '<i class="icon-topic_locked" alt="Verrouillé" title="Ce sujet est verrouillé"></i>'."\n";
      }
      $output['contentIcons'] = $anonPostingIcon.$favoriteButton.$lockingButton.$deletionButton;
      
      // Keywords and associated games
      if(!empty($keywords))
      {
         $listKeywords = '';
         for($i = 0; $i < count($keywords); $i++)
         {
            if($i > 0)
               $listKeywords .= ' ';
            $link = PathHandler::HTTP_PATH().'Search.php?keywords='.urlencode($keywords[$i]['tag']);
            $ownColor = self::stringToColour($keywords[$i]['tag']);
            $style = 'style="background-color: rgb('.$ownColor.');" data-rgb="'.$ownColor.'"';
            $listKeywords .= '<a href="'.$link.'" target="blank" '.$style.'>'.$keywords[$i]['tag'].'</a>';
            
            if($keywords[$i]['genre'] !== NULL)
            {
               $curGame = $keywords[$i];
               $gameIR = GameIR::process($curGame);
               $gameTpl = TemplateEngine::parse('view/content/Game.ctpl', $gameIR);
            
               if(!TemplateEngine::hasFailed($gameTpl))
                  $output['games'] .= $gameTpl;
            }
         }
         $output['keywords'] = $listKeywords;
      }
      
      return $output;
   }
}

?>
