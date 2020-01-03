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
      $res = Database::secureRead("SELECT tag FROM map_aliases WHERE alias=? ORDER BY tag", 
                        array($this->_data['tag']));
      
      if($res != NULL && !is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Cannot retrieve aliases: '. $res[2]);
      else if($res == NULL)
         return NULL;
      
      // Simplification : Database::secureRead() result is a 2D array; it is converted into a linear one.
      $result = array();
      for($i = 0; $i < count($res); $i++)
         array_push($result, $res[$i]['tag']);
      
      return $result;
   }
   
   /*
   * Gets a list of tropes mapped to the game. This list consists of the 5 most recurring tropes 
   * proposed by users in reviews. The trope data (i.e., description) also comes with the results.
   *
   * @return mixed[]    A 2D array containing the 5 most voted tropes (NULL if nothing)
   * @throws Exception  If a problem occurs while reading the DB (SQL error is provided)
   */
   
   public function getTropes()
   {
      $sql = 'SELECT tag, color, description, COUNT(*) AS occurrences 
      FROM map_tropes_reviews 
      NATURAL JOIN reviews 
      NATURAL JOIN tropes 
      WHERE game=? GROUP BY tag 
      ORDER BY occurrences DESC, tag LIMIT 5';
      $res = Database::secureRead($sql, array($this->_data['tag']));
      
      if($res != NULL && !is_array($res[0]) && sizeof($res) == 3)
         throw new Exception('Cannot retrieve tropes: '. $res[2]);
      
      return $res;
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
   
   /*
   * Static method to count the number of games matching a set of tropes. A second parameter
   * indicates whether the calling code wants results to work by popularity (games with the 
   * largest amount of occurrences for the given tropes come first) or by accuracy (games with 
   * the largest amount of matching tropes come first).
   *
   * @param string $tropes[]    The set of tropes to match
   * @param bool $strict        True if search is strict, cf. above (optional)
   * @return number             The amount of games matching the tropes
   * @throws Exception          If games could not be found (SQL error is provided) or if $tropes 
   *                            is not an array
   */
   
   public static function countGamesWithTropes($tropes, $strict = true)
   {
      if(!is_array($tropes))
         throw new Exception('Tropes must be provided as an array');
         
      $nbTropes = count($tropes);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbTropes; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $tropes[$i]);
      }
      
      $sql = '';
      if($strict)
      {
         $sql = 'SELECT COUNT(*) AS nb 
         FROM games INNER JOIN (
         SELECT game FROM map_tropes_reviews 
         INNER JOIN reviews 
         ON reviews.id_review=map_tropes_reviews.id_review 
         WHERE map_tropes_reviews.tag IN ('.$toParse.') 
         GROUP BY game, tag
         ) relevant_games 
         ON relevant_games.game=games.tag 
         GROUP BY games.tag';
      }
      else
      {
         $sql = 'SELECT COUNT(*) AS nb 
         FROM games INNER JOIN (
         SELECT game, COUNT(game) AS matches 
         FROM map_tropes_reviews 
         INNER JOIN reviews 
         ON reviews.id_review=map_tropes_reviews.id_review 
         WHERE map_tropes_reviews.tag IN ('.$toParse.') 
         GROUP BY game ORDER BY matches DESC
         ) relevant_games 
         ON relevant_games.game=games.tag';
      }
      
      $res = Database::secureRead($sql, $sqlInput, true);
      
      if(count($res) == 3)
         throw new Exception('Games could not be found: '. $res[2]);
      
      return $res['nb'];
   }
   
   /*
   * Static method to get a set of games matching a set of tropes. Parameters $first and $nb 
   * are still needed in order to use pages when there are too many results for a single page. A 
   * 4th parameter indicates to the method what mode should be used.
   *
   * @param string $tropes[]    The set of tropes to match
   * @param number $first       The index of the first topic of the set
   * @param number $nb          The maximum amount of topics to list
   * @param bool $strict        True if search is strict, cf. above (optional)
   * @return number             The amount of games matching the tropes
   * @throws Exception          If games could not be found (SQL error is provided) or if $tropes 
   *                            is not an array
   */
   
   public static function getGamesWithTropes($tropes, $first, $nb, $strict = true)
   {
      if(!is_array($tropes))
         throw new Exception('Tropes must be provided as an array');
         
      $nbTropes = count($tropes);
      $toParse = '';
      $sqlInput = array();
      for($i = 0; $i < $nbTropes; $i++)
      {
         if($i > 0)
            $toParse .= ', ';
         $toParse .= '?';
         array_push($sqlInput, $tropes[$i]);
      }
      
      $sql = '';
      if($strict)
      {
         $sql = 'SELECT games.*, COUNT(games.tag) AS matches
         FROM games INNER JOIN (
         SELECT game FROM map_tropes_reviews
         INNER JOIN reviews
         ON reviews.id_review=map_tropes_reviews.id_review
         WHERE map_tropes_reviews.tag IN ('.$toParse.')
         GROUP BY game, tag
         ) relevant_games
         ON relevant_games.game=games.tag
         GROUP BY games.tag
         ORDER BY matches DESC, games.tag';
      }
      else
      {
         $sql = 'SELECT games.* 
         FROM games INNER JOIN (
         SELECT game, COUNT(game) AS matches
         FROM map_tropes_reviews
         INNER JOIN reviews
         ON reviews.id_review=map_tropes_reviews.id_review
         WHERE map_tropes_reviews.tag IN ('.$toParse.')
         GROUP BY game ORDER BY matches DESC
         ) relevant_games
         ON relevant_games.game=games.tag
         ORDER BY relevant_games.matches DESC, games.tag';
      }
      $sql .= ' LIMIT '.$first.','.$nb;
      
      $res = Database::secureRead($sql, $sqlInput);
      
      if(!is_array($res[0]) && count($res) == 3)
         throw new Exception('Games could not be found: '. $res[2]);
      else if($res == NULL)
         throw new Exception('No game has been found.');
      
      return $res;
   }
}
