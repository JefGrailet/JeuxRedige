<?php

/**
* Tag models a single keyword (or tag) which has been inserted in the DB. Unlike other classes of
* the model, the single field is a simple string and not an array (since only the keyword itself
* is stored). The class is used to map topics to that tag, or unmap them, as well as creating 
* aliases (e.g. if a tag "MGR" exists, it can be mapped to "Metal Gear Rising: Revengeance" as
* an alias and the latter will be displayed instead when using autocomplete).
*/

class Tag
{
   private $_tag;
   
   /*
   * Constructor.
   *
   * @param string $arg  The tag itself
   * @throws Exception   If the tag does not exist (SQL error is provided)
   */
   
   public function __construct($arg)
   {
      $arg = ucfirst($arg); // Enforces first char to be in uppercase
      
      $res = Database::secureWrite("INSERT INTO tags VALUES(:tag)", array('tag' => $arg));
      
      if($res != NULL && strstr($res[2], '\'PRIMARY\'') == FALSE)
         throw new Exception('Could not verify the existence of the tag "'.$arg.'": '. $res[2]);
      
      $this->_tag = $arg;
   }
   
   /*
   * Maps a topic of ID $id to this tag. If the mapping already exists, nothing happens.
   *
   * @param integer $id  The ID of the topic to map to this tag (assumed to exist)
   * @return bool        True if the mapping has been created, false in case of duplicata
   * @throws Exception   If the mapping could not be inserted (SQL error is provided)
   */

   public function mapToTopic($id)
   {
      $res = Database::secureWrite("INSERT INTO map_tags VALUES(:tag, :id_topic)",
                         array('tag' => $this->_tag, 'id_topic' => $id));
      
      if($res != NULL && strstr($res[2], 'Duplicate entry') == FALSE)
         throw new Exception('Could not map topic '.$id.' with tag "'.addslashes($this->_tag).'": '. $res[2]);
      else if($res != NULL)
         return false;
      
      return true;
   }
   
   /*
   * Maps an article of ID $id to this tag. If the mapping already exists, nothing happens.
   *
   * @param integer $id  The ID of the article to map to this tag (assumed to exist)
   * @return bool        True if the mapping has been created, false in case of duplicata
   * @throws Exception   If the mapping could not be inserted (SQL error is provided)
   */

   public function mapToArticle($id)
   {
      $res = Database::secureWrite("INSERT INTO map_tags_articles VALUES(:tag, :id_article)",
                         array('tag' => $this->_tag, 'id_article' => $id));
      
      if($res != NULL && strstr($res[2], 'Duplicate entry') == FALSE)
         throw new Exception('Could not map article '.$id.' with tag "'.addslashes($this->_tag).'": '. $res[2]);
      else if($res != NULL)
         return false;
      
      return true;
   }
   
   /*
   * Checks if this tag can be used for aliases. For this to be possible, the tag should not be 
   * already used for a game. Alternatively, this method can be used to check a tag submitted for 
   * a game is not already in use (provided the calling code also checks, via countAliases(), that 
   * the tag is not used for aliases).
   *
   * @return bool       True if the tag can be used for aliases, false otherwise
   * @throws Exception  If an error occurred while checking the tag (SQL error is provided)
   */
   
   public function canBeAnAlias()
   {
      $sql1 = "SELECT COUNT(*) AS nb FROM games WHERE tag=?";
      $res1 = Database::secureRead($sql1, array($this->_tag), true);
      
      if(sizeof($res1) == 3)
         throw new Exception('Could not verify that '.addslashes($this->_tag).' can be used for aliases: '. $res1[2]);
      
      if($res1['nb'] == 1)
         return false;
      
      return true;
   }
   
   /*
   * Count how many aliases are attached to this tag (reminder: a tag cannot be attached to a 
   * media and be an alias at the same time).
   *
   * @return integer    The number of aliases attached to this tag
   * @throws Exception  If an error occurred while checking the tag (SQL error is provided)
   */
   
