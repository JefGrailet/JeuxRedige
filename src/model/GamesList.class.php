<?php

/**
* GamesList class models a list of games (could have been named "List" but there's a risk of 
* ambiguity with the language). Unlike other "commentables", it is not tied to a specific 
* game, but just like reviews, it requires an additional table in the database to maintain the 
* games that are tied to the list (among others).
*/

require_once PathHandler::WWW_PATH().'model/Commentable.class.php';

class GamesList extends Commentable
{
   // List items (2D array to load with getItems(), cf. method of the same name)
   private $_items;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to this list or an ID
   * @throws Exception    If the content cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $sql = "SELECT * FROM lists NATURAL JOIN commentables WHERE id_commentable=?";
         $this->_data = Database::secureRead($sql, array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('List does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('List could not be found: '. $this->_data[2]);
      }
      
      $this->_items = null;
   }
   
   /*
   * Static method to insert a new list of games in the database.
   *
   * @param string $title     The title of the list
   * @param string $desc      Small description (text only)
   * @param string $ordering  The ordering policy (by default, list items are listed by the date 
   *                          at which they have been created)
   * @return GamesList        The new entry as a GamesList instance
   * @throws Exception        When the insertion of the content in the database fails, with the 
   *                          actual SQL error inside
   */
   
   public static function insert($title, $desc, $ordering = 'default')
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('List creation is reserved to registered and logged users.');
      
      /*
      * Insertion consists in creating a new line in "commentables" then a new line in "lists" 
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
      
      // Small security in case the picked ordering policy wasn't correct
      $acceptedPolicies = array('default', 'top');
      $parsedOrdering = $ordering;
      if(!in_array($parsedOrdering, $acceptedPolicies))
         $parsedOrdering = 'default';
      
      $arg = array('id' => $newCommentableID, 'desc' => $desc, 'ordering' => $parsedOrdering);
      $res = Database::secureWrite("INSERT INTO lists VALUES(:id, :desc, :ordering)", $arg);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('Could not insert new list: '. $res[2]);
      }
      
      Database::commit();
      return new GamesList($newCommentableID);
   }
   
   /*
   * Edits this list (title, description and ordering policy). Returns nothing.
   *
   * @param string $title     New title
   * @param string $ordering  New ordering policy
   * @throws Exception        When the update fails, with the actual SQL error inside
   */
   
   public function edit($title, $desc, $ordering = 'default')
   {
      // Small security in case the picked ordering policy wasn't correct
      $acceptedPolicies = array('default', 'top');
      $parsedOrdering = $ordering;
      if(!in_array($parsedOrdering, $acceptedPolicies))
         $parsedOrdering = $this->_data['ordering'];
      
      // Otherwise, one additional SQL request is needed
      $sql = 'UPDATE lists SET ordering=:ordering, description=:desc WHERE id_commentable=:id_commentable';
      $arg = array('ordering' => $ordering, 'desc' => $desc, 'id_commentable' => $this->_data['id_commentable']);
      
      Database::beginTransaction();
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('List could not be updated: '. $res[2]);
      }
      
      try
      {
         parent::update($title);
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw new Exception('List could not be updated, because '. $e->getMessage());
      }
      
      Database::commit();
      
