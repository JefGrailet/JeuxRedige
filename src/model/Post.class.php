<?php

/**
* Post class models a single message in a topic. Like other classes from the model, an instance
* corresponds to a row in the "posts" table in the database. The single field of the class is an
* array with the same fields/values as in the database. Methods allows the calling code to handle
* a message without explicitely addressing the database, in a high-level fashion. Finally, a
* static method can be used to create (insert) a new message.
*/

class Post
{
   private $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the message or the ID of that message
   * @throws Exception    If the message cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $this->_data = Database::secureRead("SELECT * FROM posts WHERE id_post=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Post does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Post could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new message in the database.
   *
   * @param integer $idTopic    ID of the topic where the new message is being posted
   * @param string $message     The message itself, formatted in HTML
   * @param string $anonPseudo  The pseudo used by the user, if posting anonymously (optional)
   * @return Post               The new message as a Post instance
   * @throws Exception          When the insertion of the message in the database fails, with the
   *                            actual SQL error inside
   */
   
   public static function insert($idTopic, $message, $anonPseudo = '')
   {
      $sql = "INSERT INTO posts VALUES('0', :id_topic, :author, :ip_author, :date,
              :content, '1970-01-01 00:00:00', '', '0', '0', '0', '0', :posted_as, '')";
   
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      if(strlen($anonPseudo) > 0)
      {
         $toInsert = array('id_topic' => $idTopic,
         'author' => $anonPseudo,
         'ip_author' => $_SERVER['REMOTE_ADDR'],
         'date' => $currentDate,
         'content' => $message,
         'posted_as' => 'anonymous');
      }
      else
      {
         $rank = 'regular user';
         if(strlen(LoggedUser::$data['function_pseudo']) > 0 && LoggedUser::$data['function_pseudo'] === LoggedUser::$data['used_pseudo'])
            $rank = LoggedUser::$data['function_name'];
         
         $toInsert = array('id_topic' => $idTopic,
         'author' => LoggedUser::$data['used_pseudo'],
         'ip_author' => $_SERVER['REMOTE_ADDR'],
         'date' => $currentDate,
         'content' => $message,
         'posted_as' => $rank);
      }
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Post could not be created: '. $res[2]);
      
      return new Post(Database::newId());
   }
   
   /*
   * Variant of the previous method which purpose solely consists of creating an automatic message. 
   * Typically, used for the very first message of a topic of comments.
   *
   * @param integer $idTopic          ID of the topic where the new message is being posted
   * @param string $message           The message itself, formatted in HTML
   * @param string $overridingPseudo  The pseudo associated to the post; by default, it's the user's 
   *                                  current pseudonym
   * @return Post                     The new message as a Post instance
   * @throws Exception                When the insertion of the message in the database fails, with the
   *                                  actual SQL error inside
   */
   
   public static function autoInsert($idTopic, $message, $overridingPseudo = '')
   {
      $sql = "INSERT INTO posts VALUES('0', :id_topic, :author, :ip_author, :date,
              :content, '1970-01-01 00:00:00', '', '0', '0', '0', '0', :posted_as, '')";
      
      // Deals with the values to insert
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $finalPseudo = LoggedUser::$data['used_pseudo'];
      $finalIP = $_SERVER['REMOTE_ADDR'];
      if(strlen($overridingPseudo) >= 3 && strlen($overridingPseudo) <= 20)
      {
         $finalPseudo = $overridingPseudo;
         $finalIP = '0.0.0.0';
      }
      
      $toInsert = array('id_topic' => $idTopic,
      'author' => $finalPseudo,
      'ip_author' => $finalIP,
      'date' => $currentDate,
      'content' => $message,
      'posted_as' => 'author');
      
      // Insertion
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Post could not be created: '. $res[2]);
      
