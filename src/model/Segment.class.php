<?php

/**
* Segment class models an individual segment from an article. Like Article class, an instance of 
* Segment corresponds to a row in the "articles_segments" table in the database. The single field 
* of the class is an array with the same fields/values as in the database. Methods allows the 
* calling code to handle a segment without explicitely addressing the database, in a high-level 
* fashion. A static method can be used to create (insert) a new segment.
*/

class Segment
{
   protected $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the segment or the ID of that segment
   * @throws Exception    If the segment cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $this->_data = Database::secureRead("SELECT * FROM articles_segments WHERE id_segment=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Segment does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Segment could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new segment in the database.
   *
   * @param integer $articleID  ID of the parent article
   * @param string $title       Title of the new segment
   * @param boolean $header     True if the segment has a header (picture) by this time
   * @param integer $pos        The position of the segment within the structure of the article
   * @param string $content     Content of the segment (HTML)
   * @return Segment            The new entry as a Segment instance
   * @throws Exception          When the insertion of the segment in the database fails, with the 
   *                            actual SQL error inside
   */
   
   public static function insert($articleID, $title, $pos, $content)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('Article creation is reserved to registered and logged users.');

      $toInsert = array('parent' => $articleID, 
      'author' => LoggedUser::$data['pseudo'], 
      'title' => $title, 
      'position' => $pos, 
      'content' => $content);
      
      $sql = "INSERT INTO articles_segments VALUES('0', :parent, :author, :title, :position, 
      :content, NULL, '1970-01-01 00:00:00')";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new segment: '. $res[2]);
         
      $newSegmentID = Database::newId();
      
      // Segments have their own folders too
      $segmentDir = PathHandler::WWW_PATH().'upload/articles/'.$articleID.'/'.$newSegmentID;
      mkdir($segmentDir, 0711);
      
      return new Segment($newSegmentID);
   }
   
   // Just like in Article, method to test if a segment belongs to the current user.
   
   public function isMine()
   {
      if(!LoggedUser::isLoggedIn())
         return false;
      
      if(LoggedUser::$data['pseudo'] === $this->_data['pseudo'])
         return true;
      return false;
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Gets the header image for this segment. If there is no such header, the method returns an 
   * empty string.
   *
   * @return string  The absolute path to the header
   */
   
   public function getHeader()
   {
      $headerFile = PathHandler::WWW_PATH().'upload/articles/'.$this->_data['id_article'].'/'.$this->_data['id_segment'].'/header.jpg';
      if(file_exists($headerFile))
      {
         $URL = PathHandler::HTTP_PATH().'upload/articles/'.$this->_data['id_article'].'/'.$this->_data['id_segment'].'/header.jpg';
         return $URL;
      }
      return "";
   }
   
   /*
   * Updates the segment (title and content). A third boolean parameter tells if we should set the 
   * field "last_modification_date", which is only relevant for post-publication modifications.
   *
   * @param string $newTitle    The new title of the segment
   * @param string $newContent  The new content
   * @param bool $registerDate  True if the date of modification should be recorded
   * @throws Exception          If anything goes wrong during the update (SQL error provided)
   */
   
   public function update($newTitle, $newContent, $registerDate)
   {
      $newLastDate = '1970-01-01 00:00:00';
      if($registerDate)
      {
         $newLastDate = Utils::toDatetime(Utils::SQLServerTime());
         $sql = 'UPDATE articles_segments 
         SET title=?, content=?, date_last_modification=? 
         WHERE id_segment=?';
         $arg = array($newTitle, $newContent, $newLastDate, $this->_data['id_segment']);
      }
      else
      {
         $sql = 'UPDATE articles_segments SET title=?, content=? WHERE id_segment=?';
         $arg = array($newTitle, $newContent, $this->_data['id_segment']);
      }
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Segment could not be updated: '. $res[2]);
      
      $this->_data['title'] = $newTitle;
      $this->_data['content'] = $newContent;
      $this->_data['date_last_modification'] = $newLastDate;
   }
   
   /*
   * Method to finalize the segment, which consists in set/update its attachment and a slight 
   * modification of its text (replace some file path prefixes) if necessary. The principles are 
   * the same as for the finalize() method of Post.class.php, but slightly simplified.
   *
   * @param string $newAttachment  The attachment (already formatted)
   * @param string $newContent     The updated content (optional)
   * @throws Exception             When the update of the segment in the database fails, with the
   *                               actual SQL error inside
   */
   
   public function finalize($newAttachment, $newContent='')
   {
      $sql = "UPDATE articles_segments SET attachment=?";
      $arg = array($newAttachment);
      if(strlen($newContent) > 0)
      {
         $sql .= ", content=?";
         array_push($arg, $newContent);
      }
      $sql .= " WHERE id_segment=?";
      array_push($arg, $this->_data['id_segment']);
      
      $res = Database::secureWrite($sql, $arg);

      if($res != NULL)
         throw new Exception('Segment could not be updated: '. $res[2]);

      $this->_data['attachment'] = $newAttachment;
      if(strlen($newContent) > 0)
         $this->_data['content'] = $newContent;
   }
   
   /*
   * Resets the position of this segment within its parent article structure. In practice, this 
   * just consists in updating the "position" field and nothing else.
   *
   * @param integer $newPos  The new position of the segment
   * @throws Exception       If anything goes wrong during the update (SQL error provided)
   */
   
   public function changePosition($newPos)
   {
      $changedTitle = false;
      if($this->_data['position'] == 1 && $this->_data['title'] == NULL)
      {
         $sql = 'UPDATE articles_segments SET title=?, position=? WHERE id_segment=?';
         $arg = array('Sans titre', $newPos, $this->_data['id_segment']);
         $changedTitle = true;
      }
      else
      {
         $sql = 'UPDATE articles_segments SET position=? WHERE id_segment=?';
         $arg = array($newPos, $this->_data['id_segment']);
      }
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Segment could not be updated: '. $res[2]);
      
      if($changedTitle)
         $this->_data['title'] = 'Sans titre';
      $this->_data['position'] = $newPos;
   }
   
   /*
   * Deletes the segment and its uploads. To perform the operation properly, this method does a 
   * SQL transaction to both delete the segment and updates the position of subsequent segments in 
   * the structure of the parent article.
   *
   * @throws Exception  If deletion could not be carried out in the DB (SQL error provided)
   */
   
   public function delete()
   {
      Database::beginTransaction();
      
      $res1 = Database::secureWrite("DELETE FROM articles_segments WHERE id_segment=?",
                         array($this->_data['id_segment']), true);
      
      if(is_array($res1))
      {
         Database::rollback();
         throw new Exception('Unable to delete segment '.$this->_data['id_segment'].' : '. $res1[2]);
      }
      
      $sql = 'UPDATE articles_segments SET position=position-1 WHERE id_article=? && position>?';
      $arg = array($this->_data['id_article'], $this->_data['position']);
      $res2 = Database::secureWrite($sql, $arg);
      
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Next segments could not be updated: '. $res2[2]);
      }
      
      Database::commit();
      
      // Deletion of uploads
      $topicDirPath = PathHandler::WWW_PATH().'upload/articles/'.$this->_data['id_article'].'/'.$this->_data['id_segment'];
      if(file_exists($topicDirPath))
      {
         $dirContent = scandir($topicDirPath.'/');
         for($i = 0; $i < count($dirContent); $i++)
         {
            if($dirContent[$i] !== '.' && $dirContent[$i] !== '..')
               unlink($topicDirPath.'/'.$dirContent[$i]);
         }
         rmdir($topicDirPath);
      }
   }
}
?>
