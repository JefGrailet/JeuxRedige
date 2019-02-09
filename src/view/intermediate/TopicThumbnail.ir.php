<?php

class TopicThumbnailIR
{
   /*
   * Converts the array modelizing a topic into an intermediate representation, ready to be used 
   * in a template. The intermediate representation contains (in order of "call" in the template):
   *
   * -Absolute path to the thumbnail (picture) of the topic
   * -The icons describing the content of that topic (HTML)
   * -Author and date of the last message posted on that topic
   * -Marking of that topic (string appending a CSS class name; can be empty)
   * -Link to the topic (URL)
   * -Title, author of the topic
   * -Link to the last page of the topic (URL)
   *
   * @param mixed $topic[]     The topic itself (obtained with method getAll() from Topic class)
   * @param mixed[]            The intermediate representation
   */

   public static function process($topic)
   {
      $output = array('thumbnail' => PathHandler::getTopicThumbnail($topic['thumbnail'], $topic['id_topic']),
      'icons' => '',
      'lastAuthor' => $topic['last_author'],
      'lastPostDate' => date('d/m \à H\hi', Utils::toTimestamp($topic['last_post'])),
      'marked' => '',
      'linkTopic' => PathHandler::topicURL($topic),
      'author' => $topic['author'],
      'title' => $topic['title'],
      'linkLastPage' => '');
      
      // Checks the topic is favorited
      $favorited = false;
      if(array_key_exists('favorite', $topic) && Utils::check($topic['favorite']))
         $favorited = true;

      // Deals with icons
      if($topic['created_as'] === 'author')
         $output['icons'] .= '<img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_content.png" alt="Réactions à un article" title="Réactions à un article"/> ';
      if(Utils::check($topic['is_anon_posting_enabled']))
         $output['icons'] .= '<img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_anon_posting.png" alt="Posts anonymes autorisés" title="Posts anonymes autorisés"/> ';
      if(Utils::check($topic['is_locked']))
         $output['icons'] .= '<img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_locked.png" alt="Sujet verrouillé" title="Sujet verrouillé"/> ';
      if($favorited)
         $output['icons'] .= '<img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_favourite.png" alt="Sujet favori" title="Sujet favori"/> ';

      /*
      To be used later ?
      if(LoggedUser::isLoggedIn())
      {
         if(yes(LoggedUser::$data['can_edit_others']) OR LoggedUser::$data['pseudo'] === $topic['author'] OR LoggedUser::$data['used_pseudo'] === $topic['author'])
         {
            $icons .= '<a href="editer_sujet.php?id_topic='.$topic['id_topic'].'"><img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_title_edit.png" alt="Edition du sujet" title="Editer ce sujet"/></a> ';
            
            // $icons .= '<a href="supprimer_sujet.php?id_topic='.$topic['id_topic'].'"><img src="'.PathHandler::HTTP_PATH.'res_icons/thumbnail_delete.png" alt="Supprimer ce sujet" title="Supprimer ce sujet"/></a> ';
         }
      }
      */
      
      // Wraps icons into the proper div
      if(strlen($output['icons']) > 0)
      {
         $output['icons'] = substr($output['icons'], 0, -1);
         $output['icons'] = '<div class="thumbnailIcons">
            '.$output['icons'].'
         </div>';
      }
      
      // Marking
      if(Utils::check($topic['is_marked']))
         $output['marked'] = 'Marked';
      
      // Link to the last page
      $lastPage = ceil($topic['nb'] / WebpageHandler::$miscParams['posts_per_page']);
      if($lastPage > 1)
         $output['linkLastPage'] = '<a class="lastPage" href="'.PathHandler::topicURL($topic, $lastPage).'">[p. '.$lastPage.']</a>';
      
      // If there's a view for this user, appends a count to the "linkLastPage" field
      if(array_key_exists('last_seen', $topic))
      {
         $newMessages = $topic['nb'] - $topic['last_seen'];
         if($newMessages > 0)
            $output['linkLastPage'] .= ' <span style="color: rgb(0,255,0);">[+'.$newMessages.']</span>';
      }
      
      return $output;
   }
}

?>
