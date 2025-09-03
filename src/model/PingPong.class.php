<?php

/**
* PingPong is an extension of Ping which models a two-way conversation between two users. The 
* messages exchanged between both users are known as "pongs" in the DB. The Ping itself will 
* always have, as value for the "message" field, the first message of that conversion.
*
* It is worth noting there is no "Pong" class and that messages are directly handled in this 
* class, notably through a method to list all messages and a method to add a new one (which both 
* creates the pong and updates the map_pings and pings lines accordingly with a single SQL 
* transaction).
*
* The motivation behind this design choice is that messages in ping pong discussion do not provide 
* any form of interaction, unlike public posts, which should therefore be treated in a 
* object-oriented fashion.
*/

// N.B.: Ping.class.php must be required first!

class PingPong extends Ping
{
   /*
   * Constructor. It directly uses the constructor of the parent class (see Ping.class.php).
   */
   
   public function __construct($arg)
   {
      parent::__construct($arg);
   }   
   
   /*
   * Static method to insert a new user ping in the database. Due to using several SQL requests, 
   * this method requires a SQL transaction.
   *
   * @param string $receiver  The pseudonym of the user who will receive the message
   * @param string $title     Title of the notification
   * @param string $message   Message of the notification
   * @throws Exception        When the insertion of the notification in the database fails, with 
   *                          the actual SQL error inside
   */
   
   public static function insert($receiver, $title, $message)
   {
      Database::beginTransaction();
      
      $formattedMessage = '['.strlen($message).']'.$message;
      
      $newPingID = 0;
      try
      {
         // Ping creation
         $currentDate = Utils::toDatetime(Utils::SQLServerTime());
         $toInsert = array('emitter' => LoggedUser::$data['pseudo'], 
         'receiver' => $receiver, 
         'title' => $title, 
         'message' => $formattedMessage, 
         'emission_date' => $currentDate);
         
         $sql = "INSERT INTO pings VALUES(0, :emitter, :receiver, 'ping pong', 'pending', 
                 :title, :message, :emission_date)";
         
         $res = Database::secureWrite($sql, $toInsert);
         if($res != NULL)
            throw new Exception('Could not insert new user ping: '. $res[2]);
            
         $newPingID = Database::newId();
         
         // map_pings line insertion
         $toInsertPart1 = array('id_ping' => $newPingID,
         'pseudo' => $receiver,
         'last_update' => $currentDate);
         
         $sqlPart1 = "INSERT INTO map_pings VALUES(:pseudo, :id_ping, 'no', :last_update)";
         
         $resPart1 = Database::secureWrite($sqlPart1, $toInsertPart1);
         if($resPart1 != NULL)
            throw new Exception('Could not add receiver: '. $resPart1[2]);
         
         $toInsertPart2 = array('id_ping' => $newPingID,
         'pseudo' => LoggedUser::$data['pseudo'],
         'last_update' => $currentDate);
         
         $sqlPart2 = "INSERT INTO map_pings VALUES(:pseudo, :id_ping, 'yes', :last_update)";
         
         $resPart2 = Database::secureWrite($sqlPart2, $toInsertPart2);
         if($resPart2 != NULL)
            throw new Exception('Could not add receiver: '. $resPart2[2]);
         
         // Recording first "pong"
         $toInsertPong = array('id_ping' => $newPingID, 
         'author' => LoggedUser::$data['pseudo'], 
         'ip_author' => $_SERVER['REMOTE_ADDR'], 
         'date' => $currentDate, 
         'message' => $message);
         
         $sqlPong = "INSERT INTO pongs VALUES(0, :id_ping, :author, :ip_author, :date, :message)";
         
         $resPong = Database::secureWrite($sqlPong, $toInsertPong);
         if($resPong != NULL)
            throw new Exception('Could not record pong: '. $resPong[2]);
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw $e;
      }
      
