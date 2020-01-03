<?php

class ArticleThumbnailIR
{
   /*
   * Converts the array modelizing an article into an intermediate representation, ready to be used in
   * an template. The intermediate representation is a new array containing:
   *
   * -ID of the article
   * -Thumbnail of the article
   * -Type of the article (as a string)
   * -Link to the article (either published, either edition page; URL)
   * -Additionnal info to be displayed on link hover (HTML)
   * -Main title of the article (as a string)
   * -Subtitle (as a string)
   *
   * @param mixed $data[]  The article itself (obtained with method getAll() from Article class)
   * @param bool $edition  Set to true if displayed on "My Articles" page
   * @param mixed[]        The intermediate representation
   */

   public static function process($data, $edition = false)
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
      'link' => '', 
      'additionnalInfo' => '', 
      'title' => $data['title'], 
      'subtitle' => $data['subtitle']);
      
      $typeTranslation = array('review' => 'Critique', 'preview' => 'Aperçu', 'opinion' => 'Humeur');
      if($edition)
      {
         if($data['date_publication'] !== '1970-01-01 00:00:00')
            $output['type'] = '<p>'.$typeTranslation[$data['type']].' <span style="color: #40ff00;">[Publié]</span></p>';
         else
            $output['type'] = '<p>'.$typeTranslation[$data['type']].' <span style="color: #36DDFF;">[En cours]</span></p>';
      }
      else
         $output['type'] = '<p>'.$typeTranslation[$data['type']].'</p>';
      
      if($edition)
      {
         $output['link'] = PathHandler::HTTP_PATH().'EditArticle.php?id_article='.$data['id_article'];
      }
      else
      {
         $output['link'] = PathHandler::articleURL($data);
         $output['additionnalInfo'] = ' title="Ecrit par '.$data['pseudo'].' et publié le ';
         $output['additionnalInfo'] .= date('d/m/Y \à H\hi', Utils::toTimestamp($data['date_publication']));
         $output['additionnalInfo'] .= '"';
      }
      
      return $output;
   }
}

?>