   public function countAliases()
   {
      $sql = "SELECT COUNT(*) AS nb FROM map_aliases WHERE tag=?";
      $res = Database::secureRead($sql, array($this->_tag), true);
      
      if(sizeof($res) == 3)
         throw new Exception('Could not verify that '.addslashes($this->_tag).' is an alias: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Creates an alias for a given tag with this one. The alias is assumed to exist due to the 
   * alias procedure which always first verifies a tag can be an alias (tag is created in the 
   * process). Also, the method has a retroactive effect: if the new alias was already mapped 
   * with some topics or articles, we have to update the mapping so that it maps the aliased tag 
   * instead of the alias to the already mapped topics (without creating duplicata).
   *
   * @param string $aliasedTag  The tag for which this tag will become an alias
   * @return bool               True if the alias was successfully created (false otherwise, i.e.
   *                            when the alias already exists or is non-sensical, like having
   *                            a mapping 'MGR','MGR')
   * @throws Exception          If some SQL error occurs while creating the alias or updating the
   *                            mappings with topics
   */
   
   public function createAlias($aliasedTag)
   {
      if($aliasedTag === $this->_tag)
         return false;
      
      $sql1 = "INSERT INTO map_aliases VALUES(:thisTag, :replacedTag)";
      $res1 = Database::secureWrite($sql1, array('thisTag' => $this->_tag, 'replacedTag' => $aliasedTag));
      
      if($res1 != NULL)
      {
         if(strstr($res1[2], 'Duplicate entry') != FALSE)
            return false;
         else
            throw new Exception('Could not create alias '.addslashes($this->_tag).','.addslashes($aliasedTag).': '. $res1[2]);
      }
      
      // Retroactive application of the alias
      $topicsIDs = Database::secureRead("SELECT id_topic FROM map_tags WHERE tag=?", array($this->_tag));
      if($topicsIDs != NULL)
      {
         if(!is_array($topicsIDs[0]) && sizeof($topicsIDs) == 3)
            throw new Exception('Could not get mappings for alias '.addslashes($this->_tag).','.addslashes($aliasedTag).': '. $topicIDs[2]);
      
         $res2 = Database::secureWrite("DELETE FROM map_tags WHERE tag=?", array($this->_tag));
         if($res2 != NULL && is_array($res2))
            throw new Exception('Could not delete old mappings for alias '.addslashes($this->_tag).','.addslashes($aliasedTag).': '. $res2[2]);
         
         $sql3 = Database::prepare("INSERT INTO map_tags VALUES(:alias, :idTopic)");
         for($i = 0; $i < count($topicsIDs); $i++)
            $sql3->execute(array('alias' => $aliasedTag, 'idTopic' => $topicsIDs[$i]['id_topic']));
         $sql3->closeCursor();
      }
      
      // Same for map_tags_articles
      $articlesIDs = Database::secureRead("SELECT id_article FROM map_tags_articles WHERE tag=?", array($this->_tag));
      if($articlesIDs != NULL)
      {
         if(!is_array($articlesIDs[0]) && sizeof($articlesIDs) == 3)
            throw new Exception('Could not get article mappings for alias '.addslashes($this->_tag).','.addslashes($aliasedTag).': '. $articlesIDs[2]);
      
         $res2 = Database::secureWrite("DELETE FROM map_tags_articles WHERE tag=?", array($this->_tag));
         if($res2 != NULL && is_array($res2))
            throw new Exception('Could not delete old mappings for alias '.addslashes($this->_tag).','.addslashes($aliasedTag).': '. $res2[2]);
         
         $sql3 = Database::prepare("INSERT INTO map_tags_articles VALUES(:alias, :idArticle)");
         for($i = 0; $i < count($articlesIDs); $i++)
            $sql3->execute(array('alias' => $aliasedTag, 'idArticle' => $articlesIDs[$i]['id_article']));
         $sql3->closeCursor();
      }
      
      return true;
   }
   
   
   /*
    * Unmaps several tags to a topic of ID $id. This method is static rather than being a member 
    * method "unmapTo()" because deletion does not need any prior verification; i.e., 
    * new Tag($tag) followed by mapToTopic() both ensures the tag is inserted in the DB and 
    * mapped, but new Tag($tag) prior to a deletion with an hypothetical unmapTo() is unnecessary. 
    * Moreover, several tags can be deleted in a single SQL request, rather than making a request 
    * for each tag.
    *
    * @param integer $id     The ID of the topic for which tags must be unmapped (assumed to exist)
    * @param string[] $tags  The tags to unmap
    * @return bool           True if the mappings have been removed, false if they did not exist
    * @throws Exception      If the mappings could not be removed (SQL error is provided)
    */
   
   public static function unmapTopic($id, $tags)
   {
      $sql = 'DELETE FROM map_tags WHERE tag IN (';
      $arg = array();
      $mappings = '';
      for($i = 0; $i < count($tags); $i++)
      {
         if($i > 0)
         {
            $sql .= ', ';
            $mappings .= ', ';
         }
         $sql .= '?';
         array_push($arg, $tags[$i]);
         $mappings .= '('.addslashes($tags[$i]).','.strval($id).')';
      }
      $sql .= ') && id_topic =?';
      array_push($arg, $id);
      
      $res = Database::secureWrite($sql, $arg, true);
      
      if(is_array($res))
         throw new Exception('Could not remove the mappings ('.$mappings.'): '. $res[2]);
         
      if($res == 0)
         return false;
      return true;
   }
   
   /*
    * Does the same operation as unmapTopic() but for articles.
    *
    * @param integer $id     The ID of the article for which tags must be unmapped (assumed to exist)
    * @param string[] $tags  The tags to unmap
    * @return bool           True if the mappings have been removed, false if they did not exist
    * @throws Exception      If the mappings could not be removed (SQL error is provided)
    */
   
   public static function unmapArticle($id, $tags)
   {
      $sql = 'DELETE FROM map_tags_articles WHERE tag IN (';
      $arg = array();
      $mappings = '';
      for($i = 0; $i < count($tags); $i++)
      {
         if($i > 0)
         {
            $sql .= ', ';
            $mappings .= ', ';
         }
         $sql .= '?';
         array_push($arg, $tags[$i]);
         $mappings .= '('.addslashes($tags[$i]).','.strval($id).')';
      }
      $sql .= ') && id_article=?';
      array_push($arg, $id);
      
      $res = Database::secureWrite($sql, $arg, true);
      
      if(is_array($res))
         throw new Exception('Could not remove the mappings ('.$mappings.'): '. $res[2]);
         
      if($res == 0)
         return false;
      return true;
   }
   
   /*
    * Does the same operation as unmapTopic() but for aliases.
    *
    * @param string $aliasee    The tag which is being aliased (e.g., full title of a game)
    * @param string[] $aliases  The aliases to unmap
    * @return bool              True if the mappings have been removed, false if they did not exist
    * @throws Exception         If the mappings could not be removed (SQL error is provided)
    */
   
   public static function unmapAliases($aliasee, $aliases)
   {
      $sql = 'DELETE FROM map_aliases WHERE tag IN (';
      $arg = array();
      $mappings = '';
      for($i = 0; $i < count($aliases); $i++)
      {
         if($i > 0)
         {
            $sql .= ', ';
            $mappings .= ', ';
         }
         $sql .= '?';
         array_push($arg, $aliases[$i]);
         $mappings .= '('.addslashes($aliases[$i]).','.$aliasee.')';
      }
      $sql .= ') && alias=?';
      array_push($arg, $aliasee);
      
      $res = Database::secureWrite($sql, $arg, true);
      
      if(is_array($res))
         throw new Exception('Could not remove the mappings ('.$mappings.'): '. $res[2]);
         
      if($res == 0)
         return false;
      return true;
   }
   
   
   /*
   * Static method to clean the database from "orphan" tags. An orphan tag is a tag that has been
   * used in the past, but for which there is no mapping (of any kind; i.e. aliases and games are
   * never orphan tags) any longer. Therefore, this tag is now useless and should be removed. 
   * This method performs this operation.
   *
   * @throws Exception  If an error occurred while deleting orphan tags (SQL error is provided)
   */
   
   public static function cleanOrphanTags()
   {
      $sql = "DELETE FROM tags WHERE tag NOT IN (SELECT tag FROM map_tags GROUP BY tag) 
      && tag NOT IN (SELECT tag FROM map_tags_articles GROUP BY tag) 
      && tag NOT IN (SELECT tag FROM map_aliases GROUP BY tag) 
      && tag NOT IN (SELECT alias FROM map_aliases GROUP BY alias) 
      && tag NOT IN (SELECT tag FROM games)";
      
      $res = Database::hardWrite($sql);
      
      if($res != NULL)
         throw new Exception('Could not remove orphan tags: '. $res[2]);
   }
   
   /*
   * Static method to look for up to 5 tags relevant to a given string labelled as $needle (just 
   * like in string functions from the PHP library). The method first seeks in the aliases table 
   * to get the most particular tags (most of the time, titles of games), picking aliases that
   * contain $needle (either the alias, either the tag which this alias refers to). If this does 
   * not provide 5 tags, we seek further in tags map, ignoring the aliases and titles of games.
   *
   * @param string $needle  A string (without | or ")
   * @return string[]       An array of tags containing $needle, in lexicographical order
   * @throws Exception      If some error occurs with SQL server (SQL error is provided)
   */
   
   public static function findTags($needle)
   {
      $searchInput = array('needle' => '%'.strtolower($needle).'%');
      $sql1 = "SELECT alias FROM map_aliases WHERE LOWER(tag) LIKE :needle 
      OR LOWER(alias) LIKE :needle GROUP BY alias ORDER BY alias LIMIT 5";
      $res1 = Database::secureRead($sql1, $searchInput);
      
      if($res1 != NULL && !is_array($res1[0]))
      {
         throw new Exception('Could not find tags: '. $res1[2]);
      }
      
      // Converts results into a linear array (results are given as a 2D array)
      $output = array();
      $nbResults = count($res1);
      for($i = 0; $i < $nbResults; $i++)
         array_push($output, $res1[$i]['alias']);
      
      if($nbResults < 5)
      {
         $sql2 = 'SELECT tag FROM tags WHERE LOWER(tag) LIKE :needle 
         && tag NOT IN (SELECT tag FROM map_aliases)
         && tag NOT IN (SELECT DISTINCT alias FROM map_aliases) 
         ORDER BY tag LIMIT '.(5 - $nbResults);
         $res2 = Database::secureRead($sql2, $searchInput);
         
         if($res2 != NULL && !is_array($res2[0]))
            return $output;
         
         for($i = 0; $i < count($res2); $i++)
            array_push($output, $res2[$i]['tag']);
      }
      
      return $output;
   }
}

?>
