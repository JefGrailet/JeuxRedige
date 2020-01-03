<?php

/**
* Trivia class models a piece of trivia (or "anecdote") about some game. It consists of a short 
* text providing interesting or amusing details about said game, such as unusual development 
* conditions.
*/

require_once PathHandler::WWW_PATH().'model/Commentable.class.php';

class Trivia extends GameCommentable
{
   /*
   * Constructor. 
   *
   * @param mixed $arg[]  Existing array corresponding to this piece of trivia or an ID
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
         $sql = "SELECT * FROM trivia NATURAL JOIN commentables WHERE id_commentable=?";
         $this->_data = Database::secureRead($sql, array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Trivia does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Trivia could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new piece of trivia in the database.
   *
   * @param string $game     The game which the piece of trivia is about (must be in the DB)
   * @param string $title    The title of the piece
   * @param string $comment  The text of the piece
   * @return Trivia          The new entry as a Trivia instance
   * @throws Exception       When the insertion of the content in the database fails (e.g. because 
   *                         the game isn't listed), with the actual SQL error inside
   */
   
   public static function insert($game, $title, $content)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception('Trivia posting is reserved to registered and logged users.');
      
      /*
      * Insertion consists in creating a new line in "commentables" then a new line in "trivia" 
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
      
      $arg = array('id' => $newCommentableID, 'game' => $game, 'content' => $content);
      $res = Database::secureWrite("INSERT INTO trivia VALUES(:id, :game, :content)", $arg);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('Could not insert new piece of trivia: '. $res[2]);
      }
      
      Database::commit();
      return new Trivia($newCommentableID);
   }
   
   /*
   * Edits this piece of trivia (title and content). Returns nothing. Because the update date 
   * and title are in the "commentables" table, a second request (via the update() of the parent 
   * class) and therefore a transaction is needed.
   *
   * @param string $title    New title
   * @param string $content  Updated content
   * @throws Exception       When the update fails, with the actual SQL error inside
   */
   
   public function edit($title, $content)
   {
      $sql = 'UPDATE trivia SET content=:content WHERE id_commentable=:id_commentable';
      $arg = array('content' => $content, 'id_commentable' => $this->_data['id_commentable']);
      
      Database::beginTransaction();
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
      {
         Database::rollback();
         throw new Exception('Piece of trivia could not be updated: '. $res[2]);
      }
      
      try
      {
         parent::update($title);
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw new Exception('Piece of trivia could not be updated, because '. $e->getMessage());
      }
      
      Database::commit();
      
      $this->_data['content'] = $content;
   }
   
   /*
   * Static method to count the total amount of pieces of trivia, either in general, either for a 
   * specific game.
   *
   * @param string $game  The game for which pieces should be counted (optional)
   * @return integer      The total amount of pieces recorded in the database (for a given game)
   * @throws Exception    If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countPieces($game = '')
   {
      $sql = 'SELECT COUNT(*) AS nb FROM trivia';
      if(strlen($game) > 0)
      {
         $sql .= ' WHERE game=?';
         $res = Database::secureRead($sql, array($game), true);
      }
      else
         $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Pieces of trivia could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the total amount of pieces written by a specific user.
   *
   * @param string $user   The pseudonym of the user whose pieces are being counted
   * @return integer       The total amount of pieces written by this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countUserPieces($user)
   {
      $sql = 'SELECT COUNT(*) AS nb FROM commentables NATURAL JOIN trivia WHERE pseudo=?';
      $res = Database::secureRead($sql, array($user), true);
      
      if(count($res) == 3)
         throw new Exception('Pieces of trivia could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Variant to count pieces written by this user in particular.
   *
   * @return integer       The total amount of pieces recorded in the database for this user
   * @throws Exception     If anything goes wrong while consulting the DB (SQL error provided)
   */
   
   public static function countMyPieces()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
      
      $res = NULL;
      try
      {
         $res = Trivia::countUserPieces(LoggedUser::$data['pseudo']);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
   
   /*
   * Static method to obtain a set of pieces of trivia, for a specific game or globally. Pieces 
   * are listed by publication date.
   *
   * @param number $first  The index of the first piece of trivia of the set
   * @param number $nb     The maximum amount of pieces to list
   * @param string $game   The game for which pieces should be counted (optional)
   * @return mixed[]       The pieces that were found
   * @throws Exception     If pieces could not be found (SQL error is provided)
   */

   public static function getPieces($first, $nb, $game = '')
   {
      $sql = 'SELECT * FROM trivia NATURAL JOIN commentables ';
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
         throw new Exception('Pieces of trivia could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Likewise, static method to obtain a set of pieces written by a given user.
   *
   * @param string $user   The user who wrote the pieces
   * @param number $first  The index of the first piece of the set
   * @param number $nb     The maximum amount of pieces to list
   * @return mixed[]       The pieces that were found (NULL if not logged)
   * @throws Exception     If pieces could not be found (SQL error is provided)
   */

   public static function getUserPieces($user, $first, $nb)
   {
      $sql = 'SELECT * FROM trivia 
      NATURAL JOIN commentables 
      WHERE pseudo=? 
      ORDER BY date_publication DESC 
      LIMIT '.$first.','.$nb;
      $res = Database::secureRead($sql, array($user));
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Pieces of trivia could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Variant to get pieces of trivia from this user in particular.
   *
   * @param number $first  The index of the first piece of the set
   * @param number $nb     The maximum amount of pieces to list
   * @return mixed[]       The pieces that were found (NULL if not logged)
   * @throws Exception     If pieces could not be found (SQL error is provided)
   */
   
   public static function getMyPieces($first, $nb)
   {
      if(!LoggedUser::isLoggedIn())
         return;
      
      $res = NULL;
      try
      {
         $res = Trivia::getUserPieces(LoggedUser::$data['pseudo'], $first, $nb);
      }
      catch(Exception $e)
      {
         throw $e;
      }
      return $res;
   }
   
   /*
   * Static method to obtain a certain amount of random pieces of trivia.
   *
   * @param number $nb  The maximum amount of pieces to pick at random
   * @return mixed[]    The pieces that were found
   * @throws Exception  If pieces could not be found (SQL error is provided)
   */
   
   public static function getRandomPieces($nb)
   {
      $sql = 'SELECT * FROM trivia 
      NATURAL JOIN commentables 
      ORDER BY RAND() DESC 
      LIMIT '.$nb;
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Pieces of trivia could not be randomly picked: '. $res[2]);
      
      return $res;
   }
}
?>