      return new Post(Database::newId());
   }
   
   /*
   * Sums up a given value to the "bad score" counter.
   *
   * @param int $weight  The value to add to the "bad score" field
   */
   
   public function addToBadScore($weight)
   {
      $this->_data['bad_score'] += $weight;
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Method to finalize a new post, which consists in set/update its attachment and a slight 
   * modification of its text (replace some file path prefixes) if necessary. This is always done 
   * after the insertion or edition of the post,  because uploads are not moved as long as the 
   * post is not created/modified.
   *
   * @param string $newAttachment  The attachment (already formatted)
   * @param string $newContent     The updated content (optional)
   * @throws Exception             When the update of the post in the database fails, with the
   *                               actual SQL error inside
   */
   
   public function finalize($newAttachment, $newContent = '')
   {
      $sql = "UPDATE posts SET attachment=?";
      $arg = array($newAttachment);
      if(strlen($newContent) > 0)
      {
         $sql .= ", content=?";
         array_push($arg, $newContent);
      }
      $sql .= " WHERE id_post=?";
      array_push($arg, $this->_data['id_post']);
      
      $res = Database::secureWrite($sql, $arg);

      if($res != NULL)
         throw new Exception('Post could not be updated: '. $res[2]);

      $this->_data['attachment'] = $newAttachment;
      if(strlen($newContent) > 0)
         $this->_data['content'] = $newContent;
   }
   
   /*
   * Method to undo all reports. It should be only called in scripts that can be used only by 
   * admins/moderators. After the call, the message will be visible by everyone again. Nothing is 
   * passed, nothing is returned. As the method also needs to delete all recorded alerts, it 
   * performs the action with two SQL requests in a single transaction.
   *
   * @throws Exception  If the message could not be updated (will contain the SQL error)
   */

   public function cancelAlerts()
   {
      // Looks up the IDs to delete (MySQL forbids inner query with same table in DELETE)
      $sql0 = 'SELECT id_interaction 
      FROM posts_interactions_alerts 
      NATURAL JOIN posts_interactions 
      WHERE posts_interactions.id_post=?';
      $res0 = Database::secureRead($sql0, array($this->_data['id_post']));
      
      if($res0 != NULL && !is_array($res0[0]) && count($res0) == 3)
         throw new Exception('Alerts could not be listed: '. $res0[2]);
      
      $IDs = '';
      for($i = 0; $i < count($res0); $i++)
      {
         if($i > 0)
            $IDs .= ', ';
         $IDs .= $res0[$i]['id_interaction'];
      }
      
      // The deletion process starts here
      Database::beginTransaction();
      
      $sql1 = "DELETE FROM posts_interactions 
      WHERE id_interaction IN ($IDs)";
      $res1 = Database::hardWrite($sql1, true);
      
      // CASCADE feature will delete also the corresponding entries from posts_interactions_alerts
      
      if(is_array($res1))
      {
         Database::rollback();
         throw new Exception('Unable to delete the alerts: '. $res1[2]);
      }
      
      $res2 = Database::secureWrite("UPDATE posts SET bad_score='0' WHERE id_post=?",
                          array($this->_data['id_post']));
         
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Post could not be updated: '. $res2[2]);
      }
      
      Database::commit();
      $this->_data['bad_score'] = 0;
   }
   
   /*
   * Method to compute a list of users who interacted with this post along what they did: upvote 
   * or downvote and reporting (if any). The method does not require any argument. The format of 
   * the output is the following:
   *
   * ['pseudo'] -> ['vote' => 'upvote|downvote|[Empty]', 'report' => 'yes||[motivation]|[Empty]']
   *
   * @return mixed[]    The list of users who interacted with the post, formatted as above
   * @throws Exception  If an issue occurs while retrieving interactions (SQL error provided)
   */
   
   public function listInteractions()
   {
      $res = array();
      
      $sql = "SELECT posts_interactions.user, posts_interactions_votes.vote, 
      posts_interactions_alerts.motivation, posts_interactions_alerts.function_pseudo 
      FROM posts_interactions 
      NATURAL LEFT OUTER JOIN posts_interactions_votes 
      NATURAL LEFT OUTER JOIN posts_interactions_alerts 
      WHERE id_post=?
      ORDER BY posts_interactions.date";
      
      $interactions = Database::secureRead($sql, array($this->_data['id_post']));
      if($interactions != NULL && !is_array($interactions[0]) && count($interactions) == 3)
         throw new Exception('Interactions could not be found: '. $interactions[2]);
      
      if($interactions != NULL)
      {
         $users = array();
         for($i = 0; $i < count($interactions); $i++)
         {
            // Vote
            if($interactions[$i]['vote'] != NULL)
            {
               if(!in_array($interactions[$i]['user'], $users))
               {
                  $res[$interactions[$i]['user']] = array('vote' => '', 'report' => '');
                  array_push($users, $interactions[$i]['user']);
               }
            
               if($interactions[$i]['vote'] > 0)
                  $res[$interactions[$i]['user']]['vote'] = 'upvote';
               else
                  $res[$interactions[$i]['user']]['vote'] = 'downvote';
            }
            // Alert
            else if($interactions[$i]['motivation'] != NULL)
            {
               if($interactions[$i]['function_pseudo'] != NULL)
               {
                  $res[$interactions[$i]['function_pseudo']] = array('vote' => '', 'report' => 'yes||'.$interactions[$i]['motivation']);
               }
               else
               {
                  if(!in_array($interactions[$i]['user'], $users))
                  {
                     $res[$interactions[$i]['user']] = array('vote' => '', 'report' => '');
                     array_push($users, $interactions[$i]['user']);
                  }
                  $res[$interactions[$i]['user']]['report'] = 'yes||'.$interactions[$i]['motivation'];
               }
            }
         }
      }
      
      return $res;
   }
   
   /*
   * Lists the motivations of the alerts for the current post. The method does not require any 
   * argument. The format of the output is the following:
   *
   * ['motivation'] -> [amount of occurrences]
   *
   * @return mixed[]    The motivations for (temporarily) hiding the post, formatted as above
   * @throws Exception  If an issue occurs while retrieving motivations (SQL error provided)
   */
   
   public function listAlertMotivations()
   {
      $res = array();
      
      $sql = "SELECT posts_interactions_alerts.motivation 
      FROM posts_interactions 
      NATURAL JOIN posts_interactions_alerts 
      WHERE id_post=?
      ORDER BY posts_interactions_alerts.motivation";
      
      $alerts = Database::secureRead($sql, array($this->_data['id_post']));
      if($alerts != NULL && !is_array($alerts[0]) && count($alerts) == 3)
         throw new Exception('Alerts could not be found: '. $alerts[2]);
      
      $alreadyIn = array();
      if($alerts != NULL)
      {
         for($i = 0; $i < count($alerts); $i++)
         {
            if(in_array($alerts[$i]['motivation'], $alreadyIn))
               $res[$alerts[$i]['motivation']] += 1;
            else
            {
               $res[$alerts[$i]['motivation']] = 1;
               array_push($alreadyIn, $alerts[$i]['motivation']);
            }
         }
      }
      
      return $res;
   }
   
   /*
   * Method to edit a message. Aside updating the message itself, this method will also update the
   * amount of editions along the last modification date. It is also worth noting that it is the 
   * responsability of the calling code to check whether the logged user can edit this post or 
   * not. Also, if the message is being edited by a user who created that message and who has a 
   * function account, the pseudonym of the editor will be the pseudonym of the user when he 
   * created the message.
   *
   * Amount of editions and last modification date are untouched if the editor is the same user as 
   * the author of the original message and if the delay between this edition and the last edition 
   * (which is the date at which the post was created, if never edited before) is smaller than 120 
   * seconds. If these conditions are fulfilled, the function returns 0, otherwise it returns a 
   * non zero value (e.g. 1).
   *
   * @param string   $newContent        The updated message, formatted in HTML
   * @return integer                    0 if this is a quick edit, 1 otherwise
   * @throws Exception                  If the message could not be updated (with SQL error)
   */
   
   public function edit($newContent)
   {
      if($this->_data['posted_as'] === 'author')
         return 0; // Prevents edition of an automatic message
      
      $editionPseudo = LoggedUser::$data['used_pseudo'];
      if($this->_data['posted_as'] !== 'anonymous' && $this->_data['author'] !== $editionPseudo && 
         $this->_data['author'] === LoggedUser::$data['pseudo'])
         $editionPseudo = LoggedUser::$data['pseudo'];
      
      $editingSelf = true;
      if($this->_data['posted_as'] === 'anonymous' || 
         ($this->_data['author'] !== LoggedUser::$data['pseudo'] && 
          $this->_data['author'] !== LoggedUser::$data['function_pseudo']))
         $editingSelf = false;
   
      $lastTimestamp = 0;
      if(intval($this->_data['nb_edits']) > 0)
         $lastTimestamp = Utils::toTimestamp($this->_data['last_edit']);
      else
         $lastTimestamp = Utils::toTimestamp($this->_data['date']);
      $currentTimestamp = Utils::SQLServerTime();
      
      // Quick edit
      if($editingSelf && ($currentTimestamp - $lastTimestamp) <= 120)
      {
         $sql = "UPDATE posts SET content=? WHERE id_post=?";
         $res = Database::secureWrite($sql, array($newContent, $this->_data['id_post']));

         if($res != NULL)
            throw new Exception('Post could not be updated: '. $res[2]);

         $this->_data['content'] = $newContent;
         return 0;
      }
      
      // Full edit
      $currentDate = Utils::toDatetime($currentTimestamp);
      
      $sql = "UPDATE posts 
      SET content=?, last_edit=?, last_editor=?, nb_edits=nb_edits+1 
      WHERE id_post=?";
      
      $arg = array($newContent, $currentDate, $editionPseudo, $this->_data['id_post']);
      $res = Database::secureWrite($sql, $arg);

      if($res != NULL)
         throw new Exception('Post could not be updated: '. $res[2]);

      $this->_data['content'] = $newContent;
      $this->_data['last_edit'] = $currentDate;
      $this->_data['last_editor'] = $editionPseudo;
      $this->_data['nb_edits'] += 1;
      return 1;
   }
   
   /*
   * Method to list all versions of a post, if this one has archived versions. This method is 
   * similar to getPosts() in the class Topic, except there is no offset or amount of posts to 
   * display as parameters.
   *
   * @return mixed[]       The previous versions of the post, or NULL if it was never edited
   * @throws Exception     If messages could not be found (SQL error is provided)
   */
   
   public function getPreviousVersions()
   {
      if(intval($this->_data['nb_edits']) == 0)
         return NULL;
   
      $sql = 'SELECT * FROM posts_history WHERE id_post=? ORDER BY version';
      $res = Database::secureRead($sql, array($this->_data['id_post']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Previous versions could not be found: '. $res[2]);
      else if($res == NULL)
         return NULL;
      
      return $res;
   }
   
   /*
    * Static method to learn the delay between now and the lattest post of the current user, if 
    * logged in. The responsibility of such task has been attributed to the Post class, since it 
    * involves a SQL request on the "posts" table.
    *
    * @return integer    The delay in seconds since user's lattest post, or -1 if not logged in.
    *                    If the user never posted, the current timestamp is returned.
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
      
      $sql = 'SELECT date FROM posts WHERE author=? ORDER BY date DESC LIMIT 1';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Could not get date of the lattest post: '. $res[2]);
      else if($res == NULL)
         return $currentTime;
      
      $delay = $currentTime - Utils::toTimestamp($res['date']);
      return $delay;
   }
   
   /*
   * Extracts the set of interactions of the current user (if logged) for this post.
   *
   * @throws Exception     If an SQL error occurs while checking the database
   */
   
   public function getUserInteraction()
   {
      if(!LoggedUser::isLoggedIn())
         return false;

      $user = LoggedUser::$data['pseudo'];
      $postID = $this->_data['id_post'];
      
      $sql = "SELECT posts_interactions.*, 
      posts_interactions_votes.vote, 
      posts_interactions_alerts.motivation, 
      posts_interactions_pins.comment 
      FROM posts_interactions
      NATURAL LEFT OUTER JOIN posts_interactions_votes 
      NATURAL LEFT OUTER JOIN posts_interactions_alerts 
      NATURAL LEFT OUTER JOIN posts_interactions_pins 
      WHERE posts_interactions.user=? && posts_interactions.id_post=?";
      $res = Database::secureRead($sql, array($user, $postID));

      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot check interactions of '.$user.' on post '.$postID.'): '. $res[2]);

      $this->_data['user_vote'] = 0;
      $this->_data['user_alert'] = 'no';
      $this->_data['user_pin'] = '';
      if($res != NULL)
      {
         for($i = 0; $i < count($res); ++$i)
         {
            if($res[$i]['vote'] != NULL)
               $this->_data['user_vote'] = $res[$i]['vote'];
            else if($res[$i]['motivation'] != NULL)
               $this->_data['user_alert'] = $res[$i]['motivation'];
            else if($res[$i]['comment'] != NULL)
               $this->_data['user_pin'] = $res[$i]['comment'];
         }
      }
   }
   
   /*
   * Extracts the interactions of the current user (if logged) for a 2D array of posts (in array 
   * format).
   *
   * @param mixed posts[]  The posts for which we want the interactions
   * @throws Exception     If an SQL error occurs while checking the database
   */
   
   public static function getUserInteractions(&$posts)
   {
      if(!LoggedUser::isLoggedIn())
         return false;
      
      $postsIDs = '(';
      for($i = 0; $i < count($posts); $i++)
      {
         if($i > 0)
            $postsIDs .= ', ';
         $postsIDs .= $posts[$i]['id_post'];
      }
      $postsIDs .= ')';
      
      $user = LoggedUser::$data['pseudo'];
      $sql = "SELECT posts_interactions.id_interaction, 
      posts_interactions.id_post, 
      posts_interactions.date, 
      posts_interactions_votes.vote, 
      posts_interactions_alerts.motivation, 
      posts_interactions_pins.comment 
      FROM posts_interactions
      NATURAL LEFT OUTER JOIN posts_interactions_votes 
      NATURAL LEFT OUTER JOIN posts_interactions_alerts 
      NATURAL LEFT OUTER JOIN posts_interactions_pins 
      WHERE posts_interactions.user=? && posts_interactions.id_post IN $postsIDs";
      $res = Database::secureRead($sql, array($user));

      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Cannot check interactions of '.$user.' on posts '.$postsIDs.': '. $res[2]);
      
      $formattedRes = array();
      $keys = array();
      for($i = 0; $i < count($res); $i++)
      {
         if(!in_array($res[$i]['id_post'], $keys))
         {
            $formattedRes[$res[$i]['id_post']] = array('vote' => NULL, 'motivation' => NULL, 'comment' => NULL);
            array_push($keys, $res[$i]['id_post']);
         }
         
         if($res[$i]['vote'] != NULL)
            $formattedRes[$res[$i]['id_post']]['vote'] = $res[$i]['vote'];
         else if($res[$i]['motivation'] != NULL)
            $formattedRes[$res[$i]['id_post']]['motivation'] = $res[$i]['motivation'];
         else if($res[$i]['comment'] != NULL)
            $formattedRes[$res[$i]['id_post']]['comment'] = $res[$i]['comment'];
      }
      
      for($i = 0; $i < count($posts); $i++)
      {
         $posts[$i]['user_vote'] = 0;
         $posts[$i]['user_alert'] = 'no';
         $posts[$i]['user_pin'] = '';
         if(in_array($posts[$i]['id_post'], $keys))
         {
            if($formattedRes[$posts[$i]['id_post']]['vote'] != NULL)
               $posts[$i]['user_vote'] = $formattedRes[$posts[$i]['id_post']]['vote'];
            if($formattedRes[$posts[$i]['id_post']]['motivation'] != NULL)
               $posts[$i]['user_alert'] = $formattedRes[$posts[$i]['id_post']]['motivation'];
            if($formattedRes[$posts[$i]['id_post']]['comment'] != NULL)
               $posts[$i]['user_pin'] = $formattedRes[$posts[$i]['id_post']]['comment'];
         }
      }
   }
}

?>
