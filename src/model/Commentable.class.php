<?php

/**
* Commentable is a superclass for several types of content that are too small to systematically 
* get a discussion thread at publication (like full articles) but which should be submitted to 
* other users' evaluation anyway, to evaluate how relevant and useful this piece of content is. 
* In particular, it allows bindings between a "commentable" and several topics to discuss the 
* content more thoroughly, which should however stay an exception (a commentable isn't supposed 
* to be long and as debatable as a full article).
*/

class Commentable
{
   protected $_data;
   
   // Field to contain the details on the related topic or ratings; not loaded at construction
   protected $_topic;
   protected $_relevant_ratings;
   protected $_irrelevant_ratings;
   
   /*
   * Method to retrieve a Commentable on the basis on an ID. Unlike other constructors in other 
   * model/ classes, this one only handles the retrieval of the corresponding line in the DB on 
   * the basis of the ID (for now).
   * 
   * @param integer     The ID of the commentable
   * @throws Exception  If something goes wrong while retrieving the line
   */
   
   public function __construct($ID)
   {
      $sql = "SELECT * FROM commentables WHERE id_commentable=?";
      $this->_data = Database::secureRead($sql, array($ID), true);
      
      if($this->_data == NULL)
         throw new Exception('Commentable does not exist.');
      else if(count($this->_data) == 3)
         throw new Exception('Commentable could not be found: '. $this->_data[2]);
      
      $this->_topic = NULL;
      $this->_relevant_ratings = NULL;
      $this->_irrelevant_ratings = NULL;
   }
   
   /*
   * Method to insert a new commentable into the database and create the corresponding object. 
   * The ID of the new commentable is returned. Typically, this method is called by the insert() 
   * method of child classes.
   * 
   * @param string $title      The title of the new commentable
   * @return integer           The ID of the new Commentable
   * @throws Exception         If something goes wrong at insertion
   */
   
   protected static function create($title)
   {
      if(!LoggedUser::isLoggedIn()) // Shouldn't occur to begin with
         return;
      
      $sql = "INSERT INTO commentables VALUES('0', :user, :title, :date, '1970-01-01 00:00:00', '0', '0', NULL)";
      $arg = array('user' => LoggedUser::$data['pseudo'], 
                   'title' => $title, 
                   'date' => Utils::toDatetime(Utils::SQLServerTime()));
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
         throw new Exception('Could not insert new commentable: '. $res[2]);
         
      return Database::newId();
   }
   
   /*
   * Method to retrieve the details of the associated topic, if any. This includes the topic ID, 
   * title and total of messages. The results are both buffered in the object and returned.
   *
   * @return mixed[]    The relevant data of the associated article (title, subtitle for now)
   * @throws Exception  If the associated topic could not be fetched (with the SQL error inside)
   */
   
   public function getTopic()
   {
      if($this->_data['id_topic'] == NULL || $this->_data['id_topic'] == 0)
         return NULL;
      
      $sql = "SELECT title, nb 
      FROM topics 
      NATURAL JOIN (
      SELECT id_topic, COUNT(*) AS nb 
      FROM posts 
      WHERE id_topic=?) t_posts";
      $res = Database::secureRead($sql, array($this->_data['id_topic']), true);
      
      if($res != NULL && count($res) == 3)
         throw new Exception('Cannot get associated topic: '. $res[2]);
      
      $res['id_topic'] = $this->_data['id_topic'];
      $this->_topic = $res;
      return $res;
   }
   
   /*
   * Method to retrieve the detailed list of ratings, i.e., who voted what. A single SQL query is 
   * executed but the different ratings are inserted in different arrays.
   * 
   * @return mixed[]    The ratings ([0] => array with users who voted "relevant", [1] => same but 
   *                    who voted "irrelevant")
   * @throws Exception  If the ratings could not be fetched (with the SQL error inside)
   */
   
   public function getRatings()
   {
      $sql = "SELECT user, rating FROM commentables_ratings WHERE id_commentable=?";
      $res = Database::secureRead($sql, array($this->_data['id_commentable']));
      
      if($res != NULL && count($res) == 3)
         throw new Exception('Cannot get detailed ratings: '. $res[2]);
      
      // Creates empty arrays
      $this->_relevant_ratings = array();
      $this->_irrelevant_ratings = array();
      
      // Filters SQL request results to fill them
      for($i = 0; $i < count($res); $i++)
      {
         if($res[$i]['rating'] === 'relevant')
            array_push($this->_relevant_ratings, $res[$i]['user']);
         else
            array_push($this->_irrelevant_ratings, $res[$i]['user']);
      }
      
      $finalRes = array();
      array_push($finalRes, $this->_relevant_ratings);
      array_push($finalRes, $this->_irrelevant_ratings);
      return $finalRes;
   }
   
