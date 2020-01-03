<?php

// Includes two other IR's which can otherwise be used individually
require './view/intermediate/Game.ir.php';
// require './view/intermediate/Trope.ir.php';

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
      $webRootPath = PathHandler::HTTP_PATH();
      
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
      'titleSuffix' => '', 
      'contentIcons' => '',
      'thumbnail' => PathHandler::getTopicThumbnail($data['thumbnail'], $data['id_topic']),
      'keywords' => '', 
      'games' => '');
      
      // Title suffix
      if($location === 'edition')
         $output['titleSuffix'] = ' <img src="'.$webRootPath.'res_icons/title_edit.png" alt="Editer" title="Edition du sujet"/>';
      else if($location === 'popular')
         $output['titleSuffix'] = ' <img src="'.$webRootPath.'res_icons/title_popular.png" alt="Populaires" title="Messages populaires"/>';
      else if($location === 'unpopular')
         $output['titleSuffix'] = ' <img src="'.$webRootPath.'res_icons/title_unpopular.png" alt="Impopulaires" title="Messages impopulaires"/>';
      else if($location === 'pins')
         $output['titleSuffix'] = ' <img src="'.$webRootPath.'res_icons/title_pins.png" alt="Epinglés" title="Messages épinglés"/>';
      else if($location === 'uploads')
         $output['titleSuffix'] = ' <img src="'.$webRootPath.'res_icons/title_uploads.png" alt="Uploads" title="Galerie d\'uploads"/>';
      
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
      $output['menu'] = '<ul>'."\n";
      $output['menu'] .= '<li><img class="topicIcon" src="'.$webRootPath.'res_icons/title_menu.png" alt="Menu"/>'."\n";
      $output['menu'] .= '   <ul id="topicMenu">'."\n";
      if($featuredPosts['withUploads'] > 0 || $featuredPosts['popular'] > 0 || $featuredPosts['unpopular'] > 0 || $withPins || $canEdit)
      {
         if($featuredPosts['withUploads'] > 0)
         {
            $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_uploads.png" alt="Uploads"/>';
            $output['menu'] .= '<a href="Uploads.php?id_topic='.$data['id_topic'].'">Galerie des uploads</a></li>'."\n";
         }
         if($featuredPosts['popular'] > 0)
         {
            $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_popular.png" alt="Populaires"/>';
            $output['menu'] .= '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'">Messages populaires</a></li>'."\n";
         }
         if($featuredPosts['unpopular'] > 0)
         {
            $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_unpopular.png" alt="Impopulaires"/>';
            $output['menu'] .= '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'&section=unpopular">Messages impopulaires</a></li>'."\n";
         }
         if($withPins)
         {
            $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_pins.png" alt="Mes messages épinglés"/>';
            $output['menu'] .= '<a href="PopularPosts.php?id_topic='.$data['id_topic'].'&section=pinned">Messages épinglés</a></li>'."\n";
         }
         if($canEdit)
         {
            $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_edit.png" alt="Editer"/>';
            $output['menu'] .= '<a href="EditTopic.php?id_topic='.$data['id_topic'].'">Editer ce sujet</a></li>'."\n";
         }
      }
      $output['menu'] .= '      <li><img src="'.$webRootPath.'res_icons/title_last_post.png" alt="Dernier message"/>';
      $output['menu'] .= '<a href="LastPost.php?id_topic='.$data['id_topic'].'">Aller au dernier message</a></li>'."\n";
      $output['menu'] .= '   </ul>'."\n";
      $output['menu'] .= '</ul>'."\n";
      
      // Content and moderation icons
      $anonPostingIcon = '';
      $favoriteButton = '';
      $lockingButton = ''; // Can be a single icon
      $deletionButton = '';
      if(LoggedUser::isLoggedIn())
      {
         if($favorited)
         {
            $favoriteButton = ' &nbsp;<img id="buttonFavourite" class="topicIcon" src="'.$webRootPath.'res_icons/title_unfavourite.png" alt="Enlever" ';
            $favoriteButton .= 'data-id-topic="'.$data['id_topic'].'" title="Enlever des favoris"/>'."\n";
         }
         else
         {
            $favoriteButton = ' &nbsp;<img id="buttonFavourite" class="topicIcon" src="'.$webRootPath.'res_icons/title_favourite.png" alt="Ajouter" ';
            $favoriteButton .= 'data-id-topic="'.$data['id_topic'].'" title="Ajouter aux favoris"/>'."\n";
         }
      
         if(Utils::check(LoggedUser::$data['can_delete']))
         {
            $deletionButton .= ' &nbsp;<img id="buttonDelete" class="topicIcon" src="'.$webRootPath.'res_icons/title_delete.png" alt="Supprimer" ';
            $deletionButton .= 'title="Supprimer ce sujet"/>'."\n";
         }
      
         if(Utils::check($data['is_locked']))
         {
            if(Utils::check(LoggedUser::$data['can_lock']))
            {
               $lockingButton .= ' &nbsp;<img id="buttonLock" class="topicIcon" src="'.$webRootPath.'res_icons/title_unlock.png" alt="Déverrouiller" ';
               $lockingButton .= 'title="Déverrouiller ce sujet"/>'."\n";
            }
            else
            {
               $lockingButton = ' &nbsp;<img class="topicIcon" src="'.$webRootPath.'res_icons/title_lock.png" alt="Verrouillé" ';
               $lockingButton .= 'title="Ce sujet est verrouillé"/>'."\n";
            }
         }
         else if(Utils::check(LoggedUser::$data['can_lock']))
         {
            $lockingButton .= ' &nbsp;<img id="buttonLock" class="topicIcon" src="'.$webRootPath.'res_icons/title_lock.png" alt="Verrouiller" ';
            $lockingButton .= 'title="Verrouiller ce sujet"/>'."\n";
         }
      }
      else if(Utils::check($data['is_locked']))
      {
         $lockingLink = ' &nbsp;<img class="topicIcon" src="'.$webRootPath.'res_icons/title_lock.png" alt="Verrouillé" title=';
         $lockingLink .= '"Ce sujet est verrouillé"/>'."\n";
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
            $link = $webRootPath.'Search.php?keywords='.urlencode($keywords[$i]['tag']);
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
