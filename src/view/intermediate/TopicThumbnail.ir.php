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
      $webRootPath = PathHandler::HTTP_PATH();
      
      $output = array('thumbnail' => PathHandler::getTopicThumbnail($topic['thumbnail'], $topic['id_topic']),
      'icons' => '',
      'lastAuthor' => $topic['last_author'],
      'lastPostDate' => date('d/m/y \à H\hi', Utils::toTimestamp($topic['last_post'])),
      'marked' => '',
      'linkTopic' => PathHandler::topicURL($topic),
      'fullTitle' => 'Sujet créé par '.$topic['author'],
      'title' => $topic['title'],
      'linkLastPage' => '');
      
      // Shortens the title for display purpose (full title can be seen via tooltip)
      if(strlen($topic['title']) > 50)
      {
         $output['fullTitle'] = $topic['title'].' (par '.$topic['author'].')';
         $output['title'] = substr($topic['title'], 0, 47).'...';
      }
      
      // Checks the topic is favorited
      $favorited = false;
      if(array_key_exists('favorite', $topic) && Utils::check($topic['favorite']))
         $favorited = true;

      // Deals with icons
      if($topic['created_as'] === 'author')
         $output['icons'] .= '<i class="icon-general_content" alt="Réactions à un article" title="Réactions à un article"></i> ';
      if(Utils::check($topic['is_anon_posting_enabled']))
         $output['icons'] .= '<i class="icon-general_anonymous" alt="Posts anonymes autorisés" title="Posts anonymes autorisés"></i> ';
      if(Utils::check($topic['is_locked']))
         $output['icons'] .= '<i class="icon-topic_lock" alt="Sujet verrouillé" title="Sujet verrouillé"></i> ';
      if($favorited)
         $output['icons'] .= '<i class="icon-general_star" alt="Sujet favori" title="Sujet favori"></i> ';
      
      // Wraps icons into the proper div
      if(strlen($output['icons']) > 0)
      {
         $output['icons'] = substr($output['icons'], 0, -1);
         $output['icons'] = '<div class="thumbnailIcons">
            <p>'.$output['icons'].'</p>
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
