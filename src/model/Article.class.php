<?php

/**
* Article class models an article. Like User class, an instance of Article corresponds to a row in
* the "articles" table in the database. The main field of the class is an array with the same
* fields/values as in the database. Methods allows the calling code to handle an article without
* explicitely addressing the database, in a high-level fashion. A static method can be used to 
* create (insert) a new article. Like topics, additionnal fields are used to buffer data related 
* to the article, such as tags and segments.
*/

class Article
{
   protected $_data;
   
   // Related data fields (not loaded by constructor, see additionnal data section)
   protected $_keywords;
   protected $_segments;
   
   protected $_topic; // Exists only after publication
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the article or the ID of that article
   * @throws Exception    If the article cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $this->_data = Database::secureRead("SELECT * FROM articles WHERE id_article=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Article does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Article could not be found: '. $this->_data[2]);
      }
      
      $this->_keywords = null;
      $this->_segments = null;
      $this->_topic = null;
   }
   
   /*
   * Static method to insert a new article in the database.
   *
   * @param string $title     Title of the new article
   * @param string $subtitle  Subtitle
   * @param string $type      Type of the article ('review', 'preview' or 'opinion' for now)
   * @return Article          The new entry as an Article instance
   * @throws Exception        When the insertion of the article in the database fails, with the 
   *                          actual SQL error inside
   */
   
   public static function insert($title, $subtitle, $type)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('Article creation is reserved to registered and logged users.');

      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $toInsert = array('author' => LoggedUser::$data['pseudo'], 
      'title' => $title, 
      'subtitle' => $subtitle, 
      'type' => $type, 
      'date' => $currentDate);
      
      $sql = "INSERT INTO articles VALUES('0', :author, :title, :subtitle, :type, '0', :date, 
      '1970-01-01 00:00:00', '1970-01-01 00:00:00', 'no', 0)";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new article: '. $res[2]);
         
      $newArticleID = Database::newId();
       
      $articleDir = PathHandler::WWW_PATH().'upload/articles/'.$newArticleID;
      mkdir($articleDir, 0711);
      
      return new Article($newArticleID);
   }
   
   /*
   * Methods to test if the article is published or if the current (logged) user is the author 
   * (and therefore, allowed to handle it). No parameter is required and both methods return a 
   * boolean value.
   */
   
   public function isPublished()
   {
      $timestamp = Utils::toTimestamp($this->_data['date_publication']);
      if($timestamp > 0)
         return true;
      return false;
   }
   
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
   * Gets the thumbnail image for this article. If there is no such thumbnail, the method returns 
   * an empty string.
   *
   * @param bool $local  Optional boolean to set to true if the calling code rather needs the path 
   *                     in the file system (vs. "HTTP" path that can be inserted in HTML code)
   * @return string      The absolute path to the thumbnail
   */
   
   public function getThumbnail($local = false)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/articles/'.$this->_data['id_article'].'/thumbnail.jpg';
      if(file_exists($thumbnailFile))
      {
         if(!$local)
         {
            $URL = PathHandler::HTTP_PATH().'upload/articles/'.$this->_data['id_article'].'/thumbnail.jpg';
            return $URL;
         }
         return $thumbnailFile;
      }
      return "";
   }
   
   /*
   * Gets the highlight image used for this article to feature it in homepage. If there is no such 
   * image, the method returns an empty string.
   *
   * @return string  The absolute path to the highlight picture
   */
   
   public function getHighlight()
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/articles/'.$this->_data['id_article'].'/highlight.jpg';
      if(file_exists($thumbnailFile))
      {
         $URL = PathHandler::HTTP_PATH().'upload/articles/'.$this->_data['id_article'].'/highlight.jpg';
         return $URL;
      }
      return "";
   }
   
   /*
   * Does the same thing as above, but with the ID of the article as a parameter of a static 
   * method (avoids creating Article objects in index.php).
   *
   * @param integer $ID  ID of the article
   * @return string      The absolute path to the highlight picture
   */
   
   public static function getHighlightStatic($ID)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/articles/'.$ID.'/highlight.jpg';
      if(file_exists($thumbnailFile))
      {
         $URL = PathHandler::HTTP_PATH().'upload/articles/'.$ID.'/highlight.jpg';
         return $URL;
      }
      return "";
   }
   
   /*
   * ------------------------
   * ADDITIONNAL DATA SECTION
   * ------------------------
   *
   * The next methods aim at loading all data related to the article: keywords and associated 
   * games and the segments constituting the content of the article. In some pages, everything 
   * needs to be loaded. A unique method is dedicated to this particular task (the data is 
   * buffered with additionnal fields), which can be checked and retrieved with additionnal 
   * methods.
   */
   
   /*
   * Method to retrieve the set of keywords associated to this article. No parameter is required. 
   * The result array also contains game information if the keyword is matching a game entry.
   *
   * @return string[][]  The keywords associated with the article as a 2D array; keywords 
   *                     associated with games have additionnal fields; an empty array is returned 
   *                     when there is no keyword
   * @throws Exception   If the keywords could not be fetched (with the SQL error inside)
   */

   public function getKeywords()
   {
      $sql = "SELECT map_tags_articles.tag, games.genre, games.publisher, games.developer, 
      games.publication_date, games.hardware 
      FROM map_tags_articles 
      LEFT OUTER JOIN games 
      ON games.tag = map_tags_articles.tag 
      WHERE map_tags_articles.id_article=? ORDER BY map_tags_articles.tag";
   
      $res = Database::secureRead($sql, array($this->_data['id_article']));
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot get keywords and associated games: '. $res[2]);
      else if($res == NULL)
      {
         $this->_keywords = array(); // Shows that some request was addressed to the DB
         return $this->keywords;
      }
      
      $this->_keywords = $res;
      return $res;
   }
   
   /*
   * Gets the segments, i.e., the individual "pages" of the article. No parameter is required.
   *
   * @return mixed[]    The resulting 2D array, with a different segment per cell. Segments are 
   *                    ordered according to their respective position in the article.
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getSegments()
   {
      $sql = "SELECT * FROM articles_segments WHERE id_article=? ORDER BY position";
      $res = Database::secureRead($sql, array($this->_data['id_article']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot get segments: '. $res[2]);
      else if($res == NULL)
      {
         $this->_segments = array(); // Shows that some request was addressed to the DB
         return $this->_segments;
      }
      
      $this->_segments = $res;
      return $res;
   }
   
   /*
   * Gets the related topic, i.e., the topic containing reactions to the article. It only exists 
   * after publication. No parameter is required.
   *
   * @return mixed[]    The resulting array (a single topic)
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getTopic()
   {
      if($this->_data['related_topic'] == 0)
      {
         $this->_topic = array();
         return $this->_topic;
      }
      
      $sql = "SELECT topics.*, (SELECT COUNT(*) FROM posts WHERE id_topic=topics.id_topic) AS nb FROM topics WHERE id_topic=?";
      $res = Database::secureRead($sql, array($this->_data['related_topic']), true);
      
      if($res !== NULL && count($res) == 3)
         throw new Exception('Cannot get the related topic: '. $res[2]);
      else if($res == NULL)
      {
         $this->_topic = array();
         return $this->_topic;
      }
      
      $this->_topic = $res;
      return $res;
   }
   
   /*
   * Performs in a row getKeywords() and getSegments(). If an exception arises, the method stops 
   * and rethrows it. It returns nothing (results are buffered). It should be noted that it does 
   * load the related topic, because 1) it only exists after publication 2) it is only relevant 
   * for article display, not article edition.
   *
   * @param bool $fullSegments  True if we retrieve the whole segments or just enough data f
   * @throws Exception          If an SQL error occurs while checking the database (rethrown exception)
   */
   
   public function loadRelatedData()
   {
      try
      {
         $this->getKeywords();
         $this->getSegments();
      }
      catch(Exception $e)
      {
         throw $e;
      }
   }
   
   // Next methods are only there for accessing and handling the related data.
   public function getBufferedKeywords() { return $this->_keywords; }
   public function getBufferedSegments() { return $this->_segments; }
   public function getBufferedTopic() { return $this->_topic; }
   
   /*
   * Gets the keywords and formats them in a linear array. The associated game data (if any) is 
   * removed in the process.
   *
   * @return string[]   The keywords associated with the article
   */

   public function getKeywordsSimple()
   {
      if($this->_keywords == null)
         return array();
   
      $result = array();
      for($i = 0; $i < count($this->_keywords); $i++)
         array_push($result, $this->_keywords[$i]['tag']);
      
      return $result;
   }
   
   /*
   * Checks that there are games among the keywords.
   *
   * @return boolean  True if one or several keywords have an associated game
   */
   
   public function hasGames()
   {
      if($this->_keywords == null)
         return false; // Should never occur if the class is correctly used
   
      for($i = 0; $i < count($this->_keywords); $i++)
         if($this->_keywords[$i]['genre'] !== NULL)
            return true;
      return false;
   }
   
   /*
   * Returns the next position for a future segment, using the size of the _segments array.
   *
   * @return integer    Position of the next segment in the structure
   * @throws Exception  If a request was used in the process, an exception can be thrown if there 
   *                    was a problem with the DB (SQL error provided)
   */
   
   public function nextSegmentPosition()
   {
      if($this->_segments == null)
      {
         $sql = 'SELECT COUNT(*) AS nb FROM articles_segments WHERE id_article=?';
         $res = Database::secureRead($sql, array($this->_data['id_article']), true);
         
         if(count($res) == 3)
            throw new Exception('Segments could not be counted: '. $res[2]);
      
         return $res['nb'] + 1;
      }
      return count($this->_segments) + 1;
   }
   
   /*
   * -------------------------------
   * END OF ADDITIONNAL DATA SECTION
   * -------------------------------
   */
   
   /*
   * Updates the main entry of the article (title, subtitle, type). Returns nothing.
   *
   * @param string $title     New title
   * @param string $subtitle  New subtitle
   * @param string $type      Type of the article ('review' or 'opinion' for now)
   * @throws Exception        When the update fails, with the actual SQL error inside
   */
   
   public function update($title, $subtitle, $type)
   {
      $sql = 'UPDATE articles SET title=?, subtitle=?, type=? WHERE id_article=?';
      $arg = array($title, $subtitle, $type, $this->_data['id_article']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Article could not be updated: '. $res[2]);
      
      $this->_data['title'] = $title;
      $this->_data['subtitle'] = $subtitle;
      $this->_data['type'] = $type;
   }
   
   /*
   * Updates the main entry of the article for publication. This consists in setting the 
   * publication date and the "related_topic" field (ID of the topic collecting the reactions to 
   * the article).
   *
   * @param integer $topicID  ID of the topic with the reactions
   * @throws Exception        When the publication fails, with the actual SQL error inside
   */
   
   public function publish($topicID)
   {
      if($this->_data['date_publication'] !== '1970-01-01 00:00:00')
         return;
      
      $publicationDate = Utils::toDatetime(Utils::SQLServerTime());
      $sql = 'UPDATE articles SET date_publication=?, related_topic=? WHERE id_article=?';
      $arg = array($publicationDate, $topicID, $this->_data['id_article']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Article could not be updated: '. $res[2]);
      
      $this->_data['date_publication'] = $publicationDate;
      $this->_data['related_topic'] = $topicID;
   }
   
   /*
   * Aligns "date_last_modifications" on some provided date, which is supposed to be the last 
   * modification date of some segment.
   *
   * @param string $lastDate  Last modification date of some segment of the article
   * @throws Exception        When the update fails (actual SQL error provided)
   */
   
   public function recordDate($lastDate)
   {
      $sql = 'UPDATE articles SET date_last_modifications=? WHERE id_article=?';
      $arg = array($lastDate, $this->_data['id_article']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Article could not be updated: '. $res[2]);
      
      $this->_data['date_last_modifications'] = $lastDate;
   }
   
   /*
   * Deletes the article and all the related uploads. As the deletion DB-wise is very simple 
   * thanks to cascading with InnoDB, the challenge here is to remove the upload folder for that 
   * article.
   *
   * @throws Exception  If deletion could not be carried out in the DB (SQL error provided)
   */
   
   public function delete()
   {
      $res = Database::secureWrite("DELETE FROM articles WHERE id_article=?", array($this->_data['id_article']), true);
      if(is_array($res))
         throw new Exception('Unable to delete article '.$this->_data['id_article'].' : '. $res[2]);
      
      // Deletion of all uploads
      $articleDirPath = PathHandler::WWW_PATH().'upload/articles/'.$this->_data['id_article'];
      if(file_exists($articleDirPath))
      {
         $dirContent = scandir($articleDirPath.'/');
         for($i = 0; $i < count($dirContent); $i++)
         {
            if($dirContent[$i] === '.' || $dirContent[$i] === '..')
               continue;
            
            $curItem = $articleDirPath.'/'.$dirContent[$i];
            if(is_dir($curItem))
            {
               $subdirContent = scandir($curItem.'/');
               for($j = 0; $j < count($subdirContent); $j++)
               {
                  if($subdirContent[$j] !== '.' && $subdirContent[$j] !== '..')
                     unlink($curItem.'/'.$subdirContent[$j]);
               }
               rmdir($curItem);
            }
            else
            {
               unlink($curItem);
            }
         }
         rmdir($articleDirPath);
      }
   }
   
   /*
   * Simple method to update the "views" counter for the article. It is only incremented if: 
   * -the article has been published, 
   * -the current user is NOT the author of the article.
   * No parameter is required, nothing is returned.
   *
   * @throws Exception  If anything goes wrong while updating the counter (SQL error provided)
   */
   
   public function incViews()
   {
      if($this->_data['date_publication'] === '1970-01-01 00:00:00')
         return;
      
      if(LoggedUser::isLoggedIn() && LoggedUser::$data['pseudo'] === $this->_data['pseudo'])
         return;
      
      $sql = 'UPDATE articles SET views=views+1 WHERE id_article=?';
      $arg = array($this->_data['id_article']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Article could not be updated: '. $res[2]);
      
      $this->_data['views'] += 1;
   }
   
   /*
   * Switches the "featured" field. No parameter is required. Return value tells if the article is 
   * now featured (=true) or not (=false). Nothing happens if the article is not published yet.
   *
   * @return boolean    True if the article is now featured, false otherwise
   * @throws Exception  If anything goes wrong while updating the article (SQL error provided)
   */
   
   public function feature()
   {
      if($this->_data['date_publication'] === '1970-01-01 00:00:00')
         return;
      
      $newValue = 'yes';
      if(Utils::check($this->_data['featured']))
         $newValue = 'no';
      
      $sql = 'UPDATE articles SET featured=? WHERE id_article=?';
      $arg = array($newValue, $this->_data['id_article']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Article could not be updated: '. $res[2]);
      
      $this->_data['featured'] = $newValue;
      if(Utils::check($newValue))
         return true;
      return false;
   }
   
   /*
   * Static method to count the total number of (un)published articles. By default, this method 
   * counts how many published articles there are, but an optional boolean parameter can be used 
   * to rather count the number of unpublished articles (useful for editorial purposes).
   *
   * @param string $category    String to count articles of a specific category; empty string 
   *                            means all articles will be considered (optional; empty by default)
   * @param boolean $published  Set to false for unpublished articles (optional; true by default)
   * @return integer            The total number of articles (published or unpublished) in the DB
   * @throws Exception          If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countArticles($category = '', $published = true)
   {
      $specificCategory = false;
      if (strlen($category) > 0 && in_array($category, array_keys(Utils::ARTICLES_CATEGORIES)))
         $specificCategory = true;
      
      $sql = 'SELECT COUNT(*) AS nb FROM articles WHERE ';
      if ($specificCategory)
         $sql .= 'type = ? && ';
      if ($published)
         $sql .= 'date_publication > \'1970-01-01 00:00:00\'';
      else
         $sql .= 'date_publication = \'1970-01-01 00:00:00\'';
      if ($specificCategory)
         $res = Database::secureRead($sql, array($category), true);
      else
         $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Articles could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the total amount of articles written by the current user.
   *
   * @return integer    The total amount of articles recorded in the database for this user
   * @throws Exception  If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countMyArticles()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
   
      $sql = 'SELECT COUNT(*) AS nb FROM articles WHERE pseudo=?';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(count($res) == 3)
         throw new Exception('Articles could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to obtain a set of articles. Like countArticles(), the method can be used to 
   * retrieve either published or unpublished articles via an optional parameter (by default, it 
   * seeks published articles). Articles are listed by most recent publication date when published 
   * and by most recent creation date otherwise.
   *
   * @param number $first       The index of the first article of the set
   * @param number $nb          The maximum amount of articles to list
   * @param string $category    String to browse articles of a specific category; empty string 
   *                            means all articles will be considered (optional; empty by default)
   * @param boolean $published  Set to false for unpublished articles (optional; true by default)
   * @return mixed[]            The articles that were found
   * @throws Exception          If articles could not be found (SQL error is provided)
   */

   public static function getArticles($first, $nb, $category = '', $published = true)
   {
      $specificCategory = false;
      if (strlen($category) > 0 && in_array($category, array_keys(Utils::ARTICLES_CATEGORIES)))
         $specificCategory = true;
      
      $sql = 'SELECT * FROM articles ';
      if($published)
      {
         $sql .= 'WHERE date_publication > \'1970-01-01 00:00:00\' ';
         if ($specificCategory)
            $sql .= '&& type = ?';
         $sql .= 'ORDER BY date_publication DESC ';
      }
      else
      {
         $sql .= 'WHERE date_publication = \'1970-01-01 00:00:00\' ';
         if ($specificCategory)
            $sql .= '&& type = ?';
         $sql .= 'ORDER BY date_creation DESC ';
      }
      $sql .= 'LIMIT '.$first.','.$nb;
      if ($specificCategory)
         $res = Database::secureRead($sql, array($category));
      else
         $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Same as above but for unpublished articles. Articles are then ordered by the date at which 
   * they were created. Used for editorial features.
   *
   * @param number $first  The index of the first article of the set
   * @param number $nb     The maximum amount of articles to list
   * @return mixed[]       The articles that were found
   * @throws Exception     If articles could not be found (SQL error is provided)
   */

   public static function getUnpublishedArticles($first, $nb)
   {
      $sql = 'SELECT * FROM articles 
      WHERE date_publication=\'1970-01-01 00:00:00\' 
      ORDER BY date_creation DESC 
      LIMIT '.$first.','.$nb;
      $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to obtain a set of published, featured articles. There is no "$nb" parameter as 
   * the method always looks for the most recent articles, and list them by publication date.
   *
   * @param number $nb     The maximum amount of articles to list
   * @return mixed[]       The articles that were found
   * @throws Exception     If articles could not be found (SQL error is provided)
   */
   
   public static function getFeaturedArticles($nb)
   {
      // No criteria on date_publication in WHERE, as featured articles are always published
      $sql = 'SELECT * FROM articles 
      WHERE featured=\'yes\' 
      ORDER BY date_publication DESC 
      LIMIT 0,'.$nb;
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Likewise, static method to obtain a set of articles written by the current user. Articles are 
   * rather listed by creation date.
   *
   * @param number $first  The index of the first article of the set
   * @param number $nb     The maximum amount of articles to list
   * @return mixed[]       The articles that were found (NULL if not logged)
   * @throws Exception     If articles could not be found (SQL error is provided)
   */

   public static function getMyArticles($first, $nb)
   {
      if(!LoggedUser::isLoggedIn())
         return NULL;
   
      $sql = 'SELECT * FROM articles 
      WHERE pseudo=? 
      ORDER BY date_creation DESC 
      LIMIT '.$first.','.$nb;
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Similar method which gets all articles from the user at once, but only providing the ID, 
   * title and subtitle of each article.
   *
   * @return mixed[]       The articles that were found (NULL if not logged)
   * @throws Exception     If articles could not be found (SQL error is provided)
   */
   
   public static function listAllMyArticles()
   {
      if(!LoggedUser::isLoggedIn())
         return NULL;
   
      $sql = 'SELECT id_article, title, subtitle 
      FROM articles 
      WHERE pseudo=? && date_publication != \'1970-01-01 00:00:00\' 
      ORDER BY title';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to count the number of articles matching a set of keywords. A second parameter 
   * indicates whether the calling code wants results to match all keywords or only part of the 
   * set.
   *
   * @param string $keywords[]  The set of keywords to match
   * @param string $category    String to browse articles of a specific category; empty string 
   *                            means all articles will be considered (optional; empty by default)
   * @param bool $strict        True if articles must have all keywords (default), false if they 
   *                            can contain only one keyword of the set (optional)
   * @return number             The amount of articles matching the keywords
   * @throws Exception          If articles could not be found (SQL error is provided) or if 
   *                            $keywords is not an array
   */
   
   public static function countArticlesWithKeywords($keywords, $category = '', $strict = true)
   {
      if(!is_array($keywords))
         throw new Exception('Keywords must be provided as an array');
      
      $sqlInput = array();
      
      $specificCategory = false;
      if (strlen($category) > 0 && in_array($category, array_keys(Utils::ARTICLES_CATEGORIES)))
      {
         $specificCategory = true;
         array_push($sqlInput, $category);
      }
      
      $nbKeywords = count($keywords);
      $toParse = '';
      for($i = 0; $i < $nbKeywords; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $keywords[$i]);
      }
      
      $sql = 'SELECT COUNT(*) AS nb FROM (
      SELECT art.title 
      FROM map_tags_articles art_t, articles art, tags t 
      WHERE art_t.tag = t.tag ';
      if ($specificCategory)
         $sql .= '&& type = ? ';
      $sql .= 'AND art.date_publication != \'1970-01-01 00:00:00\' 
      AND (t.tag IN ('.$toParse.')) 
      AND art.id_article = art_t.id_article 
      GROUP BY art.id_article';
      if($strict)
      {
         $sql .= ' HAVING COUNT( art.id_article )=?';
         array_push($sqlInput, $nbKeywords);
      }
      $sql .= ') res';
      
      $res = Database::secureRead($sql, $sqlInput, true);
      
      if(count($res) == 3)
         throw new Exception('Articles could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to get a set of articles matching a set of keywords. Parameters $first and $nb 
   * are still needed in order to use pages when there are too many results for a single page. A 
   * 4th parameter indicates to the method if the calling code wants results to contain all the
   * keywords or only one or few of them.
   *
   * @param string $keywords[]  The set of keywords to match
   * @param number $first       The index of the first topic of the set
   * @param number $nb          The maximum amount of topics to list
   * @param bool $strict        True if articles must have all keywords (default), false if they 
   *                            can contain only one keyword of the set
   * @return mixed[]            The articles that were found
   * @throws Exception          If articles could not be found (SQL error is provided) or if 
   *                            $keywords is not an array
   */
   
   public static function getArticlesWithKeywords($keywords, $first, $nb, 
                                                  $category = '', $strict = true)
   {
      if(!is_array($keywords))
         throw new Exception('Keywords must be provided as an array');
      
      $sqlInput = array();
      
      $specificCategory = false;
      if (strlen($category) > 0 && in_array($category, array_keys(Utils::ARTICLES_CATEGORIES)))
      {
         $specificCategory = true;
         array_push($sqlInput, $category);
      }
         
      $nbKeywords = count($keywords);
      $toParse = '';
      for($i = 0; $i < $nbKeywords; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $keywords[$i]);
      }
      
      $sql = 'SELECT art.* 
      FROM map_tags_articles art_t, articles art, tags t 
      WHERE art_t.tag = t.tag ';
      if ($specificCategory)
         $sql .= '&& type = ? ';
      $sql .= 'AND art.date_publication != \'1970-01-01 00:00:00\' 
      AND (t.tag IN ('.$toParse.')) 
      AND art.id_article = art_t.id_article 
      GROUP BY art.id_article';
      if($strict)
      {
         $sql .= ' HAVING COUNT( art.id_article )=?';
         array_push($sqlInput, $nbKeywords);
      }
      $sql .= ' ORDER BY date_publication DESC LIMIT '.$first.','.$nb;
      
      $res = Database::secureRead($sql, $sqlInput);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Articles could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No article has been found.');
      
      return $res;
   }
}
?>
