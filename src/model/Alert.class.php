<?php

/**
* Alert models an alert recorded for a (user, post) couple.
*/

class Alert
{
   protected $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the alert or the ID of the post
   * @throws Exception    If the alert cannot be found or does not exist
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
         NATURAL JOIN posts_interactions_alerts 
         WHERE id_post=? && user=?";
         $this->_data = Database::secureRead($sql, array($arg, LoggedUser::$data['pseudo']), true);
         
         if($this->_data == NULL)
            throw new Exception('Alert does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Alert could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to record a new alert in the database. As the recording requires insertion in 
   * two tables and the update of a "posts" row, it performs a SQL transaction.
   *
   * @param number $post    The post for which we are recording the alert
   * @param string $reason  The motivation for the alert (as a string, max. 100 characters)
   * @param int             The new value for the "bad_score" field of the post
   * @throws Exception      When the insertion of the interaction in the database fails, with the 
   *                        actual SQL error inside
   */
   
   public static function insert($post, $reason)
   {
      $postID = $post->get('id_post');
      
      // If user has a function pseudo, the alert is done as if under his/her function account
      $alertPseudo = NULL;
      $alertWeight = 1;
      if(LoggedUser::$data['function_pseudo'] != NULL && strlen(LoggedUser::$data['function_pseudo']) > 0)
      {
         $alertPseudo = LoggedUser::$data['function_pseudo'];
         $alertWeight = 10;
      }
      
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
      
      // SQL 2: insertion in posts_interactions_alerts
      $sql2 = "INSERT INTO posts_interactions_alerts VALUES(:id_interact, :motivation, :function_pseudo)";
      $toInsert2 = array('id_interact' => Database::newId(), 'motivation' => $reason, 'function_pseudo' => $alertPseudo);
      $res2 = Database::secureWrite($sql2, $toInsert2);
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Interaction could not be recorded: '. $res2[2]);
      }
      
      // SQL 3: updates the like/dislike count of associated posts row
      $sql3 = "UPDATE posts SET bad_score=bad_score+? WHERE id_post=?";
      $res3 = Database::secureWrite($sql3, array($alertWeight, $postID));
      if($res3 != NULL)
      {
         Database::rollback();
         throw new Exception('Bad score in posts table could not be updated: '. $res3[2]);
      }
      
      Database::commit();
      $post->addToBadScore($alertWeight); // N.B.: Post.class.php normally required before Alert.class.php
      return $post->get('bad_score');
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Static method to get the total amount of alerts.
   *
   * @return integer    The total amount of alerts stored in the DB
   * @throws Exception  If alerts could not be counted (SQL error is provided)
   */
   
   public static function countAlerts()
   {
      $sql = 'SELECT COUNT(*) AS nb 
      FROM posts_interactions 
      NATURAL JOIN posts_interactions_alerts';
      $res = Database::hardRead($sql, true);
      
      if($res != NULL && count($res) == 3)
         throw new Exception('Alerts could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to get a set of alerts, in the same way one can get a set of posts in a topic.
   *
   * @param integer $first  The index of the first alert of the set
   * @param integer $nb     The maximum amount of alerts to retrieve
   * @return mixed[]        The alerts that were found
   * @throws Exception      If alerts could not be found (SQL error is provided)
   */
   
   public static function getAlerts($first, $nb)
   {
      $sql = 'SELECT * 
      FROM posts_interactions 
      NATURAL JOIN posts_interactions_alerts 
      ORDER BY date DESC LIMIT '.$first.','.$nb;
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Alerts could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No alert has been found.');
      
      return $res;
   }
   
   /*
   * Static method to annotate a set of alerts with some details on the related posts, most 
   * notably the author of the related post and the title of the topic it's found in.
   *
   * @param mixed[] $alerts  Reference to the alerts to annotate
   * @throws Exception       If the details could not be retrieved (SQL error is provided)
   */
   
   public static function getPostDetails(&$alerts)
   {
      $sql = 'SELECT posts.id_post, posts.author, posts.date AS date_post, topics.title, topics.id_topic 
      FROM posts 
      INNER JOIN topics ON posts.id_topic=topics.id_topic 
      WHERE posts.id_post IN (';
      for($i = 0; $i < count($alerts); $i++)
      {
         if($i > 0)
            $sql .= ', ';
         $sql .= $alerts[$i]['id_post'];
      }
      $sql .= ') ORDER BY posts.id_post';
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Post details could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No post has been found.');
      
      // Formats output for next operation
      $formatted = array();
      for($i = 0; $i < count($res); $i++)
      {
         $formatted[$res[$i]['id_post']] = array($res[$i]['author'],
                                                 $res[$i]['title'], 
                                                 $res[$i]['date_post'], 
                                                 $res[$i]['id_topic']);
      }
      $postsIDs = array_keys($formatted);
      
      // Annotates the alerts
      for($i = 0; $i < count($alerts); $i++)
      {
         if(in_array($alerts[$i]['id_post'], $postsIDs))
         {
            $alerts[$i]['author'] = $formatted[$alerts[$i]['id_post']][0];
            $alerts[$i]['title'] = $formatted[$alerts[$i]['id_post']][1];
            $alerts[$i]['date_post'] = $formatted[$alerts[$i]['id_post']][2];
            $alerts[$i]['id_topic'] = $formatted[$alerts[$i]['id_post']][3];
         }
      }
   }
   
   /*
   * No deletion method. A user cannot rollback on an alert. A moderator, however, can undo all 
   * alerts after "fixing" the bad post.
   */
}

?>