      return new PingPong($newPingID);
   }
   
   /*
   * Counts the number of messages related to this discussion.
   *
   * @return number     The amount of messages
   * @throws Exception  If the messages could not be found
   */
   
   public function countPongs()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM pongs WHERE id_ping=?';
      $arg = array($this->_data['id_ping']);
      $res = Database::secureRead($sql, $arg, true);
      
      if(count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      else if($res == NULL)
         return 0;
      
      return $res['nb'];
   }

   /*
   * Gets a set of messages recorded for that PingPong object (practically the same thing as the 
   * getPosts() method of Topic.class.php).
   *
   * @param number $first  The index of the first message of the set
   * @param number $nb     The maximum amount of messages to retrieve
   * @return mixed[]       The messages that were found
   * @throws Exception     If messages could not be found (SQL error is provided) or if no message
   *                       could be found with the given criteria
   */
   
   public function getPongs($first, $nb)
   {
      $sql = 'SELECT * FROM pongs WHERE id_ping=? ORDER BY date LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array($this->_data['id_ping']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Messages could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No message has been found.');
      
      return $res;
   }
   
   /*
   * Appends the discussion with a new message. Everything that should be refreshed in the DB is 
   * updated in the process. The new message can come from any of both users involved in the 
   * discussion. It is worth noting that, unlike insert(), there is no internal SQL transaction 
   * here as the method is normally called along others and motivate an external call to PDO's 
   * beginTransaction() method.
   *
   * @param string $message  The message to append to the discussion
   * @throws Exception       If the message could not be created or if a DB line related to the 
   *                         discussion could not be properly updated (SQL error is provided)
   */
   
   public function append($message)
   {
      // Determines which party is posting
      $otherUser = $this->_data['emitter'];
      if(LoggedUser::$data['pseudo'] === $otherUser)
         $otherUser = $this->_data['receiver'];
      
      /*
       * The message field, in the DB, is formatted this way:
       *
       * [length of the emitter's last message].emitter's last message.receiver's last message
       *
       * The goal is that each party is able to see the opposite party's last message in the ping 
       * main page while keeping a single "message" field.
       */
      
      $prevMessage = $this->_data['message'];
      $emitterMessage = '';
      $receiverMessage = '';
      $limitBlock = strpos($prevMessage, ']');
      if($limitBlock != FALSE)
      {
         $lengthEmitterMessage = intval(substr($prevMessage, 1, $limitBlock - 1));
         $emitterMessage = substr($prevMessage, $limitBlock + 1, $lengthEmitterMessage);
         $receiverMessage = substr($prevMessage, $limitBlock + 1 + $lengthEmitterMessage);
      }
      else
         $emitterMessage = $prevMessage;
      
      // Computes the new message field
      $newMessage = '';
      if($otherUser == $this->_data['emitter'])
         $newMessage = '['.strlen($emitterMessage).']'.$emitterMessage.$message;
      else
         $newMessage = '['.strlen($message).']'.$message.$receiverMessage;
      
      try
      {
         // Records new pong
         $currentDate = Utils::toDatetime(Utils::SQLServerTime());
         $newPong = array('id_ping' => $this->_data['id_ping'], 
         'author' => LoggedUser::$data['pseudo'], 
         'ip_author' => $_SERVER['REMOTE_ADDR'], 
         'date' => $currentDate, 
         'message' => $message);
         
         $sqlPong = "INSERT INTO pongs VALUES(0, :id_ping, :author, :ip_author, :date, :message)";
         
         $resPong = Database::secureWrite($sqlPong, $newPong);
         if($resPong != NULL)
            throw new Exception('Could not record pong: '. $resPong[2]);
         
         // Updates ping
         $sqlPing = "UPDATE pings SET message=? WHERE id_ping=?";
         $resPing = Database::secureWrite($sqlPing, array($newMessage, $this->_data['id_ping']));
         if($resPing != NULL)
            throw new Exception('Could not update ping: '. $resPing[2]);
         
         // For the user who is posting, simply update the last_update field
         $sql1 = "UPDATE map_pings SET last_update=? WHERE id_ping=? && pseudo=?";
         $arg1 = array($currentDate, $this->_data['id_ping'], LoggedUser::$data['pseudo']);
         $res1 = Database::secureWrite($sql1, $arg1);
      
         if($res1 != NULL)
            throw new Exception('Ping mapping of current user could not be updated: '. $res1[2]);
         
         // For the other user, updates last_update field and resets viewed field to "no"
         $sql2 = "UPDATE map_pings SET last_update=?, viewed='no' WHERE id_ping=? && pseudo=?";
         $arg2 = array($currentDate, $this->_data['id_ping'], $otherUser);
         $res2 = Database::secureWrite($sql2, $arg2);
      
         if($res2 != NULL)
            throw new Exception('Ping mapping of the other user could not be updated: '. $res2[2]);
      }
      catch(Exception $e)
      {
         throw $e;
      }
   }
   
   /*
    * Static method to learn the delay between now and the lattest discussion created by the 
    * current user, if logged in. The responsibility of such task has been attributed to the 
    * PingPong class.
    *
    * @return integer    The delay in seconds since user's lattest ping, or -1 if not logged in. 
    *                    If the user never sent pings, the current timestamp is returned.
    * @throws Exception  If the SQL request could not be executed (SQL error is provided)
    */
   
   public static function getUserDelay()
   {
      $currentTime = Utils::SQLServerTime();
      
      if(!LoggedUser::isLoggedIn())
         return -1;
      
      $sql = 'SELECT emission_date FROM pings WHERE emitter=? && ping_type=\'ping pong\' ORDER BY emission_date DESC LIMIT 1';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Could not get date of the lattest created discussion: '. $res[2]);
      else if($res == NULL)
         return $currentTime;
      
      $delay = $currentTime - Utils::toTimestamp($res['emission_date']);
      return $delay;
   }
   
   /*
    * Static method, very similar to the previous, but it gives the delay since the last "pong" 
    * entry from this user instead.
    *
    * @return integer    The delay in seconds since user's lattest pong, or -1 if not logged in. 
    *                    If the user never posted anything, the current timestamp is returned.
    * @throws Exception  If the SQL request could not be executed (SQL error is provided)
    */
   
   public static function getUserDelayBis()
   {
      $currentTime = Utils::SQLServerTime();
      
      if(!LoggedUser::isLoggedIn())
         return -1;
      
      $sql = 'SELECT date FROM pongs WHERE author=? ORDER BY date DESC LIMIT 1';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Could not get date of the lattest message: '. $res[2]);
      else if($res == NULL)
         return $currentTime;
      
      $delay = $currentTime - Utils::toTimestamp($res['date']);
      return $delay;
   }
}

?>
