<?php

/**
* Simple class to model an invitation, i.e. a form a sponsorship where an already subscribed user 
* invites (by e-mail) a non-registered third party to join the community. This form of sponsor has 
* also the property of granting the new user his/her advanced rights from the start, as this 
* function is only opened to advanced features-enabled users, who are supposed to be trustworthy. 
* The class itself is pretty straightforward.
*/

class Invitation
{
   private $_data;

   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to that invitation or the invitation key
   * @throws Exception    If the invitation cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $this->_data = Database::secureRead("SELECT * FROM invitations WHERE invitation_key=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Invitation does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Invitation could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Alternate constructor (building up on the previous) to get the invitation by the e-mail 
   * address.
   *
   * @param string $email  The associated e-mail address
   * @throws Exception     If the invitation cannot be found or does not exist
   * @return Invitation    The invitation
   */
   
   public static function getByEmail($email)
   {
      $res = Database::secureRead("SELECT * FROM invitations WHERE guest_email=?", array($email), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Invitation could not be found: '. $this->_data[2]);
      else if($res == NULL)
         throw new Exception('Invitation does not exist.');
      
      return new Invitation($res);
   }
   
   /*
   * Static method to record a new invitation.
   *
   * @param string $email     The e-mail address of the guest
   * @return mixed[]          A new Invitation instance corresponding to the new entry
   * @throws Exception        If the insertion in the DB fails (SQL error is provided)
   */
   
   public static function insert($email)
   {
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $invitationKey = substr(md5(uniqid(rand(), true)), 15, 15);
      $newLine = array('invitation_key' => $invitationKey, 
      'sponsor' => LoggedUser::$data['pseudo'], 
      'guest_email' => $email, 
      'emission_date' => $currentDate, 
      'last_email' => '1970-01-01 00:00:00');
      
      $sql = "INSERT INTO invitations VALUES(:invitation_key, 
                                             :sponsor, 
                                             :guest_email, 
                                             :emission_date,
                                             :last_email)";
      
      $res = Database::secureWrite($sql, $newLine);
      if($res != NULL)
         throw new Exception('New invitation could not be recorded: '. $res[2]);
      
      return new Invitation($newLine);
   }
   
   /*
   * Static method to now if a given e-mail address has already been invited or not. The method 
   * returns the pseudonym of the user who invited this address.
   *
   * @param string $email  The e-mail address that needs to be tested
   * @return string        The pseudonym of the sponsor, or an empty string if not sponsored yet
   * @throws Exception     If an error occurs while checking availability (SQL error is provided)
   */
   
   public static function hasBeenInvited($email)
   {
      $res = Database::secureRead("SELECT sponsor FROM invitations WHERE guest_email=?", array($email), true);
      
      if($res != NULL && sizeof($res) == 3)
         throw new Exception('Could not check the availability of the e-mail address: '. $res[2]);
      
      if($res != NULL)
         return $res['sponsor'];
      return '';
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Method to update the "last_email" field (maximum one e-mail per hour, to avoid people using 
   * sponsorship procedure to spam others).
   *
   * @throws Exception  If an error occurs while updating (SQL error is provided)
   */
   
   public function updateLastEmailDate()
   {
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $res = Database::secureWrite("UPDATE invitations SET last_email=? WHERE invitation_key=?",
                         array($currentDate, $this->_data['invitation_key']));
      
      if($res != NULL)
         throw new Exception('Invitation could not be updated: '. $res[2]);
      
      $this->_data['last_email'] = $currentDate;
   }
}

?>
