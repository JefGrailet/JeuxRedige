<?php

class ArticleThumbnailIR
{
   /*
   * Converts the array modelizing an article into an intermediate representation, ready to be used in
   * an template. The intermediate representation is a new array containing:
   *
   * -ID of the article
   * -Thumbnail of the article
   * -Type of the article as in the DB (as a string)
   * -Type of the article, translated for the display (as a string)
   * -An "extra" switch for displaying the author or the state of the publication
   * -Link to the article (either published, either edition page; URL)
   * -Additionnal info to be displayed on link hover (HTML)
   * -Main title of the article (as a string)
   * -Subtitle (as a string)
   *
   * @param mixed $data[]  The article itself (obtained with method getAll() from Article class)
   * @param bool $edition  Set to true if displayed on "My Articles" page
   * @param mixed[]        The intermediate representation
   */

   public static function process($data, $edition = false, $showAuthor = true)
   {
      $artThumbnail = '';
      $relativePath = 'upload/articles/'.$data['id_article'].'/thumbnail.jpg';
      $thumbnailPath = PathHandler::WWW_PATH().$relativePath;
      if(file_exists($thumbnailPath) == true)
         $artThumbnail = PathHandler::HTTP_PATH().$relativePath;
      else
         $artThumbnail = PathHandler::HTTP_PATH().'default_article_thumbnail.jpg';
      
      $output = array('ID' => $data['id_article'], 
      'thumbnail' => $artThumbnail, 
      'type' => '', 
      'typeTranslated' => '',
      'extra' => '', 
      'link' => '', 
      'additionnalInfo' => '', 
      'title' => $data['title'], 
      'subtitle' => $data['subtitle']);
      
      // Type of article
      $typeTranslation = array('review' => 'Critique', 
      'preview' => 'Aperçu', 
      'opinion' => 'Humeur', 
      'chronicle' => 'Chronique', 
      'guide' => 'Guide');
      
      if(in_array($data['type'], array_keys($typeTranslation)))
      {
         $output['type'] = ' '.$data['type'];
         $output['typeTranslated'] = $typeTranslation[$data['type']];
      }
      else
      {
         $output['typeTranslated'] = 'Article'; // Default (in case)
      }
      
      /* 
       * Extra details: publication state (author's side) or author (for published content or 
       * editorial review).
       */
      
      if($edition)
      {
         if($showAuthor)
         {
            $output['extra'] = 'author||'.$data['pseudo'];
         }
         else
         {
            if($data['date_publication'] !== '1970-01-01 00:00:00')
               $output['extra'] = 'state|| published|Publié';
            else
               $output['extra'] = 'state|| wip|En cours';
         }
      }
      else
      {
         if($showAuthor)
            $output['extra'] = 'author||'.$data['pseudo'];
      }
      
      if($edition)
      {
         $output['link'] = PathHandler::HTTP_PATH().'EditArticle.php?id_article='.$data['id_article'];
      }
      else
      {
         $output['link'] = PathHandler::articleURL($data);
         $output['additionnalInfo'] = ' title="Publié le ';
         $output['additionnalInfo'] .= date('d/m/Y \à H\hi', Utils::toTimestamp($data['date_publication']));
         $output['additionnalInfo'] .= '"';
      }
      
      return $output;
   }
}

?>
