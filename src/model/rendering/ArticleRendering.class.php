<?php

/**
* Static class handling the parts of article rendering that relies on PHP functions (e.g., 
* functions to check the file system) or global elements of the PHP code.
*/

class ArticleRendering
{
   /**
    * Returns the full URL to the thumbnail of an article if it exists, given its ID, and an empty 
    * string otherwise.
    *
    * @param integer $ID  ID of the article
    * @return string      The absolute path to the thumbnail picture (empty string if none)
    */

   public static function getThumbnail($ID)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/articles/'.$ID.'/thumbnail.jpg';
      if(file_exists($thumbnailFile))
         return PathHandler::HTTP_PATH().'upload/articles/'.$ID.'/thumbnail.jpg';
      return "";
   }

   /**
    * Returns the full URL to the highlight image of an article if it exists, given its ID, and an 
    * empty string otherwise.
    *
    * @param integer $ID  ID of the article
    * @return string      The absolute path to the highlight image (empty string if none)
    */

   public static function getHighlight($ID)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/articles/'.$ID.'/highlight.jpg';
      if(file_exists($thumbnailFile))
         return PathHandler::HTTP_PATH().'upload/articles/'.$ID.'/highlight.jpg';
      return "";
   }

   /**
    * Returns the full URL to the header image of a segment if it exists, given the article ID and 
    * the segment ID. Returns a placeholder if such image doesn't exist.
    *
    * @param integer $articleID   ID of the article
    * @param integer $segmentID   ID of the segment
    * @return string              The absolute path to the header image (placeholder if none)
    */

   public static function getSegmentHeader($articleID, $segmentID)
   {
   	  $suffix = $articleID.'/'.$segmentID.'/header.jpg';
      $headerFile = PathHandler::WWW_PATH().'upload/articles/'.$suffix;
      if(file_exists($headerFile))
         return PathHandler::HTTP_PATH().'upload/articles/'.$suffix;
      return PathHandler::HTTP_PATH().'default_article_header.jpg';
   }
}
