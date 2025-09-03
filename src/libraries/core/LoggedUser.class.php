<?php

/**
 * This file declares a static class to handle the logged in user (if any). Its methods are meant 
 * to verify the user is properly logged in and check if (s)he as special permissions, with a 
 * forced log out if something is wrong. For the class that can be used to create and manage users 
 * in general, see User class in model/.
 */

class LoggedUser
{
   public static $data = NULL; // Main array, contains pseudonym(s), used pseudonym and abilities
   public static $fullData = NULL; // Contains the full DB row matching this user (if logged)
   public static $messages = NULL; // Unread messages (N.B.: may be made more general later)
   
   /**
    * Private static method that forces log out by destroying $_SESSION variables as well as 
    * cookies (if they exist).
    */
   
   private static function forceLogOut()
   {
      $_SESSION['pseudonym'] = '';
      $_SESSION['password'] = '';
      if(!empty($_COOKIE['pseudonym']) && !empty($_COOKIE['password']))
      {
         $expire = $timestampNow - 24 * 60 * 60;
         setcookie('pseudonym', '', $expire);
         setcookie('password', '', $expire);
      }
   }
   
   /**
    * Private static method that performs the log in procedure, complete with recording the 
    * current time as the last time the user was logged in. Pseudonym and password are parameters 
    * because they are found either in $_SESSION variables or in cookies.
    *
    * @param string $pseudo  Pseudonym of the user to log in
    * @param string $pwd     Hashed password (SHA-1)
    * @return bool           True if the user was properly authenticated
    */
   
   private static function logInProcedure($pseudo, $pwd)
   {
      $sql = "SELECT * FROM users WHERE pseudo=? LIMIT 1";
      $arg = array(Utils::secure($pseudo));
      self::$fullData = Database::secureRead($sql, $arg, true);
      
      $timestampBan = Utils::toTimestamp(self::$fullData['last_ban_expiration']);
      $timestampNow = Utils::SQLServerTime();

      $allGood = false;
      if(count(self::$fullData) > 3 && self::$fullData != NULL)
         if(self::$fullData['confirmation'] === 'DONE' && $timestampNow > $timestampBan)
            if($pwd === self::$fullData['password'])
               $allGood = true;
      
      if(!$allGood)
      {
         self::forceLogOut();
         return false;
      }
      
      self::$data = array(
      'pseudo' => $pseudo,
      'function_pseudo' => self::$fullData['function_pseudo'],
      'used_pseudo' => $pseudo,
      'new_messages' => 0);
            
      $datetimeNow = Utils::toDatetime($timestampNow);
      $res = Database::secureWrite(
         "UPDATE users SET last_connection=? WHERE pseudo=?", 
         array($datetimeNow, self::$data['pseudo'])
      );
      
      if($res == NULL)
         self::$fullData['last_connection'] = $datetimeNow;
      
      return true;
   }
   
   /**
    * Conducts the full log in operation, i.e., it first tries the log in with $_SESSION variables 
    * (when available) then with cookies (=> long-term log in; again, only if available). If 
    * successful, a few additional operations are carried out to check the abilities of this user 
    * and get his/her amount of new messages (notifications in the future).
    */
   
   public static function init()
   {
      $res = false;
      
      if(!empty($_SESSION['pseudonym']) && !empty($_SESSION['password']))
      {
         $res = self::logInProcedure($_SESSION['pseudonym'], $_SESSION['password']);
      }
      else if(!empty($_COOKIE['pseudonym']) && !empty($_COOKIE['password']))
      {
         $res = self::logInProcedure($_COOKIE['pseudonym'], $_COOKIE['password']);
         if($res)
         {
            $_SESSION['pseudonym'] = $_COOKIE['pseudonym'];
            $_SESSION['password'] = $_COOKIE['password'];
         }
      }
      
      if(!$res)
         return;
      
      // Prepares user's permissions, which includes checking if he's using a function account.
      $defaultAbilities = array('function_name' => '', 
      'can_create_topics' => 'no', 
      'can_upload' => 'no', 
      'can_edit_all_posts' => 'no', 
      'can_edit_games' => 'no', 
      'can_edit_users' => 'no', 
      'can_mark' => 'no', 
      'can_lock' => 'no', 
      'can_delete' => 'no', 
      'can_ban' => 'no', 
      'can_invite' => 'no');

      $hasFunctionAccount = false;
      if(self::$data !== NULL && self::$data['function_pseudo'] !== NULL)
         if(strlen(self::$data['function_pseudo']) > 0)
            $hasFunctionAccount = true;
      
      $usingFunctionAccount = false;
      if($hasFunctionAccount)
      {
         $sql = "SELECT functions.* 
         FROM map_functions 
         NATURAL JOIN functions 
         WHERE function_pseudo=?";
         $secondAccount = Database::secureRead($sql, array(self::$data['function_pseudo']), true);
         
         if($secondAccount != NULL && count($secondAccount) > 3)
            if(isset($_SESSION['function_pseudo']))
               if($_SESSION['function_pseudo'] === sha1(self::$data['function_pseudo']))
                  $usingFunctionAccount = true;
         
         if($usingFunctionAccount)
         {
            self::$data = array_merge(self::$data, $secondAccount);
            self::$data['used_pseudo'] = self::$data['function_pseudo'];
         }
         else
            $defaultAbilities['function_name'] = $secondAccount['function_name'];
      }
      
      if(!$usingFunctionAccount)
      {
         if(self::$fullData['advanced_features'] === 'yes')
         {
            $defaultAbilities['can_create_topics'] = 'yes';
            $defaultAbilities['can_upload'] = 'yes';
            $defaultAbilities['can_invite'] = 'yes';
         }
         self::$data = array_merge(self::$data, $defaultAbilities);
      } 
      
      // Counts the amount of unread new messages.
      $sql = "SELECT pings.id_ping, pings.ping_type, pings.title, pings.emitter, pings.receiver 
      FROM map_pings 
      NATURAL JOIN pings
      WHERE map_pings.pseudo=? 
      AND map_pings.viewed='no'
      ORDER BY map_pings.last_update DESC, map_pings.id_ping DESC";
      
      self::$messages = Database::secureRead($sql, array(self::$data['pseudo']));
      $nbNew = count(self::$messages);
      if($nbNew == 0 || is_array(self:$messages[0]))
         self::$data['new_pings'] = $nbNew;
      else
         self::$data['new_pings'] = -1; // Alert other parts of the code that there's a problem
   }
   
   /**
    * Static method to quickly check the current user is logged in.
    * 
    * @return boolean  True if the user is logged in, false otherwise
    */
   
   public static function isLoggedIn()
   {
      return self::$data != NULL && is_array(self::$data);
   }
   
   /**
    * Returns a string describing the rank (or function, if being used) of the current user.
    *
    * @return string  The "rank" of this user
    */
   
   public static function rank()
   {
      if(self::isLoggedIn())
      {
         if(self::$data['used_pseudo'] == self::$data['function_pseudo'])
            return self::$data['function_name'];
         else
            return 'regular user';
      }
      return 'anonymous';
   }
}
