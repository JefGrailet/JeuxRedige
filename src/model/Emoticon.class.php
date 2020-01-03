<?php

/**
* Emoticon models a single emoticon, which consists of an uploaded file of limited dimensions with 
* additionnal metadata (uploader, title, date of upload) and which can be mapped to users such 
* that they can create their own shortcuts for each emoticon. The idea is to have an emoticon 
* library accessible to all (with only users with advanced features enabled being able to create 
* new emoticons) where anyone can select their everyday emoticons and create personnalized 
* shortcuts for each in their selection.
*
* General remark: for all member methods, it is assumed they will always be called if and only if 
* the current user is logged in and has the rights (when necessary).
*/

class Emoticon
{
   protected $_data;

   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the emoticon or its ID
   * @throws Exception    If the emoticon cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $sql = "SELECT emoticons.*, map_emoticons.shortcut 
         FROM emoticons 
         LEFT OUTER JOIN map_emoticons 
         ON map_emoticons.id_emoticon=emoticons.id_emoticon && map_emoticons.pseudo=? 
         WHERE emoticons.id_emoticon=?";
      
         $this->_data = Database::secureRead($sql, array(LoggedUser::$data['pseudo'], $arg), true);
         
         if($this->_data == NULL)
            throw new Exception('Emoticon does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('Emoticon could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new emoticon in the database.
   *
   * @param string $file      Name of the associated file (in uploads/smilies/)
   * @param string $name      The name of the emoticon
   * @param string $shortcut  Suggested shortcut (must start with ":")
   * @return Emoticon         The new emoticon as an Emoticon instance
   * @throws Exception        When the insertion in the database fails, with the SQL error inside
   */
   
   public static function insert($file, $name, $shortcut)
   {
      $currentDate = Utils::toDatetime(Utils::SQLServerTime());
      $toInsert = array('file' => $file,
      'name' => $name, 
      'uploader' => LoggedUser::$data['pseudo'], 
      'upload_date' => $currentDate, 
      'suggestion' => $shortcut);
      
      $sql = "INSERT INTO emoticons VALUES('0', :file, :name, :uploader, :upload_date, :suggestion)";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new emoticon: '. $res[2]);
      
      $newEmoticonID = Database::newId();
      
      return new Emoticon($newEmoticonID);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Maps a user which pseudo is $pseudo with this emoticon. A new shortcut can be chosen by the 
   * user if (s)he wishes a unique one, otherwise the shortcut will be the suggested one. If the 
   * mapping already exists, nothing happens.
   *
   * @param string $pseudo    The pseudo of the user to map with this emoticon (assumed to exist)
   * @param string $shortcut  The shortcut the user wishes to use for this emoticon (optional); 
   *                          verifying its format is up to the calling code
   * @return bool             True if the mapping has been created, false in case of duplicata
   * @throws Exception        If the mapping could not be inserted (SQL error is provided)
   */

   public function mapTo($pseudo, $shortcut = "")
   {
      $arg = array('id' => $this->_data['id_emoticon'], 
                   'user' => $pseudo, 
                   'short' => $this->_data['suggested_shortcut']);
      
      if(strlen($shortcut) > 0)
         $arg['short'] = $shortcut;
      
      $res = Database::secureWrite("INSERT INTO map_emoticons VALUES(:id, :user, :short)", $arg);
      
      if($res != NULL && strstr($res[2], 'Duplicate entry') == FALSE)
      {
         $excepMsg = 'Could not map emoticon '.$this->_data['id_emoticon'].' with';
         $excepMsg .= 'user "'.addslashes($pseudo).'": '. $res[2];
         throw new Exception($excepMsg);
      }
      else if($res != NULL)
         return false;
      
      $this->_data['shortcut'] = $arg['short'];
      return true;
   }
   
   /*
   * Unmaps user $pseudo from this emoticon. If the mapping does not exist, nothing happens.
   *
   * @param string $pseudo  The pseudo of the user to unmap from this emoticon (assumed to exist)
   * @return bool           True if the mapping has been removed, false if it did not exist
   * @throws Exception      If the mapping could not be removed (SQL error is provided)
   */
   
   public function unmapTo($pseudo)
   {
      $res = Database::secureWrite("DELETE FROM map_emoticons WHERE id_emoticon=? && pseudo=?",
                         array($this->_data['id_emoticon'], $pseudo), true);
      
      if(is_array($res))
      {
         $excepMsg = 'Could not remove the mapping ('.$this->_data['id_emoticon'].',';
         $excepMsg .= addslashes($pseudo).'): '. $res[2];
         throw new Exception($excepMsg);
      }

      if($res == NULL)
         return false;
         
      $this->_data['shortcut'] = NULL;
      return true;
   }
   
   /*
   * Checks a particular user is mapped to this emoticon.
   *
   * @param string user  The user to check
   * @return boolean     True if this user is mapped to this emoticon
   * @throws Exception   If an error occurred while checking the emoticon (SQL error is provided)
   */
   
   public function isMappedTo($user)
   {
      $sql = "SELECT COUNT(*) AS nb FROM map_emoticons WHERE id_emoticon=? && pseudo=?";
      $res = Database::secureRead($sql, array($this->_data['id_emoticon'], $user), true);
      
      if(count($res) == 3)
         throw new Exception('Could not check mapping of '.$this->_data['id_emoticon'].' with '.$user.': '.$res[2]);
      
      if($res['nb'] == 1)
         return true;
      return false;
   }
   
   /*
   * Updates the meta-data of the emoticon, i.e., the name/title and the shortcut.
   *
   * @param string $newName      The new name
   * @param string $newShortcut  The new shortcut
   * @throws Exception           If the update could not take place (SQL error is provided), also 
   *                             thrown if the shortcut is a duplicate
   */
   
   public function update($newName, $newShortcut)
   {
      $sql = "UPDATE emoticons SET name=?, suggested_shortcut=? WHERE id_emoticon=?";
      $arg = array($newName, $newShortcut, $this->_data['id_emoticon']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Emoticon could not be updated: '. $res[2]);
         
      $this->_data['name'] = $newName;
      $this->_data['suggested_shortcut'] = $newShortcut;
   }
   
   /*
   * Updates the shortcut of a mapping between this emoticon and some user.
   *
   * @param string $user         The mapped user
   * @param string $newShortcut  The new shortcut
   * @throws Exception           If the update could not take place
   */
   
   public function updateMapping($user, $newShortcut)
   {
      $sql = "UPDATE map_emoticons SET shortcut=? WHERE id_emoticon=? && pseudo=?";
      $arg = array($newShortcut, $this->_data['id_emoticon'], $user);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Emoticon mapping could not be updated: '. $res[2]);
      
      if($user === LoggedUser::$data['pseudo'])
         $this->_data['shortcut'] = $newShortcut;
   }
   
   /*
   * Count how many mappings there are with this emoticon.
   *
   * @return number     The number of mappings for this emoticon
   * @throws Exception  If an error occurred while checking the emoticon (SQL error is provided)
   */
   
   public function countMappings()
   {
      $sql = "SELECT COUNT(*) AS nb FROM map_emoticons WHERE id_emoticon=?";
      $res = Database::secureRead($sql, array($this->_data['id_emoticon']), true);
      
      if(count($res) == 3)
         throw new Exception('Could not check mappings of '.$this->_data['id_emoticon'].': '.$res[2]);
      
      return $res['nb'];
   }
   
   /*
    * Deletes the current emoticon along its mappings. The whole is performed in a single SQL 
    * transaction. Normally, only admins should be allowed to delete emoticons, but this feature 
    * could be opened to others in the future.
    *
    * @throws Exception  If the operation could not be properly carried out (SQL error is provided)
    */
   
   public function delete()
   {
      Database::beginTransaction();
      try
      {
         $sqlMappings = "DELETE FROM map_emoticons WHERE id_emoticon=?";
         $resMappings = Database::secureWrite($sqlMappings, array($this->_data['id_emoticon']));
         if($resMappings != NULL)
            throw new Exception('Emoticon mapping(s) could not be deleted: '. $resMappings[2]);
         
         $sqlEmoticon = "DELETE FROM emoticons WHERE id_emoticon=?";
         $resEmoticon = Database::secureWrite($sqlEmoticon, array($this->_data['id_emoticon']));
         if($resEmoticon != NULL)
            throw new Exception('Emoticon could not be deleted: '.$resEmoticon[2]);
         
         // Deletion of the associated file
         $pathToUnlink = PathHandler::WWW_PATH().'upload/emoticons/'.$this->_data['file'];
         $success = unlink($pathToUnlink);
         if(!$success)
            throw new Exception('File associated to the emoticon could not be deleted.');
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw $e;
      }
   }
   
   /*
   * Static method to list all emoticon uploaders from the global library. Uploaders are listed 
   * alphabetically.
   *
   * @return string[]   The uploaders, as an array (alphabetical order)
   * @throws Exception  If the uploaders could not be listed (SQL error is provided)
   */
   
   public static function listUploaders()
   {
      $sql = "SELECT DISTINCT uploader FROM emoticons ORDER BY uploader";
      $res = Database::hardRead($sql);
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Uploaders could not be listed: '. $res[2]);
      
      // Transforms the result into a simple 1D array
      $finalRes = array();
      for($i = 0; $i < count($res); $i++)
         array_push($finalRes, $res[$i]['uploader']);
      
      return $finalRes;
   }
   
   /*
   * Static method to count the amount of emoticons in the global library, with the option of 
   * being able to isolate the emoticons uploaded by a particular user.
   *
   * @param string $uploader  The uploader of the emoticons (optional)
   * @return number           The total amount of emoticons
   * @throws Exception        If emoticons could not be counted (SQL error is provided)
   */
   
   public static function countEmoticons($uploader = '')
   {
      $sql = 'SELECT COUNT(*) AS nb FROM emoticons ';
      if(strlen($uploader) > 0)
         $sql .= 'WHERE uploader=? ';
      
      if(strlen($uploader) > 0)
         $res = Database::secureRead($sql, array($uploader), true);
      else
         $res = Database::hardRead($sql, true);
      
      if(count($res) == 3)
         throw new Exception('Emoticons could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to count the amount of emoticons from the current user's library.
   *
   * @return number           The total amount of emoticons in the user's library
   * @throws Exception        If emoticons could not be counted (SQL error is provided)
   */
   
   public static function countMyEmoticons()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM map_emoticons WHERE pseudo=?';
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']), true);
      
      if(count($res) == 3)
         throw new Exception('Emoticons could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to obtain a set of emoticons from the global library, with the option of being 
   * able to isolate the emoticons uploaded by a particular user. Emoticons are listed by name.
   *
   * @param number $first     The index of the first emoticon of the set
   * @param number $nb        The maximum amount of emoticons to list
   * @param string $uploader  The uploader of the emoticons (optional)
   * @return mixed[]          The emoticons that were found
   * @throws Exception        If emoticons could not be found (SQL error is provided)
   */

   public static function getEmoticons($first, $nb, $uploader = '')
   {
      $sql = 'SELECT emoticons.*, map_emoticons.shortcut FROM emoticons ';
      $sql .= 'LEFT OUTER JOIN map_emoticons ';
      $sql .= 'ON map_emoticons.id_emoticon=emoticons.id_emoticon && map_emoticons.pseudo=? ';
      if(strlen($uploader) > 0)
         $sql .= 'WHERE emoticons.uploader=? ';
      $sql .= 'ORDER BY emoticons.name LIMIT '.$first.','.$nb;
      
      if(strlen($uploader) > 0)
         $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo'], $uploader));
      else
         $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Emoticons could not be listed: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No emoticon has been found.');
      
      return $res;
   }
   
   /*
   * Static method to obtain a set of emoticons to which the user has mappings. Emoticons are 
   * listed by name. It is assumed the user is logged in when this method is called.
   *
   * @param number $first  The index of the first emoticon of the set
   * @param number $nb     The maximum amount of emoticons to list
   * @return mixed[]       The emoticons that were found
   * @throws Exception     If emoticons could not be found (SQL error is provided)
   */

   public static function getMyEmoticons($first, $nb)
   {
      $sql = 'SELECT emoticons.*, map_emoticons.shortcut 
      FROM emoticons 
      NATURAL JOIN map_emoticons 
      WHERE map_emoticons.pseudo=? 
      ORDER BY emoticons.name LIMIT '.$first.','.$nb;
   
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Emoticons could not be listed: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No emoticon has been found.');
      
      return $res;
   }
   
   /*
   * Static method to get the user's shortcuts along the file and name associated to the 
   * corresponding emoticon. It is quite similar to getMyEmoticons(), but it is not designed for 
   * pagination and returns much less information.
   *
   * @return mixed[]       The user's emoticons (file and personal shortcut)
   * @throws Exception     If emoticons could not be found (SQL error is provided)
   */
   
   public static function getMyShortcuts()
   {
      $sql = 'SELECT emoticons.name, emoticons.file, map_emoticons.shortcut 
      FROM emoticons 
      NATURAL JOIN map_emoticons 
      WHERE map_emoticons.pseudo=? 
      ORDER BY emoticons.name';
   
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Emoticons could not be listed: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No emoticon has been found.');
      
      return $res;
   }
   
   /*
   * Static method to test if a shortcut is properly formatted.
   *
   * @param string $shortcut  The shortcut to test
   * @return bool             True if it is properly formatted
   */
   
   public static function hasGoodFormat($shortcut)
   {
      if(strlen($shortcut) < 2 || strlen($shortcut) > 30)
         return false;
      
      $firstChar = substr($shortcut, 0, 1);
      if($firstChar !== ':' && $firstChar !== ';')
         return false;
      
      $lastChar = substr($shortcut, -1);
      $content = '';
      if($lastChar === ':')
         $content = substr($shortcut, 1, -1);
      else
         $content = substr($shortcut, 1);
      
      if(!preg_match('!^[a-zA-Z0-9\(\)\|\[\]\\\^_-]{1,29}$!', $content))
         return false;
      return true;
   }
   
   /*
   * Static method to parse a text sent by the current user and replace its shortcuts with the 
   * format code for smilies.
   *
   * @param string  $text  The text to parse
   * @return string        The same text, after parsing the user's emoticon shortcuts
   * @throws Exception     If the user's shortcuts could not be loaded or if there is no shortcut
   */
   
   public static function parseEmoticonsShortcuts($text)
   {
      $parsedText = $text;
      
      $sql = 'SELECT emoticons.file AS file, map_emoticons.shortcut AS shortcut 
      FROM emoticons 
      NATURAL JOIN map_emoticons 
      WHERE map_emoticons.pseudo=?';
      
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Emoticons could not be listed: '. $res[2]);
      else if($res == NULL)
      {
         // throw new Exception('No emoticon has been found.');
         return $text;
      }
      
      for($i = 0; $i < count($res); $i++)
      {
         $curSmilie = $res[$i];
         $parsedText = str_replace($curSmilie['shortcut'], '!emoticon['.$curSmilie['file'].']', $parsedText);
      }
      
      return $parsedText;
   }
   
   /*
   * Static method to unparse a text taken from the DB and replace the emoticon tags with the 
   * shortcuts used by the current user.
   *
   * @param string  $text  The text to parse
   * @return string        The same text, after unparsing emoticon tags into the user's shortcuts
   * @throws Exception     If the user's shortcuts could not be loaded or if there is no shortcut
   */
   
   public static function unparseEmoticonsShortcuts($text)
   {
      $parsedText = $text;
      
      $sql = 'SELECT emoticons.file AS file, map_emoticons.shortcut AS shortcut 
      FROM emoticons 
      NATURAL JOIN map_emoticons 
      WHERE map_emoticons.pseudo=?';
      
      $res = Database::secureRead($sql, array(LoggedUser::$data['pseudo']));
      
      if($res != NULL && !is_array($res[0]) && count($res) == 3)
         throw new Exception('Emoticons could not be listed: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No emoticon has been found.');
      
      for($i = 0; $i < count($res); $i++)
      {
         $curSmilie = $res[$i];
         $parsedText = str_replace('!emoticon['.$curSmilie['file'].']', $curSmilie['shortcut'], $parsedText);
      }
      
      return $parsedText;
   }
}

?>
