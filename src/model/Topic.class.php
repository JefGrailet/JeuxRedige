<?php

/**
* Topic class models a single topic. Like User class, an instance of Topic corresponds to a row in 
* the "topics" table in the database. The main field of the class is an array with the same 
* fields/values as in the database. Methods allows the calling code to handle a topic without 
* explicitely addressing the database, in a high-level fashion. A static method can be used to 
* create (insert) a new topic.
*/

class Topic
{
   protected $_data;

   // Metadata fields (not loaded by constructor, see additionnal data section)
   protected $_keywords;
   protected $_userView;
   protected $_featuredPosts;
   protected $_nbPins;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the topic or the ID of that topic
   * @throws Exception    If the topic cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $this->_data = Database::secureRead("SELECT * FROM topics WHERE id_topic=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Topic does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Topic could not be found: '. $this->_data[2]);
      }
      
      $this->_keywords = array();
      $this->_userView = NULL;
      $this->_featuredPosts = array('withUploads' => 0, 'popular' => 0, 'unpopular' => 0);
      $this->_nbPins = 0;
   }
   
   /*
   * Static method to insert a new topic in the database.
   *
   * N.B. : this method DOES NOT insert the first message.
   *
   * @param string $title        Title of the new topic
   * @param string $thumbnail    The thumbnail for that topic
   * @param integer $type        Type of the topic (unused for now)
   * @param bool $anonPosting    True if anonymous user are allowed to post messages on this topic
   * @param bool $enableUploads  True if uploads are enabled for this topic
   * @return Topic               The new topic as a Topic instance
   * @throws Exception           When the insertion of the topic in the database fails, with the
   *                             actual SQL error inside
   */
   
   public static function insert($title, $thumbnail, $type, $anonPosting, $enableUploads)
   {
      $userRank = 'regular user';
      if(LoggedUser::$data['used_pseudo'] === LoggedUser::$data['function_pseudo'])
         $userRank = LoggedUser::$data['function_name'];
      
      $anonPostingStr = 'no';
      $enableUploadsStr = 'no';
      if($anonPosting)
         $anonPostingStr = 'yes';
      if($enableUploads)
         $enableUploadsStr = 'yes';
      
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $toInsert = array('title' => $title,
      'thumbnail' => $thumbnail,
      'author' => LoggedUser::$data['used_pseudo'],
      'created_as' => $userRank,
      'date' => $currentDate,
      'type' => $type,
      'anon_posting' => $anonPostingStr,
      'enable_uploads' => $enableUploadsStr);
      
      $sql = "INSERT INTO topics VALUES('0', :title, :thumbnail, :author, :created_as, :date,
              :type, :anon_posting, :enable_uploads, 'no', 'no', '1970-01-01 00:00:00', 
              :author, '')";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new topic: '. $res[2]);
         
      $newTopicID = Database::newId();
       
      $topicDir = PathHandler::WWW_PATH().'upload/topics/'.$newTopicID;
      mkdir($topicDir, 0711);
      
      return new Topic($newTopicID);
   }
   
   /*
   * Variant of insert() to create automatic topics, just like there's an autoInsert() in Post.
   *
   * @param string $title             Title of the new topic
   * @param string $thumbnail         The thumbnail for that topic
   * @param integer $type             Type of the topic (unused for now)
   * @param bool $anonPosting         True if anonymous user are allowed to post messages
   * @param bool $enableUploads       True if uploads are enabled for this topic
   * @param string $overridingPseudo  The pseudo of the "author" of the topic; by default, it's 
   *                                  pseudo of the current user
   * @return Topic                    The new topic as a Topic instance
   * @throws Exception                When the insertion of the topic in the database fails, with 
   *                                  the actual SQL error inside
   */
   
   public static function autoInsert($title, $thumbnail, $type, $anonPosting, $enableUploads, $overridingPseudo = '')
   {
      $finalPseudo = LoggedUser::$data['pseudo'];
      if(strlen($overridingPseudo) >= 3 && strlen($overridingPseudo) < 20)
         $finalPseudo = $overridingPseudo;
      
      $anonPostingStr = 'no';
      $enableUploadsStr = 'no';
      if($anonPosting)
         $anonPostingStr = 'yes';
      if($enableUploads)
         $enableUploadsStr = 'yes';
      
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $toInsert = array('title' => $title,
      'thumbnail' => $thumbnail,
      'author' => $finalPseudo,
      'created_as' => 'author',
      'date' => $currentDate,
      'type' => $type,
      'anon_posting' => $anonPostingStr,
      'enable_uploads' => $enableUploadsStr);
      
      $sql = "INSERT INTO topics VALUES('0', :title, :thumbnail, :author, :created_as, :date,
              :type, :anon_posting, :enable_uploads, 'no', 'no', '1970-01-01 00:00:00', 
              :author, '')";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new topic: '. $res[2]);
         
      $newTopicID = Database::newId();
       
      $topicDir = PathHandler::WWW_PATH().'upload/topics/'.$newTopicID;
      mkdir($topicDir, 0711);
      
      return new Topic($newTopicID);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * ----------------
   * METADATA SECTION
   * ----------------
   *
   * The next methods aim at loading additionnal details (hereby called "metadata") on the topic:
   * -keywords and associated games, 
   * -the user's view, which consists on an amount of seen message and a favorite status, 
   * -amounts of posts containing (respectively): uploads, a positive score and a negative score, 
   * -amounts of pins used by the current user on the messages of the topic.
   *
   * In some pages, all metadata needs to be loaded. A unique method is dedicated to this 
   * particular task (the data is buffered with additionnal fields), which can be checked and 
   * retrieved with additionnal methods.
   */
   
   /*
   * Method to retrieve the set of keywords associated to this topic. No parameter is required. 
   * The result array also contains game information if the keyword is matching a game entry.
   *
   * @return string[][]  The keywords associated with the topic as a 2D array; keywords associated 
   *                     with games have additionnal fields; an empty array is returned when there 
   *                     is no keyword
   * @throws Exception   If the keywords could not be fetched (with the SQL error inside)
   */

   public function getKeywords()
   {
      $sql = "SELECT map_tags.tag, games.genre, games.publisher, games.developer, 
      games.publication_date, games.hardware 
      FROM map_tags 
      LEFT OUTER JOIN games 
      ON games.tag = map_tags.tag 
      WHERE map_tags.id_topic=? ORDER BY map_tags.tag";
   
      $res = Database::secureRead($sql, array($this->_data['id_topic']));
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot get keywords and associated games: '. $res[2]);
      else if($res == NULL)
         return array();
      
      $this->_keywords = $res;
      return $res;
   }
   
   /*
   * Fetches the topic,user mapping in the database (in map_topics_users) corresponding to the 
   * current user, if logged in. The data appended to the mapping tells the amount of messages 
   * seen since the last visit and whether the user favorited this topic or not.
   *
   * @return mixed[]    An associative array (favorite => [yes/no], last_seen => [integer]) or 
   *                    NULL if there's no entry/user's not logged in
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getUserView()
   {
      if(!LoggedUser::isLoggedIn())
         return NULL;
         
      $user = LoggedUser::$data['pseudo'];
      $ID = $this->_data['id_topic'];
      $sql = "SELECT favorite, last_seen FROM map_topics_users WHERE id_topic=? && pseudo=?";
      $res = Database::secureRead($sql, array($ID, $user), true);
      
      if($res == NULL)
         return NULL;
      else if(count($res) == 3)
         throw new Exception('Cannot get the topic,user mapping ('.$ID.','.$user.'): '. $res[2]);
      
      $this->_userView = $res;
      return $res;
   }
   
   /*
   * Analyzes the posts of the topic and returns an array with the following values:
   * -amount of posts featuring upload(s) as 'withUploads',
   * -amount of posts featuring positive score as 'popular', 
   * -amount of posts featuring negative score as 'unpopular'.
   * No parameter is required.
   *
   * @return integer[]  An associative array featuring the values described above
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getFeaturedPosts()
   {
      $sql = "SELECT SUM(CASE WHEN attachment!='' && attachment LIKE '%uploads%' THEN 1 ELSE 0 END) AS withUploads, 
      SUM(CASE WHEN nb_likes > nb_dislikes THEN 1 ELSE 0 END) AS popular, 
      SUM(CASE WHEN nb_likes < nb_dislikes THEN 1 ELSE 0 END) AS unpopular 
      FROM posts WHERE id_topic=?";
      
      $res = Database::secureRead($sql, array($this->_data['id_topic']));
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot compute additionnal data on the content of the topic: '. $res[2]);
      
      $this->_featuredPosts = $res[0];
      return $res[0];
   }
   
   /*
   * Counts the messages pinned by the current user in this topic.
   *
   * @return integer    > 0 if the current user has pinned messages in this topic, = 0 for all 
   *                    other cases (including the case where user is actually not logged in)
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getPins()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
      
      $sql = "SELECT COUNT(*) AS nb 
      FROM posts 
      INNER JOIN posts_interactions 
      ON posts.id_post = posts_interactions.id_post
      INNER JOIN posts_interactions_pins 
      ON posts_interactions.id_interaction = posts_interactions_pins.id_interaction
      WHERE posts_interactions.user=? && posts.id_topic=?";
      
      $user = LoggedUser::$data['pseudo'];
      $ID = $this->_data['id_topic'];
      $res = Database::secureRead($sql, array($user, $ID), true);
                         
      if($res != NULL && count($res) == 3)
         throw new Exception('Cannot check the pins of '.$user.' for this topic: '. $res[2]);
      
      if($res['nb'] > 0)
      {
         $this->_nbPins = $res['nb'];
         return $res['nb'];
      }
      return 0;
   }
   
   /*
   * Performs in a row: getKeywords(), getFavoriteStatus(), getFeaturedPosts() and getPins(). If 
   * an exception arises, the method stops and rethrows it. It returns nothing (results are 
   * buffered).
   *
   * @throws Exception  If an SQL error occurs while checking the database (rethrown exception)
   */
   
   public function loadMetadata()
   {
      try
      {
         $this->getKeywords();
         $this->getUserView();
         $this->getFeaturedPosts();
         $this->getPins();
      }
      catch(Exception $e)
      {
         throw $e;
      }
   }
   
   // Next methods are only there for accessing and handling the metadata.
   public function getBufferedKeywords() { return $this->_keywords; }
   public function getBufferedView() { return $this->_userView; }
   public function getBufferedFeaturedPosts() { return $this->_featuredPosts; }
   public function getNbPins() { return $this->_nbPins; }
   
   /*
   * Gets the keywords and formats them in a linear array. The associated game data (if any) is 
   * removed in the process.
   *
   * @return string[]   The keywords associated with the topic
   */

   public function getKeywordsSimple()
   {
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
      for($i = 0; $i < count($this->_keywords); $i++)
         if($this->_keywords[$i]['genre'] !== NULL)
            return true;
      return false;
   }
   
   /*
   * -----------------------
   * END OF METADATA SECTION
   * -----------------------
   */
   
   /*
   * ---------
   * USER VIEW
   * ---------
   */
   
   /*
   * Creates the view for this user. If a view is already buffered or if SQL detects a duplicate 
   * mapping, nothing happens.
   *
   * @throws Exception       If the mapping could not be created (SQL error is provided inside)
   */
   
   public function createView()
   {
      if(!LoggedUser::isLoggedIn() || $this->_userView != NULL)
         return;
      
      $topicID = $this->_data['id_topic'];
      $user = LoggedUser::$data['pseudo'];
      
      $sql = "INSERT INTO map_topics_users VALUES(:id_topic, :pseudo, 'no', 0)";
      $arg = array('id_topic' => $topicID, 'pseudo' => $user);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL && strstr($res[2], 'Duplicate entry') == FALSE)
         throw new Exception('Could not map topic '.$topicID.' with user "'.$user.'": '. $res[2]);
      
      $this->_userView = array('favorite' => 'no', 'last_seen' => 0);
   }
   
   /*
   * Changes the status of the "favorite" field.
   *
   * @param string $newStatus  "Yes" or "no"
   * @throws Exception         If the mapping could not be updated (SQL error is provided inside)
   */
   
   private function changeFavoriteStatus($newStatus)
   {
      if(!LoggedUser::isLoggedIn() || $this->_userView == NULL)
         return;
      
      $topicID = $this->_data['id_topic'];
      $user = LoggedUser::$data['pseudo'];
      
      $sql = "UPDATE map_topics_users SET favorite=? WHERE id_topic=? && pseudo=?";
      $arg = array($newStatus, $topicID, $user);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Could not update mapping '.$topicID.','.$user.': '. $res[2]);
      
      $this->_userView['favorite'] = $newStatus;
   }
   
   // Methods to call to (un)favorite a topic
   public function favorite() { self::changeFavoriteStatus('yes'); }
   public function unfavorite() { self::changeFavoriteStatus('no'); }
   
   /*
   * Updates the "last_seen" field. Nothing happens if the provided amount of covered posts is 
   * lower than the current "last_seen" value or if the user's view is not buffered.
   *
   * @param integer $coveredPosts  The amount of messages allegedly read by the user (usually, 
   *                               page being read times the amount of posts per page)
   * @throws Exception             If the mapping could not be updated (SQL error is provided)
   */
   
   public function updateLastSeen($coveredPosts)
   {
      if($this->_userView == NULL || $coveredPosts < intval($this->_userView['last_seen']))
         return;
      
      $topicID = $this->_data['id_topic'];
      $user = LoggedUser::$data['pseudo'];
      
      $sql = "UPDATE map_topics_users SET last_seen=? WHERE id_topic=? && pseudo=?";
      $arg = array($coveredPosts, $topicID, $user);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Could not update mapping '.$topicID.','.$user.': '. $res[2]);
      
      $this->_userView['last_seen'] = $coveredPosts;
   }
   
   /*
   * Updates the "last_seen" field such as if the user had seen all messages. This is useful, for 
   * instance, after the user has posted a new message or for some specific AJAX scripts.
   *
   * @throws Exception             If the mapping could not be updated (SQL error is provided)
   */
   
   public function setAllSeen()
   {
      $topicID = $this->_data['id_topic'];
      $user = LoggedUser::$data['pseudo'];
      
      $sql = "UPDATE map_topics_users 
      SET last_seen=(SELECT COUNT(*) FROM posts WHERE id_topic=:topic) 
      WHERE id_topic=:topic && pseudo=:user";
      $arg = array('topic' => $topicID, 'user' => $user);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Could not update mapping '.$topicID.','.$user.': '. $res[2]);
      
      $this->_userView['last_seen'] = $this->countPosts();
   }
   
   /*
   * Annotates a set of $topics (provided as an array of arrays) with the matching user views if 
   * user's logged in and if the mappings exist.
   *
   * @param mixed $topics[]  The set of topics to check (2D array, one row = one topic)
   * @throws Exception       If $topics is not a 2D array or if an SQL error occurs
   */
   
   public static function getUserViews(&$topics)
   {
      if($topics == NULL || !is_array($topics) || $topics[0] == NULL || !is_array($topics[0]))
         throw new Exception('Topics input array does not fit the expected format');
      else if(!LoggedUser::isLoggedIn())
         return;
      
      $sqlInput = array();
      $toParse = '';
      for($i = 0; $i < count($topics); $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         array_push($sqlInput, $topics[$i]['id_topic']);
         $toParse .= '?';
      }
      array_push($sqlInput, LoggedUser::$data['pseudo']);
      
      if(count($sqlInput) == 1)
         throw new Exception('Topics input array does not fit the expected format');
      
      $sql = 'SELECT id_topic, favorite, last_seen FROM map_topics_users WHERE id_topic IN ('.$toParse.') && pseudo=?';
      $res = Database::secureRead($sql, $sqlInput);
      
      // Format the output as an associative array to ease next steps
      $output = array();
      for($i = 0; $i < count($res); $i++)
         $output[$res[$i]['id_topic']] = array('favorite' => $res[$i]['favorite'], 'last_seen' => $res[$i]['last_seen']);
      $keys = array_keys($output);
      
      // Appends the 'favorite', 'last_seen' fields to topics which the user is mapped to
      for($i = 0; $i < count($topics); $i++)
      {
         if(in_array($topics[$i]['id_topic'], $keys))
         {
            $topics[$i]['favorite'] = $output[$topics[$i]['id_topic']]['favorite'];
            $topics[$i]['last_seen'] = $output[$topics[$i]['id_topic']]['last_seen'];
         }
      }
   }
   
   /*
   * ------------------------
   * END OF USER VIEW SECTION
   * ------------------------
   */
   
   /*
   * Method to update a topic just after having inserted a new message in it (done through the
   * Post class). It is used, for instance, to update the last activity date of the topic.
   *
   * @param mixed $post[]  The row corresponding to the new message
   * @throws Exception     If the topic could not be updated (SQL error is provided inside)
   *                       or if it was already up-to-date
   */
   
   public function update($post)
   {
      if(Utils::toTimestamp($post['date']) > Utils::toTimestamp($this->_data['last_post']))
      {
         $sql = "UPDATE topics SET last_post=?, last_author=?, posted_as=? WHERE id_topic=?";
         
         $arg = array($post['date'], $post['author'], $post['posted_as'], $this->_data['id_topic']);
         $res = Database::secureWrite($sql, $arg);
   
         if($res != NULL)
            throw new Exception('Topic could not be updated: '. $res[2]);
         
         $this->_data['last_post'] = $post['date'];
         $this->_data['last_author'] = $post['author'];
         return true;
      }
      else
         throw new Exception('This topic is already up-to-date.');
   }
   
   /*
   * Locks this topic to prevent the posting of new messages.
   *
   * @returns bool      True if the topic has been locked, false if it was already locked
   * @throws Exception  If the topic could not be updated (SQL error is provided inside)
   */

   public function lock()
   {
      if(!Utils::check($this->_data['is_locked']))
      {
         $sql = "UPDATE topics SET is_locked='yes' WHERE id_topic=?";
         $res = Database::secureWrite($sql, array($this->_data['id_topic']));
         
         if($res != NULL)
            throw new Exception('Topic could not be updated: '. $res[2]);
         
         $this->_data['is_locked'] = 'yes';
         return true;
      }
      return false;
   }
   
   /*
   * Unlocks this topic to allow new messages.
   *
   * @returns bool      True if the topic has been unlocked, false if it was already open
   * @throws Exception  If the topic could not be updated (SQL error is provided inside)
   */
   
   public function unlock()
   {
      if(Utils::check($this->_data['is_locked']))
      {
         $sql = "UPDATE topics SET is_locked='no' WHERE id_topic=?";
         $res = Database::secureWrite($sql, array($this->_data['id_topic']));
         
         if($res != NULL)
            throw new Exception('Topic could not be updated: '. $res[2]);
         
         $this->_data['is_locked'] = 'no';
         return true;
      }
      return false;
   }
   
   /*
   * Deletes the topic and its upload folder.
   *
   * @throws Exception  If a keyword, message or the topic itself could not be deleted (SQL error
   *                    is provided in the Exception object)
   */
   
   public function delete()
   {
      $res = Database::secureWrite("DELETE FROM topics WHERE id_topic=?",
                         array($this->_data['id_topic']), true); // CASCADE from InnoDB will do the rest
      
      if(is_array($res))
      {
         throw new Exception('Unable to delete topic '.$this->_data['id_topic'].' : '. $res[2]);
      }
      
      // Deletion of uploads
      $topicDirPath = PathHandler::WWW_PATH().'upload/topics/'.$this->_data['id_topic'];
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
   
   /*
   * Counts the amount of messages related to this topic. The method can be used "as is" with no 
   * argument at all, but 2 parameters can be used to count the amount of messages before/after a 
   * given date. Both must be provided together.
   *
   * @param string $date         The given date (optional)
   * @param bool $beforeOrAfter  True if we count messages before that date, false for after
   * @return integer             The amount of messages
   * @throws Exception           If the messages could not be found
   */
   
   public function countPosts($date = '1970-01-01 00:00:00', $beforeOrAfter = true)
   {
      $sql = 'SELECT COUNT(*) AS nb FROM posts WHERE id_topic=?';
      $arg = array($this->_data['id_topic']);
   
      if(Utils::toTimestamp($date) > 0)
      {
         if($beforeOrAfter)
            $sql .= ' && date < ?';
         else
            $sql .= ' && date > ?';
         array_push($arg, $date);
      }
      
      $res = Database::secureRead($sql, $arg, true);
      
      if($res == NULL)
         return 0;
      else if(count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Gets a set of messages from this topic, provided an index (first message of the set) and an
   * amount (amount of messages to retrieve). The result is a 2D array.
   *
   * @param integer $first  The index of the first message of the set
   * @param integer $nb     The maximum amount of messages to retrieve
   * @return mixed[]        The messages that were found
   * @throws Exception      If messages could not be found (SQL error is provided)
   */
   
   public function getPosts($first, $nb)
   {
      $sql = 'SELECT * FROM posts WHERE id_topic=? ORDER BY date LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array($this->_data['id_topic']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No message has been found.');
      
      return $res;
   }
   
   /*
   * List the attachments of all posts of the topic. The attachments are not parsed and kept as 
   * they are in the database.
   *
   * @return mixed[]       The list of posts having attachments as an array where each row 
   *                       features the quartet [id_post, author, bad_score, attachment]; NULL 
   *                       if no message features attachment
   * @throws Exception     If messages could not be found (SQL error is provided)
   */
   
   public function listAttachments()
   {
      $sql = "SELECT id_post, author, bad_score, attachment 
      FROM posts 
      WHERE id_topic=? && attachment!='' 
      ORDER BY date";
   
      $res = Database::secureRead($sql, array($this->_data['id_topic']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
         
      return $res;
   }
   
   /*
   * Counts the amount of (un)popular messages. An optional parameter allows calling code to 
   * obtain the contrary result (i.e., amount of unpopular posts) when modified.
   *
   * @param boolean npopular  True if calling code wants popular posts (true by default)
   * @return integer          The total amount of (popular) posts
   * @throws Exception        If messages could not be found (SQL error is provided) or if no 
   *                          message could be found with the given criteria
   */
   
   public function countPopularPosts($popular = true)
   {
      $sql = 'SELECT COUNT(*) AS nb 
      FROM posts 
      WHERE id_topic=? && ';
      if(!$popular)
         $sql .= 'nb_dislikes > nb_likes';
      else
         $sql .= 'nb_likes > nb_dislikes';
   
      $res = Database::secureRead($sql, array($this->_data['id_topic']), true);
      
      if(count($res) == 3)
         throw new Exception('Popular posts could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Gets a set of popular messages (i.e., positive score, highest score first) from this topic, 
   * provided an index (first message of the set) and an amount (of messages to retrieve). The 
   * result is a 2D array. An optional parameter allows calling code to obtain the contrary result 
   * (i.e., unpopular posts, negative score, lowest score first) when modified.
   *
   * @param integer $first    The index of the first message of the set
   * @param integer $nb       The maximum amount of messages to retrieve 
   * @param boolean $popular  True if calling code wants popular posts (true by default)
   * @return mixed[]          The messages that were found
   * @throws Exception        If messages could not be found (SQL error is provided) or if no 
   *                          message could be found with the given criteria
   */
   
   public function getPopularPosts($first, $nb, $popular = true)
   {
      $sql = 'SELECT *, (CAST(nb_likes AS SIGNED INT) - CAST(nb_dislikes AS SIGNED INT)) AS score 
      FROM posts 
      WHERE id_topic=? && ';
      if(!$popular)
      {
         $sql .= 'nb_dislikes > nb_likes 
         ORDER BY score, date ';
      }
      else
      {
         $sql .= 'nb_likes > nb_dislikes 
         ORDER BY score DESC, date ';
      }
      $sql .= 'LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array($this->_data['id_topic']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No message has been found.');
      
      return $res;
   }
   
   /*
   * Gets a set of messages pinned by the current user from this topic, provided an index (first 
   * message of the set) and an amount (of messages to retrieve). The result is a 2D array.
   *
   * @param integer $first    The index of the first message of the set
   * @param integer $nb       The maximum amount of messages to retrieve
   * @return mixed[]          The messages that were found
   * @throws Exception        If messages could not be found (SQL error is provided) or if no 
   *                          message could be found with the given criteria
   */
   
   public function getPinnedPosts($first, $nb)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('This feature is reserved to logged in users.');
   
      $sql = 'SELECT posts.*
      FROM posts 
      INNER JOIN posts_interactions 
      ON posts.id_post = posts_interactions.id_post
      INNER JOIN posts_interactions_pins 
      ON posts_interactions.id_interaction = posts_interactions_pins.id_interaction
      WHERE posts_interactions.user=? && posts.id_topic=? 
      ORDER BY posts.date 
      LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo'], $this->_data['id_topic']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No message has been found.');
      
      return $res;
   }

   /*
   * Updates the topic to edit the title, the thumbnail or open/close the topic for anonymous
   * users. Nothing is returned.
   *
   * @param string $newTitle       The new title
   * @param string $newThumbnail   The new thumbnail
   * @param bool $newAnonPolicy    The new policy regarding anonymous users (true = they can post,
   *                               false = only logged users may create new messages)
   * @param bool $newUploadPolicy  The new policy regarding uploads (true if enabled)
   * @throws Exception             If the topic could not be updated (SQL error is provided)
   */
   
   public function edit($newTitle, $newThumbnail, $newAnonPolicy, $newUploadPolicy)
   {
      $sql = 'UPDATE topics 
              SET title=?, thumbnail=?, is_anon_posting_enabled=?, uploads_enabled=? 
              WHERE id_topic=?';
              
      $newAnonPolicyStr = 'no';
      $newUploadPolicyStr = 'no';
      if($newAnonPolicy)
         $newAnonPolicyStr = 'yes';
      if($newUploadPolicy)
         $newUploadPolicyStr = 'yes';
   
      $arg = array($newTitle, 
                   $newThumbnail, 
                   $newAnonPolicyStr, 
                   $newUploadPolicyStr, 
                   $this->_data['id_topic']);
      
      $res = Database::secureWrite($sql, $arg);
   
      if($res != NULL)
         throw new Exception('Topic could not be updated: '. $res[2]);
      
      $this->_data['title'] = $newTitle;
      $this->_data['thumbnail'] = $newThumbnail;
      $this->_data['is_anon_posting_enabled'] = $arg[2];
   }
   
   /*
   * Static method to get the total amount of topics.
   *
   * @return integer    The total amount of topics
   * @throws Exception  If topics could ne counted (SQL error is provided)
   */
   
   public static function countTopics()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM topics';
      $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Topics could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to obtain a set of topics, in a similar fashion to getPosts(), except this
   * method is (obviously) not specific to a given topic. Topics are listed by last activity
   * date (the most recently active first).
   *
   * @param integer $first  The index of the first topic of the set
   * @param integer $nb     The maximum amount of topics to list
   * @return mixed[]        The topics that were found
   * @throws Exception      If topics could not be found (SQL error is provided)
   */

   public static function getTopics($first, $nb)
   {
      $sql = 'SELECT *, nb FROM topics NATURAL JOIN (
      SELECT id_topic, COUNT(*) AS nb FROM posts GROUP BY id_topic
      ) t_nb ORDER BY last_post DESC LIMIT '.$first.','.$nb;
   
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Topics could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to count the amount of topics matching a set of keywords. A second parameter 
   * indicates whether the calling code wants results to match all keywords or only part of the 
   * set.
   *
   * @param string $keywords[]  The set of keywords to match
   * @param bool $strict        True if topics must have all keywords (default), false if they can
   *                            contain only one keyword of the set (optional)
   * @return integer            The amount of topics matching the keywords
   * @throws Exception          If topics could not be found (SQL error is provided) or if 
   *                            $keywords is not an array
   */
   
   public static function countTopicsWithKeywords($keywords, $strict = true)
   {
      if(!is_array($keywords))
         throw new Exception('Keywords must be provided as an array');
         
      $nbKeywords = count($keywords);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbKeywords; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $keywords[$i]);
      }
      
      $sql = 'SELECT COUNT(*) AS nb FROM (
      SELECT topic.title
      FROM map_tags topic_t, topics topic, tags t
      WHERE topic_t.tag = t.tag
      AND (t.tag IN ('.$toParse.'))
      AND topic.id_topic = topic_t.id_topic
      GROUP BY topic.id_topic';
      if($strict)
      {
         $sql .= ' HAVING COUNT( topic.id_topic )=?';
         array_push($sqlInput, $nbKeywords);
      }
      $sql .= ') res';
      
      $res = Database::secureRead($sql, $sqlInput, true);
      
      if(count($res) == 3)
         throw new Exception('Topics could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to get a set of topics matching a set of keywords. Parameters $first and $nb 
   * are still needed in order to use pages when there are too many results for a single page. A 
   * 4th parameter indicates to the method if the calling code wants results to contain all the
   * keywords or only one or few of them.
   *
   * @param string $keywords[]  The set of keywords to match
   * @param integer $first      The index of the first topic of the set
   * @param integer $nb         The maximum amount of topics to list
   * @param bool $strict        True if topics must have all keywords (default), false if they can
   *                            contain only one keyword of the set
   * @return mixed[]            The topics that were found
   * @throws Exception          If topics could not be found (SQL error is provided) or if 
   *                            $keywords is not an array
   */
   
   public static function getTopicsWithKeywords($keywords, $first, $nb, $strict = true)
   {
      if(!is_array($keywords))
         throw new Exception('Keywords must be provided as an array');
         
      $nbKeywords = count($keywords);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbKeywords; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $keywords[$i]);
      }
      
      $sql = 'SELECT topic.*, (SELECT COUNT(*) FROM posts WHERE id_topic=topic.id_topic) AS nb
      FROM map_tags topic_t, topics topic, tags t
      WHERE topic_t.tag = t.tag
      AND (t.tag IN ('.$toParse.'))
      AND topic.id_topic = topic_t.id_topic
      GROUP BY topic.id_topic';
      if($strict)
      {
         $sql .= ' HAVING COUNT( topic.id_topic )=?';
         array_push($sqlInput, $nbKeywords);
      }
      $sql .= ' ORDER BY topic.last_post DESC LIMIT '.$first.','.$nb;
      
      $res = Database::secureRead($sql, $sqlInput);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Topics could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No topic has been found.');
      
      return $res;
   }
   
   /*
    * Static method to learn the delay between now and the lattest topic created by the current 
    * user, if logged in. The responsibility of such task has been attributed to the Topic class, 
    * since it involves a SQL request on the "topics" table.
    *
    * @return integer    The delay in seconds since user's lattest authored topic, or -1 if not 
    *                    logged in. If the user never created topics, the current timestamp is 
    *                    returned.
    * @throws Exception  If the SQL request could not be executed (SQL error is provided)
    */
   
   public static function getUserDelay()
   {
      $currentTime = Utils::SQLServerTime();
      if(!LoggedUser::isLoggedIn())
         return -1;
      
      /*
       * Note: 'pseudo' is used instead of 'used_pseudo' on purpose. Admins should not be limited 
       * in terms of posts or topics.
       */
      
      $sql = 'SELECT date FROM topics WHERE author=? ORDER BY date DESC LIMIT 1';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Could not get date of the lattest created topic: '. $res[2]);
      else if($res == NULL)
         return $currentTime;
      
      $delay = $currentTime - Utils::toTimestamp($res['date']);
      return $delay;
   }
}

?>
