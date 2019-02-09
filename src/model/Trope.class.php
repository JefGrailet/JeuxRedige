<?php

/**
* Trope class models a game trope registered in the database, which consists of an extension of 
* an existing keyword/tag (DB-wise). The extension provides additional fields to register a color 
* and a short description of the trope. Such information are displayed in the topics which tags 
* contain tropes, with mouse hover.
*
* The class is designed in the same fashion as Game.
*/

class Trope
{
   private $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the trope or its title
   * @throws Exception    If the trope cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $res = Database::secureRead("SELECT * FROM tropes WHERE tag=?", array($arg));
      
         if(!is_array($res[0]) && count($res) == 3)
            throw new Exception('The trope could not be found: '. $res[2]);
         else if($res == NULL)
            throw new Exception('This trope does not exist.');
         
         $this->_data = $res[0];
      }
   }
   
   /*
   * Static method to insert a new trope in the database.
   *
   * N.B.: if the name of the trope did not exist as a tag yet, this method will not create it. 
   * It is up to the calling code to make sure the tag exists and can be used for a new game.
   *
   * @param string $tag    The tag associated with the trope
   * @param string $color  The color associated to the trope
   * @param string $desc   The description of the trope (max. 500 characters)
   * @return Trope         The new trope as a Trope instance
   * @throws Exception     When the insertion of the game in the database fails, with the actual 
   *                       SQL error inside
   */
   
   public static function insert($tag, $color, $desc)
   {
      $toInsert = array('title' => $tag, 'color' => $color, 'description' => $desc);
      $sql = "INSERT INTO tropes VALUES(:title, :color, :description)";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new trope: '. $res[2]);
      
      return new Trope($tag);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Updates the trope details (color and description). In order for the buffered tropes which are 
   * mapped to reviews to stay up to date, a second SQL request is executed to update the 
   * "associated_tropes" field in each review (this field stores name and color of the mapped 
   * tropes in order to avoid additional SQL requests at display). As a consequence, a transaction 
   * is needed.
   *
   * @param string $color  The updated color
   * @param string $desc   The updated description
   * @throws Exception     If a problem occurs while updating (SQL error is provided)
   */
   
   public function update($color, $desc)
   {
      $oldCode = $this->_data['tag'].','.$this->_data['color'];
      $newCode = $this->_data['tag'].','.$color;
      
      $updateInput = array('color' => $color, 
      'description' => $desc,
      'title' => $this->_data['tag']);
      
      Database::beginTransaction();
      
      $sql1 = "UPDATE tropes SET color=:color, description=:description WHERE tag=:title";
      $res1 = Database::secureWrite($sql1, $updateInput);
      if($res1 != NULL)
      {
         Database::rollback();
         throw new Exception('Could not update trope: '. $res1[2]);
      }
      
      $sql2 = "UPDATE reviews 
      SET associated_tropes=REPLACE(associated_tropes, ?, ?) 
      WHERE associated_tropes LIKE ?";
      $res2 = Database::secureWrite($sql2, array($oldCode, $newCode, '%'.$oldCode.'%'));
      if($res2 != NULL)
      {
         Database::rollback();
         throw new Exception('Could not update trope: '. $res2[2]);
      }
      
      Database::commit();
      
      $this->_data['color'] = $color;
      $this->_data['description'] = $desc;
   }
   
   /*
   * Maps a review of ID $id to this tag. If the mapping already exists, nothing happens.
   *
   * @param number $id  The ID of the review to map to this trope (assumed to exist)
   * @return bool       True if the mapping has been created, false in case of duplicata
   * @throws Exception  If the mapping could not be inserted (SQL error is provided)
   */

   public function mapTo($id)
   {
      $res = Database::secureWrite("INSERT INTO map_tropes_reviews VALUES(:tag, :id_commentable)",
                         array('tag' => $this->_data['tag'], 'id_commentable' => $id));
      
      if($res != NULL && strstr($res[2], 'Duplicate entry') == FALSE)
         throw new Exception('Could not map review '.$id.' with tag "'.addslashes($this->_data['tag']).'": '. $res[2]);
      else if($res != NULL)
         return false;
      
      return true;
   }
   
   /*
   * Deletes the trope. Thanks to cascade deletion, mappings related to this trope are deleted as 
   * well.
   *
   * @throws Exception  If the trope could not be deleted (SQL error is provided)
   */
   
   public function delete()
   {
      $res = Database::secureWrite("DELETE FROM tropes WHERE tag=?",
                         array($this->_data['tag']), true); // CASCADE from InnoDB will do the rest
      
      if(is_array($res))
      {
         throw new Exception('Unable to delete trope '.$this->_data['tag'].' : '. $res[2]);
      }
      
      // Deletion of the icon
      $tropeIconPath = PathHandler::WWW_PATH.'upload/tropes/'.PathHandler::formatForURL($this->_data['tag']).'.png';
      if(file_exists($topicDirPath))
         unlink($tropeIconPath);
   }
   
   /*
   * Static method to unmap several tropes from a review, just like the unmapTopic() and 
   * unmapArticle() methods from the Tag class.
   * 
   * @param integer $id     The ID of the review for which tropes must be unmapped
   * @param string[] $tags  The tropes to unmap
   * @return bool           True if the mappings have been successfully removed
   * @throws Exception      If the mappings could not be removed (SQL error is provided)
   */
   
   public static function unmap($id, $tropes)
   {
      $sql = 'DELETE FROM map_tropes_reviews WHERE tag IN (';
      $arg = array();
      $mappings = '';
      for($i = 0; $i < count($tropes); $i++)
      {
         if($i > 0)
         {
            $sql .= ', ';
            $mappings .= ', ';
         }
         $sql .= '?';
         array_push($arg, $tropes[$i]);
         $mappings .= '(' + addslashes($tropes[$i]) + ',' + $id + ')';
      }
      $sql .= ') && id_commentable=?';
      array_push($arg, $id);
      
      $res = Database::secureWrite($sql, $arg, true);
      
      if(is_array($res))
         throw new Exception('Could not remove the mappings ('.$mappings.'): '. $res[2]);
         
      if($res == 0)
         return false;
      return true;
   }
   
   /*
   * Static method to get the total number of tropes stored in the DB.
   *
   * @return number     The total number of tropes
   * @throws Exception  If tropes could not be counted (SQL error is provided)
   */
   
   public static function countTropes()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM tropes';
      
      $res = Database::hardRead($sql, true);
      
      if(sizeof($res) == 3)
         throw new Exception('Tropes could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to obtain a set of tropes in a similar fashion to that of the static method
   * getTopics() in Topic class. This method is useful to display a list of tropes. The tropes are 
   * sorted by lexicographical order.
   *
   * @param number $first  The index of the first trope of the set
   * @param number $nb     The maximum amount of tropes to list
   * @return mixed[]       The tropes that were found
   * @throws Exception     If tropes could not be found (SQL error is provided)
   */

   public static function getTropes($first, $nb)
   {
      $sql = 'SELECT * FROM tropes ORDER BY tag LIMIT '.$first.','.$nb;
   
      $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Tropes could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to look up for a set of tropes (with description) based on their names. It is 
   * notably useful to load tropes when looking at evaluations.
   *
   * @param string[] $needles  A set of trope names
   * @return mixed[]           Tropes that were found in the DB based on the names
   * @throws Exception         If some error occurs with SQL server (SQL error is provided)
   */
   
   public static function getTropesByName($needles)
   {
      $sql = "SELECT * FROM tropes WHERE tag IN (";
      for($i = 0; $i < count($needles); $i++)
      {
         if($i > 0)
            $sql .= ", ";
         $sql .= "'".$needles[$i]."'";
      }
      $sql .= ") ORDER BY tag";
      
      $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Tropes could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to look for up to 5 tropes relevant to a given string labelled as $needle (just 
   * like in string functions from the PHP library). The method only looks in the tropes table (no 
   * aliasing with tropes, yet).
   *
   * @param string $needle  A string (without | or ")
   * @return string[]       Tropes which name contains $needle, in lexicographical order
   * @throws Exception      If some error occurs with SQL server (SQL error is provided)
   */
   
   public static function findTropes($needle)
   {
      $searchInput = array('needle' => '%'.strtolower($needle).'%');
      $sql1 = "SELECT tag FROM tropes WHERE LOWER(tag) LIKE :needle ORDER BY tag LIMIT 5";
      $res1 = Database::secureRead($sql1, $searchInput);
      
      if($res1 != NULL && !is_array($res1[0]))
      {
         throw new Exception('Could not find tropes: '. $res1[2]);
      }
      
      // Converts results into a linear array (results are given as a 2D array)
      $output = array();
      $nbResults = count($res1);
      for($i = 0; $i < $nbResults; $i++)
         array_push($output, $res1[$i]['tag']);
      
      return $output;
   }
}
