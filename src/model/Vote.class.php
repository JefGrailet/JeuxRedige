<?php

/**
* Vote models an upvote or a downvote recorded for a (user, post) couple.
*/

class Vote
{
   protected $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the vote or the ID of the post
   * @throws Exception    If the vote cannot be found or does not exist
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
         NATURAL JOIN posts_interactions_votes 
         WHERE id_post=? && user=?";
         $this->_data = Database::secureRead($sql, array($arg, LoggedUser::$data['pseudo']), true);
         
         if($this->_data == NULL)
            throw new Exception('Vote does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Vote could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to record a new vote in the database. As the recording requires insertion in 
   * two tables and the update of a "posts" row, it performs a SQL transaction. This insert() 
   * method has also the particularity of not returning an object, as there is no further handling 
   * of a new vote after recording it within a same script.
   *
   * @param number $postID  ID of the post for which we are recording the vote
   * @param bool $upvote    True if the vote is an upvote (downvote otherwise)
   * @throws Exception      When the insertion of the interaction in the database fails, with the 
   *                        actual SQL error inside
   */
   
   public static function insert($postID, $upvote)
   {
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
      
      // SQL 2: insertion in posts_interactions_votes
      $sql2 = "INSERT INTO posts_interactions_votes VALUES(:id_interact, :vote)";
      $toInsert2 = array('id_interact' => Database::newId(), 'vote' => ($upvote ? 1 : -1));
      $res2 = Database::secureWrite($sql2, $toInsert2);
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Interaction could not be recorded: '. $res2[2]);
      }
      
      // SQL 3: updates the like/dislike count of associated posts row
      $sql3 = "UPDATE posts SET ";
      if($upvote)
         $sql3 .= "nb_likes=nb_likes+1 ";
      else
         $sql3 .= "nb_dislikes=nb_dislikes+1 ";
      $sql3 .= "WHERE id_post=?";
      $res3 = Database::secureWrite($sql3, array($postID));
      if($res3 != NULL)
      {
         Database::rollback();
         throw new Exception('Vote count in posts table could not be updated: '. $res3[2]);
      }
      
      Database::commit();
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Returns true if the vote is an upvote/like.
   *
   * @return bool  True if the vote is an upvote
   */
   
   public function isUpvote()
   {
      if($this->_data['vote'] > 0)
         return true;
      return false;
   }
   
   /*
   * Deletion method. No argument is required. Again, because multiple tables are involved, a SQL 
   * transaction is needed.
   *
   * @throws Exception  If the deletion could not occur properly (SQL error is provided)
   */
    
   public function delete()
   {
      Database::beginTransaction();
   
      // Deletion of the vote (posts_interactions; CASCADE will do the rest)
      $sqlVote = "DELETE FROM posts_interactions WHERE id_interaction=?";
      $resVote = Database::secureWrite($sqlVote, array($this->_data['id_interaction']), true);
      
      if(is_array($resVote))
      {
         Database::rollback();
         throw new Exception('Unable to delete the vote: '. $resVote[2]);
      }
      else if($resVote == 0)
      {
         Database::rollback();
         throw new Exception('Vote did not exist at first.');
      }
      
      // Updating the associated post entry
      $sql = "UPDATE posts SET ";
      if($this->isUpvote())
         $sql .= "nb_likes=nb_likes-1 ";
      else
         $sql .= "nb_dislikes=nb_dislikes-1 ";
      $sql .= "WHERE id_post=?";
      $resPost = Database::secureWrite($sql, array($this->_data['id_post']));
      if($resPost != NULL)
      {
         Database::rollback();
         throw new Exception('Vote count in posts table could not be updated: '. $resPost[2]);
      }
      
      Database::commit();
   }

}

?>