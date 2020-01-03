<?php

/**
* Review class models a review. A review consists of a short evaluation of a game, including a 
* rating (interpreted in practice in text form), a title (inherited from the parent Commentable 
* class) and a short comment (which can however contain format code, but no uploads or special 
* formatting like articles). The main interest of having reviews in addition to articles is to 
* allow everyone to add small comments when currently available articles already tell a lot but 
* also to participate to the identification and aggregation of tropes. A review can also be linked 
* to an existing article.
*/

require_once PathHandler::WWW_PATH().'model/Commentable.class.php';

class Review extends GameCommentable
{
   // Related data fields (not loaded at construction, see methods following it)
   protected $_article;
   protected $_tropes;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the review or the ID of that review
   * @throws Exception    If the review cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $sql = "SELECT * FROM reviews NATURAL JOIN commentables WHERE id_commentable=?";
         $this->_data = Database::secureRead($sql, array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Review does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Review could not be found: '. $this->_data[2]);
      }
      
      $this->_article = NULL;
      $this->_tropes = NULL;
   }
   
   /*
   * Static method to insert a new review in the database. Because several SQL requests are 
   * needed, this method uses a SQL transaction.
   *
   * @param string $game     The game being evaluated (must be in the DB)
   * @param int $rating      Rating of the review
   * @param string $title    Title of the review
   * @param string $comment  Main comment
   * @param mixed $related   Related article; either a URL to some external resource (as a string) 
   *                         or an ID pointing to an article; 0 means no related article (default)
   * @return Review          The new entry as a Review instance
   * @throws Exception       When the insertion of the review in the database fails (e.g. because 
   *                         the game isn't listed), with the actual SQL error inside
   */
   
   public static function insert($game, $rating, $title, $comment, $related = 0)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('Review creation is reserved to registered and logged users.');
      
      /*
      * Insertion consists in creating a new line in "commentables" then a new line in "reviews" 
      * with the same "id_commentable". The whole operation should occur in a single transaction.
      */
      
      Database::beginTransaction();
      $newCommentableID = 0;
      try
      {
         $newCommentableID = parent::create($title);
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw $e;
      }
      
      $toInsert = array('id' => $newCommentableID, 
      'game' => $game, 
      'rating' => $rating, 
      'comment' => $comment, 
      'id_article' => NULL, 
      'external_link' => NULL);
      
      if($related != 0 || !is_int($related))
      {
         if(strpos($related, '|') != false)
         {
            $relatedSplit = explode('|', $related);
            $regexURL = '(https?:\/\/(?:www\.|(?!www))?[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})';
            if(preg_match('/^'.$regexURL.'$/', $relatedSplit[0]))
               $toInsert['external_link'] = $related;
         }
         else if(is_int($related))
            $toInsert['id_article'] = $related;
      }
      
      $sql = "INSERT INTO reviews VALUES(:id, :game, :rating, :comment, '', :id_article, :external_link)";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('Could not insert new review: '. $res[2]);
      }
      
      Database::commit();
      return new Review($newCommentableID);
   }
   
   /*
   * Method to retrieve the title and subtitle of the associated article, if any. The results 
   * are both buffered in the object and returned.
   *
   * @return mixed[]    The relevant data of the associated article (title, subtitle for now)
   * @throws Exception  If the associated article could not be fetched (with the SQL error inside)
   */
   
   public function getArticle()
   {
      if($this->_data['id_article'] == NULL || $this->_data['id_article'] == 0)
         return NULL;
      
      $sql = "SELECT title, subtitle FROM articles WHERE id_article=?";
      $res = Database::secureRead($sql, array($this->_data['id_article']), true);
      
      if($res != NULL && count($res) == 3)
         throw new Exception('Cannot get associated article: '. $res[2]);
      
      $res['id_article'] = $this->_data['id_article'];
      $this->_article = $res;
      return $res;
   }
   
   public function setBufferedArticle($art) { $this->_article = $art; }
   public function getBufferedArticle() { return $this->_article; }
   
   /*
   * Method to retrieve the set of tropes associated to the review, i.e., the tropes the user 
   * wants to associate with the game. No parameter is required. The result array also contains 
   * trope details.
   *
   * @return string[][]  The tropes associated with the review as a 2D array; an empty array is 
   *                     returned when there is no trope
   * @throws Exception   If the tropes could not be fetched (with the SQL error inside)
   */

   public function getTropes()
   {
      $sql = "SELECT map_tropes_reviews.tag, tropes.color, tropes.description 
      FROM map_tropes_reviews 
      LEFT OUTER JOIN tropes 
      ON tropes.tag = map_tropes_reviews.tag 
      WHERE map_tropes_reviews.id_commentable=? ORDER BY map_tropes_reviews.tag";
   
      $res = Database::secureRead($sql, array($this->_data['id_commentable']));
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot get associated tropes: '. $res[2]);
      else if($res == NULL)
      {
         $this->_tropes = array(); // Shows that some request was addressed to the DB
         return $this->tropes;
      }
      
      $this->_tropes = $res;
      return $res;
   }
   
   public function setBufferedTropes($tro) { $this->_tropes = $tro; }
   public function getBufferedTropes() { return $this->_tropes; }
   
   /*
   * Gets the tropes and formats them in a linear array. The associated trope data is removed in 
   * the process.
   *
   * @return string[]   The tropes associated with the review
   */

   public function getTropesSimple()
   {
      if($this->_tropes == null)
         return array();
   
      $result = array();
      for($i = 0; $i < count($this->_tropes); $i++)
         array_push($result, $this->_tropes[$i]['tag']);
      
      return $result;
   }
   
   /*
   * Registers the tropes associated to this review in the DB entry matching this review in a 
   * special field named "associated_tropes". The motivation of this field is to be able to 
   * retrieve reviews along their associated tropes in a single SQL SELECT request, rather than 
   * making an additionnal request for each review. As tropes are only listed by names, an 
   * additionnal SQL request will still be needed to retrieve the details, but merging the sets 
   * of tropes of different reviews allows to use a single request for all unique tropes. No 
   * parameter is required, and no value is returned.
   *
   * @throws Exception       When the update fails, with the actual SQL error inside
   */
   
   public function registerTropes()
   {
      // Refreshes tropes
      try
      {
         $this->getTropes();
      }
      catch(Exception $e)
      {
         throw $e;
      }
      
      $tropesToRegister = $this->getTropes();
      $tropesStr = '';
      for($i = 0; $i < count($tropesToRegister); ++$i)
      {
         if($i > 0)
            $tropesStr .= '|';
         $tropesStr .= $tropesToRegister[$i]['tag'].','.$tropesToRegister[$i]['color'];
      }
      
      $sql = "UPDATE reviews SET associated_tropes=? WHERE id_commentable=?";
      $res = Database::secureWrite($sql, array($tropesStr, $this->_data['id_commentable']));
      if($res != NULL)
         throw new Exception('Review could not be updated: '. $res[2]);
      
      $this->_data['associated_tropes'] = $tropesStr;
   }
   
   /*
   * Edits the review (rating, title, comment, external). Returns nothing. Because the update 
   * date and title are in the "commentables" table, a second request (via the update() of the 
   * parent class) and therefore a transaction is needed.
   *
   * @param int $rating      New rating
   * @param string $title    New title
   * @param string $comment  Updated comment
   * @param mixed $related   New related article (again, optional)
   * @throws Exception       When the update fails, with the actual SQL error inside
   */
   
   public function edit($rating, $title, $comment, $related = 0)
   {
      $sql = 'UPDATE reviews 
      SET rating=:rating, comment=:comment, 
      id_article=:id_article, external_link=:external_link 
      WHERE id_commentable=:id_commentable';
      
      $arg = array('rating' => $rating, 
      'comment' => $comment, 
      'id_article' => $this->_data['id_article'], 
      'external_link' => $this->_data['external_link'], 
      'id_commentable' => $this->_data['id_commentable']);
      
      if($related != 0 || !is_int($related))
      {
         if(strpos($related, '|') != false)
         {
            $relatedSplit = explode('|', $related);
            $regexURL = '(https?:\/\/(?:www\.|(?!www))?[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})';
            if(preg_match('/^'.$regexURL.'$/', $relatedSplit[0]))
            {
               $arg['external_link'] = $related;
               $arg['id_article'] = NULL;
            }
         }
         else if(is_int($related))
         {
            $arg['id_article'] = $related;
            $arg['external_link'] = NULL;
         }
      }
      else
      {
         $arg['id_article'] = NULL;
         $arg['external_link'] = NULL;
      }
      
      Database::beginTransaction();
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('Review could not be updated: '. $res[2]);
      }
      
      try
      {
         parent::update($title);
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw new Exception('Review could not be updated, because '. $e->getMessage());
      }
      
      Database::commit();
      
      $this->_data['rating'] = $rating;
      $this->_data['comment'] = $comment;
      $this->_data['id_article'] = $arg['id_article'];
      $this->_data['external_link'] = $arg['external_link'];
   }
   
   /*
   * Static method testing if the current user has already reviewed or not a given game. If (s)he 
   * already reviewed the game, the ID of the review is returned.
   * 
   * @param string $game  The game to review
   * @return integer      ID of the review (if it exists), otherwise 0
   * @throws Exception    If anything goes wrong while consulting the DB (SQL error provided) or 
   *                      if the user is not logged int
   */
   
   public static function hasReviewed($game)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('Review creation is reserved to registered and logged users.');
      
      $sql = "SELECT id_commentable FROM reviews NATURAL JOIN commentables WHERE pseudo=? && game=?";
      $arg = array(LoggedUser::$data['pseudo'], $game);
      $res = Database::secureRead($sql, $arg, true);
      if($res == NULL)
         return 0;
      else if(count($res) == 3)
         throw new Exception('Unable to check user reviewed '.$game.' : '. $res[2]);
      
      return $res['id_commentable'];
   }
   
   /*
   * Static method to count the total amount of reviews, either in general, either for a specific 
   * game.
   *
   * @param string $game  The game for which reviews should be counted (optional)
   * @return integer      The total amount of reviews recorded in the database (for a given game)
   * @throws Exception    If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countReviews($game = '')
   {
      $sql = 'SELECT COUNT(*) AS nb FROM reviews';
      if(strlen($game) > 0)
      {
         $sql .= ' WHERE game=?';
         $res = Database::secureRead($sql, array($game), true);
      }
      else
         $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Reviews could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the total amount of reviews written by a specific user.
   *
   * @param string $user   The pseudonym of the user whose reviews are being counted
   * @return integer       The total amount of reviews recorded in the database for this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countUserReviews($user)
   {
      $sql = 'SELECT COUNT(*) AS nb FROM commentables NATURAL JOIN reviews WHERE pseudo=?';
      $res = Database::secureRead($sql, array($user), true);
      
      if(count($res) == 3)
         throw new Exception('Reviews could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Variant to count reviews from this user in particular.
   *
   * @return integer       The total amount of reviews recorded in the database for this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countMyReviews()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
      
      $res = NULL;
      try
      {
         $res = Review::countUserReviews(LoggedUser::$data['pseudo']);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
   
   /*
   * Static method to obtain a set of reviews, for a specific game or globally. Reviews are listed 
   * by publication date.
   *
   * @param number $first  The index of the first review of the set
   * @param number $nb     The maximum amount of reviews to list
   * @param string $game   The game for which reviews should be counted (optional)
   * @return mixed[]       The reviews that were found
   * @throws Exception     If reviews could not be found (SQL error is provided)
   */

   public static function getReviews($first, $nb, $game = '')
   {
      $sql = 'SELECT * FROM reviews NATURAL JOIN commentables ';
      if(strlen($game) > 0)
         $sql .= 'WHERE game=? ';
      $sql .= ' ORDER BY date_publication DESC 
      LIMIT '.$first.','.$nb;
      
      $res = NULL;
      if(strlen($game) > 0)
         $res = Database::secureRead($sql, array($game));
      else
         $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Reviews could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Likewise, static method to obtain a set of reviews written by a given user.
   *
   * @param string $user   The user who wrote the reviews
   * @param number $first  The index of the first review of the set
   * @param number $nb     The maximum amount of reviews to list
   * @return mixed[]       The reviews that were found (NULL if not logged)
   * @throws Exception     If reviews could not be found (SQL error is provided)
   */

   public static function getUserReviews($user, $first, $nb)
   {
      $sql = 'SELECT * FROM reviews 
      NATURAL JOIN commentables 
      WHERE pseudo=? 
      ORDER BY date_publication DESC 
      LIMIT '.$first.','.$nb;
      $res = Database::secureRead($sql, array($user));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Reviews could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Variant to get reviews from this user in particular.
   *
   * @param number $first  The index of the first review of the set
   * @param number $nb     The maximum amount of reviews to list
   * @return mixed[]       The reviews that were found (NULL if not logged)
   * @throws Exception     If reviews could not be found (SQL error is provided)
   */
   
   public static function getMyReviews($first, $nb)
   {
      if(!LoggedUser::isLoggedIn())
         return;
      
      $res = NULL;
      try
      {
         $res = Review::getUserReviews(LoggedUser::$data['pseudo'], $first, $nb);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
   
   /*
   * Static method to count the number of reviews matching a set of tropes. A second parameter 
   * indicates whether the calling code wants results to match all tropes or only part of the 
   * set.
   *
   * @param string $tropes[]  The set of tropes to match
   * @param bool $strict      True if reviews must have all tropes (default), false if they can 
   *                          contain only one trope of the set (optional)
   * @return number           The amount of reviews matching the tropes
   * @throws Exception        If reviews could not be found (SQL error is provided) or if $tropes 
   *                          is not an array
   */
   
   public static function countReviewsWithTropes($tropes, $strict = true)
   {
      if(!is_array($tropes))
         throw new Exception('Tropes must be provided as an array');
         
      $nbTropes = count($tropes);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbTropes; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $tropes[$i]);
      }
      
      $sql = 'SELECT COUNT(*) AS nb FROM (
      SELECT rev.rating 
      FROM map_tropes_reviews rev_t, reviews rev, tropes t 
      WHERE rev_t.tag = t.tag 
      AND (t.tag IN ('.$toParse.')) 
      AND rev.id_commentable = rev_t.id_commentable 
      GROUP BY rev.id_commentable';
      if($strict)
      {
         $sql .= ' HAVING COUNT( rev.id_commentable )=?';
         array_push($sqlInput, $nbTropes);
      }
      $sql .= ') res';
      
      $res = Database::secureRead($sql, $sqlInput, true);
      
      if(count($res) == 3)
         throw new Exception('Reviews could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to get a set of reviews matching a set of tropes. Parameters $first and $nb 
   * are still needed in order to use pages when there are too many results for a single page. A 
   * 4th parameter indicates to the method if the calling code wants results to contain all the
   * tropes or only one or few of them.
   *
   * @param string $tropes[]  The set of tropes to match
   * @param number $first     The index of the first review of the set
   * @param number $nb        The maximum amount of reviews to list
   * @param bool $strict      True if reviews must have all keywords (default), false if they 
   *                          can contain only one trope of the set
   * @return mixed[]          The reviews that were found
   * @throws Exception        If reviews could not be found (SQL error is provided) or if $tropes 
   *                          is not an array
   */
   
   public static function getReviewsWithTropes($tropes, $first, $nb, $strict = true)
   {
      if(!is_array($tropes))
         throw new Exception('Tropes must be provided as an array');
         
      $nbTropes = count($tropes);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbTropes; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $tropes[$i]);
      }
      
      $sql = 'SELECT * 
      FROM (SELECT rev.* 
      FROM map_tropes_reviews rev_t, reviews rev, tropes t 
      WHERE rev_t.tag = t.tag 
      AND (t.tag IN ('.$toParse.')) 
      AND rev.id_commentable = rev_t.id_commentable 
      GROUP BY rev.id_commentable) selected_rev
      NATURAL JOIN commentables';
      if($strict)
      {
         $sql .= ' HAVING COUNT(rev.id_commentable)=?';
         array_push($sqlInput, $nbKeywords);
      }
      $sql .= ' ORDER BY date_publication DESC LIMIT '.$first.','.$nb;
      
      $res = Database::secureRead($sql, $sqlInput);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Reviews could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No review has been found.');
      
      return $res;
   }
   
   /*
   * Static method to look for the tropes associated to a bunch of reviews (as objects) in the 
   * DB and map them to each review. To do so, it looks in the "associated_tropes" field of each 
   * review to find all tropes associated to the selected reviews in one query. The tropes are 
   * both returned and associated with each review object.
   * 
   * @param Review $reviews[]  The reviews for which tropes are being looked for
   * @return mixed[]           The tropes (NULL if no tropes)
   * @throws Exception         If something goes wrong while looking for the tropes
   */
   
   public static function getGroupTropes($reviews)
   {
      $targets = array();
      for($i = 0; $i < count($reviews); $i++)
      {
         $assocTropes = explode('|', $reviews[$i]->get('associated_tropes'));
         for($j = 0; $j < count($assocTropes); $j++)
         {
            $splitTrope = explode(',', $assocTropes[$j]);
            if(!in_array($splitTrope[$j], $targets))
               array_push($targets, $splitTrope[0]);
         }
      }
      
      if(count($targets) == 0)
         return NULL;
      
      $sql = 'SELECT tag, color, description FROM tropes WHERE tag IN (';
      for($i = 0; $i < count($targets); $i++)
      {
         if($i > 0)
            $sql .= ', ';
         $sql .= '?';
      }
      $sql .= ')';
      
      $res = Database::secureRead($sql, $targets);
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Associated tropes could not be found: '. $res[2]);
      
      // Associating tropes to each review
      $assocRes = array();
      for($i = 0; $i < count($res); $i++)
         $assocRec[$res[$i]['tag']] = $res[$i];
      
      for($i = 0; $i < count($reviews); $i++)
      {
         $assocTropes = explode('|', $reviews[$i]->get('associated_tropes'));
         $tropes = array();
         for($j = 0; $j < count($assocTropes); $j++)
         {
            $splitTrope = explode(',', $assocTropes[$j]);
            $lookUp = $assocRec[$splitTrope[0]];
            if($lookUp != NULL)
               array_push($tropes, $lookUp);
         }
         $reviews[$i]->setBufferedTropes($tropes);
      }
      
      return $res;
   }
   
   /*
   * Static method to look for the details of the articles associated to a bunch of reviews (as 
   * objects) in the DB and map them to each review. The method is very similar to 
   * getGroupTropes() but is simpler. Moreover, it does not return anything.
   *
   * @param Review $reviews[]  The reviews for which article details are being looked for
   * @throws Exception         If something goes wrong while looking for the details
   */
   
   public static function getGroupArticles($reviews)
   {
      $targets = array();
      for($i = 0; $i < count($reviews); $i++)
         if($reviews[$i]->get('id_article') != NULL)
            array_push($targets, $reviews[$i]->get('id_article'));
      
      if(count($targets) == 0)
         return;
         
      $sql = 'SELECT id_article, title, subtitle FROM articles WHERE id_article IN (';
      for($i = 0; $i < count($targets); $i++)
      {
         if($i > 0)
            $sql .= ', ';
         $sql .= '?';
      }
      $sql .= ')';
      
      $res = Database::secureRead($sql, $targets);
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Associated articles could not be found: '. $res[2]);
         
      // Associating tropes to each review
      $assocRes = array();
      for($i = 0; $i < count($res); $i++)
         $assocRec[$res[$i]['id_article']] = $res[$i];
         
      for($i = 0; $i < count($reviews); $i++)
      {
         $lookUp = $assocRec[$reviews[$i]->get('id_article')];
         if($lookUp != NULL)
            $reviews[$i]->setBufferedArticle($lookUp);
      }
   }
}
?>