   public function getBufferedTopic() { return $this->_topic; }
   public function getBufferedRelevantRatings() { return $this->_relevant_ratings; }
   public function getBufferedIrrelevantRatings() { return $this->_irrelevant_ratings; }
   
   /*
    * Method to bind this commentable to a given topic, which the ID is provided. There's only
    * one SQL request; it's up to the calling code to do a SQL transaction if relevant.
    *
    * @param $topicID    ID of the topic to bind to the commentable
    * @throws Exception  If something goes wrong while updating the commentable in the DB
    */
   
   public function bindTopic($topicID)
   {
      $sql = "UPDATE commentables SET id_topic=? WHERE id_commentable=?";
      $res = Database::secureWrite($sql, array($topicID, $this->_data['id_commentable']));
      if($res != NULL)
         throw new Exception('Could not bind topic: '. $res[2]);
   }
   
   /*
   * Method to update the dates and the title of the commentable. The title can be omitted, so 
   * that only the last edition date is updated.
   * 
   * @param string $title  The new title of the commentable (optional)
   * @throws Exception     If something goes wrong during the update
   */
   
   public function update($title = '')
   {
      $updateTime = Utils::toDatetime(Utils::SQLServerTime());
      
      $sql = 'UPDATE commentables SET ';
      $arg = NULL;
      if(strlen($title) > 0)
      {
         $sql .= 'title=?, ';
         $arg = array($title, $updateTime, $this->_data['id_commentable']);
      }
      else
         $arg = array($updateTime, $this->_data['id_commentable']);
      
      $sql .= 'date_last_edition=? WHERE id_commentable=?';
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
         throw new Exception('Commentable could not be updated: '. $res[2]);
      
      if(strlen($title) > 0)
         $this->_data['title'] = $title;
      $this->_data['date_last_edition'] = $updateTime;
   }
   
   // Accessers and a setter (N.B.: no effect on DB ! Only for some very specific tasks)
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   public function set($field, $value) { $this->_data[$field] = $value; }
   
   /*
   * Method to test if the current (logged) user is the author (and therefore, allowed to handle 
   * it). No parameter is required and the method returns a boolean.
   */
   
   public function isMine()
   {
      if(!LoggedUser::isLoggedIn())
         return false;
      if(LoggedUser::$data['pseudo'] === $this->_data['pseudo'])
         return true;
      return false;
   }
   
   /*
   * Checks the DB to see if the current user has rated this commentable yet.
   *
   * @return string     A small string giving the user's rating or a reason why he cannot rate
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getUserRating()
   {
      if(!LoggedUser::isLoggedIn())
      {
         $this->_data['user_rating'] = 'notLogged';
         return $this->_data['user_rating'];
      }
      
      if(LoggedUser::$data['pseudo'] === $this->_data['pseudo'])
      {
         $this->_data['user_rating'] = 'author';
         return $this->_data['user_rating'];
      }
      
      // Avoids an additional SQL request if the rating has been previously fetched
      if(array_key_exists('user_rating', $this->_data))
         return $this->_data['user_rating'];
      
      $user = LoggedUser::$data['pseudo'];
      $itemID = $this->_data['id_commentable'];
         
      $sql = "SELECT rating 
      FROM commentables_ratings 
      WHERE user=? && id_commentable=?";
      $res = Database::secureRead($sql, array($user, $itemID));
         
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot check rating of '.$user.' on commentable '.$itemID.'): '. $res[2]);
      
      $this->_data['user_rating'] = 'none';
      if($res != NULL)
         $this->_data['user_rating'] = $res[0]['rating'];
      return $this->_data['user_rating'];
   }
   
   /*
   * Perform a similar operation but for several commentables at once, just like the 
   * getUserInteractions() method from the Post class.
   * 
   * @param mixed commentables[]  The commentables for which we want the user's ratings
   * @throws Exception            If an SQL error occurs while checking the database
   */
   
