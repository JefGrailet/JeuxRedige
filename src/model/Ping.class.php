<?php

/**
* Ping class models the system of private messages of Project AG, which is actually closer to an 
* alert system. Indeed, private discussions can only occur between two protagonists and are not 
* supposed to be a kind of chat; most people will probably prefer using other means like Skype or 
* Facebook to chat with more interactivity and more people at the same time. Moreover, the ping 
* system also encompasses any kind of automatic message, such as invitations to exchange personal 
* details (since there will be no such thing as a public profile), reviews for articles, etc.
*
* Methods allows the calling code to handle a ping without explicitely addressing the database, 
* in a high-level fashion. As this class is more like a superclass (each type of ping has its 
* own features), there is no insert() method here.
*/

class Ping
{
   protected $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the ping or the ID of that ping
   * @throws Exception    If the ping cannot be found or does not exist
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
         FROM pings 
         NATURAL JOIN map_pings 
         WHERE pings.id_ping=? && map_pings.pseudo=?";
      
         $this->_data = Database::secureRead($sql, array($arg, LoggedUser::$data['pseudo']), true);
         
         if($this->_data == NULL)
            throw new Exception('Ping does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Ping could not be found: '. $this->_data[2]);
      }
   }

   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }

   /*
   * Method to update the "map_pings" entry for the current user to signal that he read his/her 
   * new ping. It is worth noting nothing happens if the "viewed" field is already set to "yes".
   *
   * @throws Exception   If the update fails for some reason (SQL error is provided)
   */
   
   public function updateView()
   {
      if(Utils::check($this->_data['viewed']))
         return;
      
      $sql = "UPDATE map_pings SET viewed='yes' WHERE id_ping=? && pseudo=?";
      $arg = array($this->_data['id_ping'], LoggedUser::$data['pseudo']);
      $res = Database::secureWrite($sql, $arg);
   
      if($res != NULL)
         throw new Exception('Ping mapping could not be updated: '. $res[2]);
      
      $this->_data['viewed'] = 'yes';
   }
   
   /*
   * Method to archive a ping (any kind).
   *
   * @throws Exception   If the archiving could not be achieved
   */
   
   public function archive()
   {
      $sql = "UPDATE pings SET state='archived' WHERE id_ping=?";
      $arg = array($this->_data['id_ping']);
      $res = Database::secureWrite($sql, $arg);
   
      if($res != NULL)
         throw new Exception('Ping could not be archived: '. $res[2]);
      
      $this->_data['archived'] = 'yes';
   }
   
   /*
   * Method to delete the mapping of this user to the ping. The ping itself is not deleted for the 
   * sake of keeping track of anything that could be problematic.
   *
   * @throws Exception   If the deletion fails for some (reason SQL error provided) or if the 
   *                     ping is not archived
   */
   
   public function deletePing()
   {
      if($this->_data['state'] !== 'archived')
         throw new Exception('Ping can only be deleted after being archived');
      
      $sql1 = "DELETE FROM map_pings WHERE id_ping=? && pseudo=?";
      $arg1 = array($this->_data['id_ping'], LoggedUser::$data['pseudo']);
      $res1 = Database::secureWrite($sql1, $arg1);
      
      if($res1 != NULL)
         throw new Exception('Ping mapping could not be deleted: '. $res1[2]);
      
      $this->_data = NULL;
   }
   
   /*
   * Static method to update all the "map_pings" entries for the current user and the pings that 
   * are not a form of discussion with another user (i.e., everything automatic).
   *
   * @throws Exception   If the update fails for some reason (SQL error is provided)
   */
   
   public static function updateAllViews()
   {
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      
      $sql = "UPDATE map_pings 
      NATURAL JOIN pings 
      SET viewed='yes' 
      WHERE ping_type!='ping pong' && viewed='no' && pseudo=?";
      $arg = array(LoggedUser::$data['pseudo']);
      $res = Database::secureWrite($sql, $arg);
   
      if($res != NULL)
         throw new Exception('Ping mappings could not be updated: '. $res[2]);
   }
   
   /*
   * Static method to get the total number of pings of the current user. It is assumed the user 
   * is logged in when this method is called.
   *
   * @return number     The total number of pings
   * @throws Exception  If pings could ne counted (SQL error is provided)
   */
   
   public static function countPings()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM map_pings WHERE pseudo=?';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(count($res) == 3)
         throw new Exception('Pings could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Variant of countPings() method. Instead of providing the total amount of pings, it provides 
   * an array of two cells with the first giving the total of pings BEFORE this one and the second 
   * providing the total AFTER. It is helpful to adjust pages while deleting archived pings.
   *
   * @return number[]   The total number of pings beofre and after this one (2-cell array)
   * @throws Exception  If pings could ne counted (SQL error is provided)
   */
   
   public function countPingsBeforeAfter()
   {
      $sql = 'SELECT last_update FROM map_pings WHERE pseudo=? && id_ping!=? ORDER BY last_update DESC';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo'], $this->_data['id_ping']));
      
      if($res != NULL && count($res) > 0 && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Pings could not be listed: '. $res[2]);
      
      // Going through the list to count before/after
      $trueRes = array(0, 0);
      $toCompare = Utils::toTimestamp($this->_data['last_update']);
      for($i = 0; $i < count($res); $i++)
      {
         if(Utils::toTimestamp($res[$i]['last_update']) >= $toCompare)
            $trueRes[0]++;
         else
            $trueRes[1]++;
      }
      
      return $trueRes;
   }
   
   /*
   * Static method to obtain a set of pings to which the user is mapped to. Pings are listed by 
   * last update date. It is assumed the user is logged in when this method is called.
   *
   * @param number $first  The index of the first ping of the set
   * @param number $nb     The maximum amount of pings to list
   * @return mixed[]       The pings that were found
   * @throws Exception     If pings could not be found (SQL error is provided)
   */

   public static function getPings($first, $nb)
   {
      $sql = 'SELECT * 
      FROM pings 
      NATURAL JOIN map_pings 
      WHERE map_pings.pseudo=? 
      ORDER BY map_pings.last_update DESC, map_pings.id_ping DESC LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && count($res) > 0 && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Pings could not be listed: '. $res[2]);
      
      return $res;
   }
}

?>
