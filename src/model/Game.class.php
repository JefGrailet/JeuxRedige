<?php

/**
* Game class models a game registered in the database, which consists of an extension of an
* existing keyword/tag (DB-wise). The extension provides additional fields to register the 
* genre, publisher, developer, release date and hardware of the game. Such information are 
* displayed in the topics which tags contain the title of the game. Having games as extensions 
* of tags also allows to have special home pages dedicated to one game, displaying its details 
* and listing all topics related to it.
*
* However, despite the 'games' table being an extension of 'tags', this class does not feature
* inheritance of "Tag" class as the scripts handling "Game" objects will not use methods of "Tag" 
* on them. The sole purpose of Game class is to create scripts to create and handle games; 
* searching/mapping topics with the game name is already handled by Tag class.
*/

class Game
{
   private $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to the game or its title
   * @throws Exception    If the game cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $arg = ucfirst($arg); // Enforces first char to be in uppercase
         
         $this->_data = Database::secureRead("SELECT * FROM games WHERE tag=?", array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('This game does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('The game could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new game in the database.
   *
   * N.B.: if the title of the game did not exist as a tag yet, this method will not create it.
   * It is up to the calling code to make sure the tag exists and can be used for a new game.
   *
   * @param mixed $arr[]   Array with all the details about the game (including title), with 
   *                       appropriate labels (in order: tag, genre, publisher, developer, 
   *                       publicationDate, hardware; genre must be in "genres" table and hardware
   *                       must be a concatenation of hardware codes such as: X360|PS3|PC)
   * @return Game          The new game as a Game instance
   * @throws Exception     When the insertion of the game in the database fails, with the actual 
   *                       SQL error inside
   */
   
   public static function insert($arr)
   {
      $toInsert = array('title' => $arr['tag'],
      'genre' => $arr['genre'],
      'publisher' => $arr['publisher'],
      'developer' => $arr['developer'],
      'date' => $arr['publicationDate'],
      'hardware' => $arr['hardware']);
      
      $sql = "INSERT INTO games VALUES(:title, :genre, :publisher, :developer, :date, :hardware)";
      
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new game: '. $res[2]);
      
      $gameDir = PathHandler::WWW_PATH().'upload/games/'.PathHandler::formatForURL($arr['tag']);
      mkdir($gameDir, 0711);
      
      return new Game($arr['tag']);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Updates the game details. A single array is required, but it must be formatted in the same
   * way as for the insert() static method.
   *
   * @param mixed $arr[]  The updated details (format: see insert() specification)
   * @throws Exception    If a problem occurs while updating (SQL error is provided)
   */
   
   public function update($arr)
   {
      $updateInput = array('genre' => $arr['genre'],
      'publisher' => $arr['publisher'],
      'developer' => $arr['developer'],
      'date' => $arr['publicationDate'],
      'hardware' => $arr['hardware'],
      'title' => $this->_data['tag']);
      
      $sql = "UPDATE games SET genre=:genre, publisher=:publisher, developer=:developer, 
      publication_date=:date, hardware=:hardware WHERE tag=:title";
      
      $res = Database::secureWrite($sql, $updateInput);
      if($res != NULL)
         throw new Exception('Could not update game: '. $res[2]);
   }
   
   /*
   * Gets a list of aliases mapped to the game. Actually, this operation does not affect the
   * table "games" but rather "map_aliases", since the name of the game is also a tag.
   *
   * @return string[]   A linear array with all aliases, NULL if there is no alias
   * @throws Exception  If a problem occurs while reading the DB (SQL error is provided)
   */
   
   public function getAliases()
   {
      $sql = "SELECT tag FROM map_aliases WHERE alias=? ORDER BY tag";
      $res = Database::secureRead($sql, array($this->_data['tag']));
      
      if($res != NULL && !is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Cannot retrieve aliases: '. $res[2]);
      else if($res == NULL)
         return NULL;
      
      $result = array();
      if(count($res) == 1 && strlen($res[0]['tag']) == 0)
         return $result; 
      
      // Database::secureRead() returns a 2D array, converted into a linear one
      for($i = 0; $i < count($res); $i++)
         array_push($result, $res[$i]['tag']);
      
      return $result;
   }
   
   /*
   * Static method to get the total number of games stored in the DB.
   *
   * @return number     The total number of games
   * @throws Exception  If games could not be counted (SQL error is provided)
   */
   
   public static function countGames()
   {
      $sql = 'SELECT COUNT(*) AS nb FROM games';
      
      $res = Database::hardRead($sql, true);
      
      if(sizeof($res) == 3)
         throw new Exception('Games could not be counted: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to obtain a set of games in a similar fashion to that of the staitc method
   * getTopics() in Topic class. This method is useful to display a list of games. Such games are
   * sorted by lexicographical order.
   *
   * @param number $first  The index of the first game of the set
   * @param number $nb     The maximum amount of games to list
   * @return mixed[]       The games that were found
   * @throws Exception     If games could not be found (SQL error is provided)
   */

   public static function getGames($first, $nb)
   {
      $sql = 'SELECT * FROM games ORDER BY tag LIMIT '.$first.','.$nb;
   
      $res = Database::hardRead($sql);
      
      if(!is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Games could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Static method to obtain the set of hardware categories registered in the DB.
   *
   * @return string[][]  A 2D array corresponding to the content of "hardware" table
   * @throws Exception   If a SQL problem occurs while retrieving the set (SQL error is provided)
   */
   
   public static function getHardware()
   {
      $res = Database::hardRead("SELECT * FROM hardware ORDER BY code");
      
      if(!is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Hardware categories could not be listed: '. $res[2]);
      
      return $res;
   }
   
   /*
   * Similarly to getHardware(), this function obtains the set of genres of games registered in
   * the DB. However, since "genres" table consists in a single field, the output of the database
   * (a 2D array) is converted into a linear array.
   *
   * @return string[]   A linear array with all the genres registered in the DB
   * @throws Exception  If a SQL problem occurs while retrieving the set (SQL error is provided)
   */
   
   public static function getGenres()
   {
      $res = Database::hardRead("SELECT * FROM genres ORDER BY genre");
      
      if(!is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Genres could not be listed: '. $res[2]);
      
      $output = array();
      for($i = 0; $i < count($res); $i++)
         array_push($output, $res[$i]['genre']);
      
      return $output;
   }
   
   /*
   * Static method to look for up to 5 games relevant to a given string labelled as $needle (just 
   * like in string functions from the PHP library). The method only looks for games in the 
   * map_aliases table.
   *
   * @param string $needle  A string (without | or ")
   * @return string[]       Tags (associated to games) containing $needle, in lexicographical order
   * @throws Exception      If some error occurs with SQL server (SQL error is provided)
   */
   
   public static function findGames($needle)
   {
      $searchInput = array('needle' => '%'.strtolower($needle).'%');
      $sql1 = "SELECT map_aliases.alias 
      FROM map_aliases 
      JOIN games ON games.tag = map_aliases.alias
      WHERE LOWER(games.tag) LIKE :needle 
      OR LOWER(map_aliases.tag) LIKE :needle 
      GROUP BY map_aliases.alias 
      ORDER BY map_aliases.alias 
      LIMIT 5";
      $res1 = Database::secureRead($sql1, $searchInput);
      
      if($res1 != NULL && !is_array($res1[0]))
      {
         throw new Exception('Could not find games: '. $res1[2]);
      }
      
      // Converts results into a linear array (results are given as a 2D array)
      $output = array();
      $nbResults = count($res1);
      for($i = 0; $i < $nbResults; $i++)
         array_push($output, $res1[$i]['alias']);
      
      return $output;
   }
}