   public static function getUserRatings(&$commentables)
   {
      if(!LoggedUser::isLoggedIn() || count($commentables) == 0)
         return;
      
      $commentablesIDs = '(';
      for($i = 0; $i < count($commentables); $i++)
      {
         if($i > 0)
            $commentablesIDs .= ', ';
         $commentablesIDs .= $commentables[$i]['id_commentable'];
      }
      $commentablesIDs .= ')';
      
      $user = LoggedUser::$data['pseudo'];
      $sql = "SELECT id_commentable, rating 
      FROM commentables_ratings 
      WHERE user=? && id_commentable IN $commentablesIDs";
      $res = Database::secureRead($sql, array($user));
         
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot check ratings of '.$user.' on commentables '.$commentablesIDs.': '. $res[2]);
      
      $formattedRes = array();
      $keys = array();
      for($i = 0; $i < count($res); $i++)
      {
         if(!in_array($res[$i]['id_commentable'], $keys))
         {
            $formattedRes[$res[$i]['id_commentable']] = $res[$i]['rating'];
            array_push($keys, $res[$i]['id_commentable']);
         }
      }
      
      for($i = 0; $i < count($commentables); $i++)
      {
         $commentables[$i]['user_rating'] = 'none';
         if(in_array($commentables[$i]['id_commentable'], $keys))
            $commentables[$i]['user_rating'] = $formattedRes[$commentables[$i]['id_commentable']];
      }
   }
   
   /*
   * Method to rate the commentable as (ir)relevant given the point of view of the current user. 
   * It only requires the user's opinion. As the recording requires insertion in a table and the
   * update of the corresponding "commentables" row, it performs a SQL transaction. Note that this 
   * method assumes the user is logged in.
   *
   * @param bool $relevancy  True if the user rates this commentable as relevant
   * @throws Exception       When the insertion of the rating in the database fails, with the
   *                         actual SQL error inside
   */
   
   public function rate($relevancy)
   {
      if(!LoggedUser::isLoggedIn())
         return;
      
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      Database::beginTransaction();
      
      // SQL 1: insertion in commentables_ratings
      $sql1 = "INSERT INTO commentables_ratings VALUES(:id_commentable, :user, :rating, :date)";
      $toInsert1 = array('id_commentable' => $this->_data['id_commentable'], 
                         'user' => LoggedUser::$data['pseudo'], 
                         'rating' => ($relevancy ? 'relevant' : 'irrelevant'), 
                         'date' => $currentDate);
      $res1 = Database::secureWrite($sql1, $toInsert1);
      if($res1 != NULL)
      {
         Database::rollback();
         throw new Exception('Commentable rating could not be recorded: '. $res1[2]);
      }
      
      // SQL 2: updates the relevant/irrelevant count of associated commentables row
      $sql2 = "UPDATE commentables SET ";
      if($relevancy)
         $sql2 .= "votes_relevant=votes_relevant+1 ";
      else
         $sql2 .= "votes_irrelevant=votes_irrelevant+1 ";
      $sql2 .= "WHERE id_commentable=?";
      $res2 = Database::secureWrite($sql2, array($this->_data['id_commentable']));
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Ratings in commentables table could not be updated: '. $res2[2]);
      }
      
      Database::commit();
      
