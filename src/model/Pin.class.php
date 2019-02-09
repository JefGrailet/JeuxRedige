<?php

/**
* Pin models a pin recorded for a (user, post) couple. A pin is a way to "favourite" some posts. 
* In the process, the user can (should) also join a comment.
*/

class Pin
{
   protected $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the interaction or the ID of the post
   * @throws Exception    If the pin cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $sql = "SELECT * 
         FROM posts_interactions 
         NATURAL JOIN posts_interactions_pins 
         WHERE id_post=? && user=?";
         $this->_data = Database::secureRead($sql, array($arg, LoggedUser::$data['pseudo']), true);
         
         if($this->_data == NULL)
            throw new Exception('Pin does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Pin could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to record a new pin in the database. As the recording requires insertion in two 
   * tables, it is performed in a single SQL transaction. This insert() method has also the 
   * particularity of not returning an object, as there is no further handling of a new pin after 
   * recording it within a same script.
   *
   * @param number $postID   ID of the post for which we are recording the pin
   * @param string $comment  An optional comment made by the user on the post for him- or herself
   * @throws Exception       When the insertion of the pin in the database fails, with the actual 
   *                         SQL error inside
   */
   
   public static function insert($postID, $comment = "No comment")
   {
      if(strlen($comment) == 0) // Just in case
         $comment = "No comment";
      
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      Database::beginTransaction();
   
      // SQL 1: insertion in posts_interactions
      $sql1 = "INSERT INTO posts_interactions VALUES('0', :id_post, :user, :date)";
      $toInsert1 = array('id_post' => $postID, 'user' => LoggedUser::$data['pseudo'], 'date' => $currentDate);
      $res1 = Database::secureWrite($sql1, $toInsert1);
      if($res1 != NULL)
      {
         Database::rollback();
         throw new Exception('Interaction could not be recorded: '. $res1[2]);
      }
      
      // SQL 2: insertion in posts_interactions_pins
      $sql2 = "INSERT INTO posts_interactions_pins VALUES(:id_interact, :comment)";
      $toInsert2 = array('id_interact' => Database::newId(), 'comment' => $comment);
      $res2 = Database::secureWrite($sql2, $toInsert2);
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Interaction could not be recorded: '. $res2[2]);
      }
      
      Database::commit();
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }

   /*
   * Deletion method. No argument is required.
   *
   * @throws Exception  If the deletion could not occur properly (SQL error is provided)
   */
    
   public function delete()
   {
      // Deletion of the vote (posts_interactions; CASCADE will do the rest)
      $sql = "DELETE FROM posts_interactions WHERE id_interaction=?";
      $res = Database::secureWrite($sql, array($this->_data['id_interaction']), true);
      
      if(is_array($res))
         throw new Exception('Unable to delete the pin: '. $res[2]);
   }
   
   /*
   * Simple method to change the comment of the pin.
   *
   * @param string $newComment  The updated comment
   * @throws Exception          If the update could not occur properly (SQL error is provided)
   */
   
   public function edit($newComment)
   {
      $sql = "UPDATE posts_interactions_pins SET comment=? WHERE id_interaction=?";
      $res = Database::secureWrite($sql, array($newComment, $this->_data['id_interaction']));
      
      if($res != NULL)
         throw new Exception('Pin could not be updated: '. $res[2]);
      
      $this->_data['comment'] = $newComment;
   }
   
   /*
   * Static method to count all pins of the current user. Returns 0 if there is no user logged in.
   *
   * @return integer    The amount of pins placed by this user (no distinction on topic)
   * @throws Exception  If some DB issue arises (SQL error is provided)
   */
   
   public static function countPins()
   {
      if(!LoggedUser::isLoggedIn())
         return 0;
      
      $sql = "SELECT COUNT(*) AS nb 
      FROM posts_interactions 
      NATURAL JOIN posts_interactions_pins 
      WHERE posts_interactions.user=?";
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(count($res) == 3)
         throw new Exception('Pins could not be counted: '. $res[2]);
      else if($res == NULL)
         return 0;
      
      return $res['nb'];
   }
   
   /*
   * Static method to list a set of pins, provided an index (first message of the set) and an 
   * amount (number of pins to retrieve). The result is a 2D array. In addition to the join 
   * between posts_interactions and posts_interactions_pins, a third join on posts table is also 
   * requested in order to get the author and date of each message.
   *
   * @param number $first  The index of the first pin of the set
   * @param number $nb     The maximum amount of pins to retrieve
   * @param bool $chrono   True if we use the chronological order of pins (otherwise, post dates)
   * @return mixed[]       The pins that were found
   * @throws Exception     If pins could not be found (SQL error is provided) or if no pin/log in
   */
   
   public static function getPins($first, $nb, $chrono = true)
   {
      if(!LoggedUser::isLoggedIn())
         throw new Exception("Listing pins is a functionnality reserved to logged in users.");
      
      $sql = "SELECT posts_interactions.*, posts_interactions_pins.comment, posts.author, posts.date AS date_post 
      FROM posts 
      INNER JOIN posts_interactions 
      ON posts.id_post = posts_interactions.id_post
      INNER JOIN posts_interactions_pins 
      ON posts_interactions.id_interaction = posts_interactions_pins.id_interaction
      WHERE posts_interactions.user=? \n";
      if($chrono)
         $sql .= "ORDER BY posts_interactions.date DESC \n";
      else
         $sql .= "ORDER BY posts.date DESC \n";
      $sql .= "LIMIT ".$first.",".$nb;
      
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Pinned messages could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No pinned message has been found.');
      
      return $res;
   }
}

?>