      $this->_data['description'] = $desc;
      $this->_data['ordering'] = $parsedOrdering;
   }
   
   /*
   * Gets the amount of items.
   *
   * @return integer    The amount of items for this list
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function countItems()
   {
      $sql = "SELECT COUNT(*) AS nb FROM map_lists_games WHERE id_commentable=?";
      $res = Database::secureRead($sql, array($this->_data['id_commentable']), true);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot count items: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Gets the list items.
   *
   * @return mixed[]    The resulting 2D array, with a different item per cell. Segments are 
   *                    ordered according to their respective "rank" in the list.
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function getItems()
   {
      $sql = "SELECT * FROM map_lists_games WHERE id_commentable=? ORDER BY rank";
      $res = Database::secureRead($sql, array($this->_data['id_commentable']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot get items: '. $res[2]);
      else if($res == NULL)
      {
         $this->_items = array(); // Shows that some request was addressed to the DB
         return $this->_items;
      }
      
      $this->_items = $res;
      return $res;
   }
   
   public function getBufferedItems() { return $this->_items; }
   
   /*
   * Checks whether some game is already in the list or not.
   *
   * @return bool       True if the game is already listed
   * @throws Exception  If an SQL error occurs while checking the database
   */
   
   public function hasListed($game)
   {
      $sql = "SELECT COUNT(*) AS nb FROM map_lists_games WHERE game=? && id_commentable=?";
      $res = Database::secureRead($sql, array($game, $this->_data['id_commentable']), true);
      
      if(count($res) == 3)
         throw new Exception('Cannot check presence of a game in the list: '. $res[2]);
      
      if($res['nb'] > 0)
         return true;
      return false;
   }
   
   /*
   * Gets the default thumbnail to use for the comment topic.
   *
   * @param bool $local  Optional boolean to set to true if the calling code rather needs the path 
   *                     in the file system (vs. "HTTP" path that can be inserted in HTML code)
   * @return string      The full URL (or absolute path) to the thumbnail
   */
   
   public function getThumbnail($local = false)
   {
      $thumbnailFile = PathHandler::WWW_PATH().'upload/commentables/'.$this->_data['id_commentable'].'.jpg';
      if(file_exists($thumbnailFile))
      {
         if(!$local)
         {
            $URL = PathHandler::HTTP_PATH().'upload/commentables/'.$this->_data['id_commentable'].'.jpg';
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
      if($this->_items == null)
      {
         try
         {
            $this->getItems();
         }
         catch(Exception $e)
         {
            return array('Liste'); // Worst case
         }
      }
      
      $res = array();
      for($i = 0; $i < count($this->_items) && $i < 10; $i++)
         array_push($res, $this->_items[$i]['game']);
      return $res;
   }
   
   /*
   * Static method to count the total amount of lists.
   *
   * @return integer      The total amount of lists recorded in the database
   * @throws Exception    If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countLists()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM lists';
      $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Lists could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the total amount of lists mentioning a specific game.
   *
   * @param string $game  The mentioned game
   * @return integer      The total amount of lists recorded in the database listing this game
   * @throws Exception    If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countListsMentioning($game)
   {
      $sql = 'SELECT COUNT(*) AS nb FROM map_lists_games WHERE game=?';
      $res = Database::secureRead($sql, array($game), true);
      
      if(count($res) == 3)
         throw new Exception('Lists could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the total amount of lists created by a specific user.
   *
   * @param string $user   The pseudonym of the user whose lists are being counted
   * @return integer       The total amount of lists created by this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countUserLists($user)
   {
      $sql = 'SELECT COUNT(*) AS nb FROM commentables NATURAL JOIN lists WHERE pseudo=?';
      $res = Database::secureRead($sql, array($user), true);
      
      if(count($res) == 3)
         throw new Exception('Lists could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Variant to count lists written by this user in particular.
   *
   * @return integer       The total amount of lists recorded in the database for this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countMyLists()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
      
      $res = NULL;
      try
      {
         $res = GamesList::countUserLists(LoggedUser::$data['pseudo']);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
   
   /*
   * Static method to obtain a set of lists. Lists are listed (duh) by publication date.
   *
   * @param number $first  The index of the first list of the set
   * @param number $nb     The maximum amount of lists
   * @return mixed[]       The lists that were found
   * @throws Exception     If lists could not be found (SQL error is provided)
   */

   public static function getLists($first, $nb)
   {
      $sql = 'SELECT * FROM lists NATURAL JOIN commentables ORDER BY date_publication DESC LIMIT '.$first.','.$nb;
      $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Lists could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Likewise, static method to obtain a set of lists listing a specific game.
   *
   * @param string $game   The game that was listed
   * @param number $first  The index of the first list of the set
   * @param number $nb     The maximum amount of lists
   * @return mixed[]       The lists that were found (NULL if not logged)
   * @throws Exception     If lists could not be found (SQL error is provided)
   */
   
   public static function getListsMentioning($game, $first, $nb)
   {
      $sql = 'SELECT * FROM lists 
      NATURAL JOIN commentables 
      WHERE id_commentable IN (
         SELECT id_commentable 
         FROM map_lists_games 
         WHERE game=?
      )
      ORDER BY date_publication DESC LIMIT '.$first.','.$nb;
      $res = Database::secureRead($sql, array($game));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Lists could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Likewise, static method to obtain a set of lists created by a given user.
   *
   * @param string $user   The user who created the lists
   * @param number $first  The index of the first list of the set
   * @param number $nb     The maximum amount of lists
   * @return mixed[]       The lists that were found (NULL if not logged)
   * @throws Exception     If lists could not be found (SQL error is provided)
   */

   public static function getUserLists($user, $first, $nb)
   {
      $sql = 'SELECT * FROM lists 
      NATURAL JOIN commentables 
      WHERE pseudo=? 
      ORDER BY date_publication DESC 
      LIMIT '.$first.','.$nb;
      $res = Database::secureRead($sql, array($user));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Lists could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Variant to get lists from this user in particular.
   *
   * @param number $first  The index of the first list of the set
   * @param number $nb     The maximum amount of lists
   * @return mixed[]       The lists that were found (NULL if not logged)
   * @throws Exception     If lists could not be found (SQL error is provided)
   */
   
   public static function getMyLists($first, $nb)
   {
      if(!LoggedUser::isLoggedIn())
         return;
      
      $res = NULL;
      try
      {
         $res = GamesList::getUserLists(LoggedUser::$data['pseudo'], $first, $nb);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
}
?>