      if($relevancy)
         $this->_data['votes_relevant'] += 1;
      else
         $this->_data['votes_irrelevant'] += 1;
   }
   
   /*
   * Method to undo the rating of the current user. Like the rating itself, it requires more than 
   * one SQL request, and therefore, the operation must be performed in a single SQL transaction.
   * 
   * @throws Exception   If something goes wrong while undoing the rating
   */
   
   public function unrate()
   {
      if(!LoggedUser::isLoggedIn())
         return;
      
      /*
       * To know how the user rated this commentable. N.B.: no additional SQL request will be
       * executed if a similar method was called before (cf. the body of getUserRating()).
       */
      
      $this->getUserRating();
      
      // Start of transaction
      Database::beginTransaction();
      
      // Deletion of the rating (in commentables_ratings)
      $sqlRating = "DELETE FROM commentables_ratings WHERE id_commentable=? && user=?";
      $resRating = Database::secureWrite($sqlRating, array($this->_data['id_commentable'], LoggedUser::$data['pseudo']), true);
      
      if(is_array($resRating))
      {
         Database::rollback();
         throw new Exception('Unable to delete the rating: '. $resVote[2]);
      }
      else if($resRating == 0)
      {
         Database::rollback();
         throw new Exception('Rating did not exist at first.');
      }
      
      // Updating the associated commentable entry
      $sql = "UPDATE commentables SET ";
      if($this->_data['user_rating'] === 'relevant')
         $sql .= "votes_relevant=votes_relevant-1 ";
      else
         $sql .= "votes_irrelevant=votes_irrelevant-1 ";
      $sql .= "WHERE id_commentable=?";
      $resCommentable = Database::secureWrite($sql, array($this->_data['id_commentable']));
      if($resCommentable != NULL)
      {
         Database::rollback();
         throw new Exception('Ratings in commentables table could not be updated: '. $resCommentable[2]);
      }
      
      Database::commit();
      
      if($this->_data['user_rating'] === 'relevant')
         $this->_data['votes_relevant'] -= 1;
      else 
         $this->_data['votes_irrelevant'] -= 1;
      $this->_data['user_rating'] = 'none';
   }
   
   /*
   * Deletes the commentable. Thanks to InnoDB, everything related to the commentable in other 
   * tables is deleted as well.
   *
   * @throws Exception  If deletion could not be carried out in the DB (SQL error provided)
   */
   
   public function delete()
   {
      $res = Database::secureWrite("DELETE FROM commentables WHERE id_commentable=?", array($this->_data['id_commentable']), true);
      if(is_array($res))
         throw new Exception('Unable to delete commentable '.$this->_data['id_commentable'].' : '. $res[2]);
   }
   
   /*
   * Returns as a string the type of commentable for a given ID. Is only used in a few specific 
   * cases (like creation of a topic of comments for that commentable, in order to create the 
   * automatic message), but consists of a single SQL query.
   * 
   * @param integer $commentableID  The ID of the commentable
   * @return string                 A string telling what kind of commentable it is, or "Missing" 
   *                                (commentable does not exist), or "Undefined" (commentable only 
   *                                exists in the "commentables" table)
   * @throws Exception              If an error arises while checking the DB
   */
   
   public static function whatKind($commentableID)
   {
      $sql = 'SELECT commentables.id_commentable, trivia.content, lists.ordering 
      FROM commentables 
      LEFT OUTER JOIN trivia ON trivia.id_commentable=commentables.id_commentable 
      LEFT OUTER JOIN lists ON lists.id_commentable=commentables.id_commentable 
      WHERE commentables.id_commentable=?';
      $res = Database::secureRead($sql, array($commentableID));
      
      if($res != NULL)
      {
         if(!is_array($res[0]) && count($res) == 3)
            throw new Exception('Cannot check commentable type: '.$res[2]);
         
         if($res[0]['content'] != NULL)
            return 'Trivia';
         if($res[0]['ordering'] != NULL)
            return 'GamesList';
         return 'Undefined';
      }
      
      return 'Missing';
   }
}

/**
* GameCommentable is a simple extension of Commentable which provides a few additional methods for 
* the commentables that are mapped to a single game. These methods are used to determine default 
* values of associated topics.
*/

class GameCommentable extends Commentable
{
   /*
   * Gets the default thumbnail to use for the comment topic.
   *
   * @param bool $local  Optional boolean to set to true if the calling code rather needs the path 
   *                     in the file system (vs. "HTTP" path that can be inserted in HTML code)
   * @return string      The full URL (or absolute path) to the thumbnail
   */
   
   public function getThumbnail($local = false)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/games/'.PathHandler::formatForURL($this->_data['game']).'/thumbnail1.jpg';
      if(file_exists($thumbnailFile))
      {
         if(!$local)
         {
            $URL = PathHandler::HTTP_PATH().'upload/games/'.PathHandler::formatForURL($this->_data['game']).'/thumbnail1.jpg';
            return $URL;
         }
         return $thumbnailFile;
      }
      
      if(!$local)
         return PathHandler::HTTP_PATH().'/defaultthumbnail.jpg';
      return PathHandler::WWW_PATH().'/defaultthumbnail.jpg';
   }
   
   /*
   * Gets the default keyword(s) to use for the comment topic.
   *
   * @return string[]  An array of keywords
   */
   
   public function getKeywords()
   {
      return array($this->_data['game']);
   }
}
