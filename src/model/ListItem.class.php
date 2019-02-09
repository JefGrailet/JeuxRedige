<?php

/**
* ListItem models a single item for a GamesList object. It is represented in the DB by rows of the 
* "map_lists_games" table.
*/

class ListItem
{
   private $_data;
   
   /*
   * Constructor.
   *
   * @param mixed $arg[]  Existing array corresponding to this list or an ID
   * @throws Exception    If the content cannot be found or does not exist
   */
   
   public function __construct($arg)
   {
      if(is_array($arg))
      {
         $this->_data = $arg;
      }
      else
      {
         $sql = "SELECT * FROM map_lists_games WHERE id_item=?";
         $this->_data = Database::secureRead($sql, array($arg), true);
         
         if($this->_data == NULL)
            throw new Exception('List item does not exist.');
         else if(count($this->_data) == 3)
            throw new Exception('List item could not be found: '. $this->_data[2]);
      }
   }
   
   /*
   * Static method to insert a new list item into the database.
   *
   * @param integer $listID   The ID of the list this item will belong to
   * @param string $game      The game being listed
   * @param string $comment   A comment for the item (mandatory)
   * @param integer $rank     The rank of the item in the list
   * @param string $subtitle  Will override the title of the game in the list display (optional)
   * @return GamesList        The new entry as a ListItem instance
   * @throws Exception        When the insertion of the content in the database fails, with the 
   *                          actual SQL error inside
   */
   
   public static function insert($listID, $game, $comment, $rank, $subtitle = '')
   {
      $finalRank = 0;
      $parsedRank = intval($rank);
      if($parsedRank > 0)
         $finalRank = $parsedRank;
      
      $finalSubtitle = NULL;
      if(strlen($subtitle) > 0 && strlen($subtitle) < 100)
         $finalSubtitle = $subtitle;
      
      $toInsert = array('listID' => $listID, 
      'game' => $game,
      'subtitle' => $finalSubtitle,
      'comment' => $comment,
      'rank' => $finalRank);
      
      $sql = "INSERT INTO map_lists_games VALUES('0', :listID, :game, :subtitle, :comment, :rank)";
      $res = Database::secureWrite($sql, $toInsert);
      if($res != NULL)
         throw new Exception('Could not insert new list item: '. $res[2]);
         
      $newItemID = Database::newId();
      return new ListItem($newItemID);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Updates the item to change its comment and subtitle.
   *
   * @param string $comment   A comment for the item (mandatory)
   * @param string $subtitle  Will override the title of the game in the list display (optional)
   * @throws Exception        When the update fails, with the SQL error inside
   */
   
   public function edit($comment, $subtitle = '')
   {
      $finalSubtitle = NULL;
      if(strlen($subtitle) > 0 && strlen($subtitle) < 100)
         $finalSubtitle = $subtitle;
      
      $sql = 'UPDATE map_lists_games SET comment=:comment, subtitle=:title WHERE id_item=:id_item';
      $arg = array('comment' => $comment, 'title' => $finalSubtitle, 'id_item' => $this->_data['id_item']);
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
         throw new Exception('Could not update list item '.$this->_data['id_item'].': '. $res[2]);
      
      $this->_data['comment'] = $comment;
      $this->_data['subtitle'] = $finalSubtitle;
   }
   
   /*
   * Resets the position of this item within its parent list. In practice, this just consists in 
   * updating the "rank" field and nothing else.
   *
   * @param integer $newRank  The new position in the parent list
   * @throws Exception        If anything goes wrong during the update (SQL error provided)
   */
   
   public function changeRank($newRank)
   {
      $sql = 'UPDATE map_lists_games SET rank=? WHERE id_item=?';
      $arg = array($newRank, $this->_data['id_item']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Item could not be updated: '. $res[2]);
      
      $this->_data['rank'] = $newRank;
   }
   
   /*
   * Deletes the item. A second SQL request is carried out to ensure items with higher rank see 
   * their rank getting decremented, which justifies a SQL transaction.
   *
   * @throws Exception  If deletion could not be carried out in the DB (SQL error provided)
   */
   
   public function delete()
   {
      Database::beginTransaction();
      
      $res1 = Database::secureWrite("DELETE FROM map_lists_games WHERE id_item=?", array($this->_data['id_item']), true);
      if(is_array($res1))
      {
         Database::rollback();
         throw new Exception('Unable to delete list item '.$this->_data['id_item'].' : '. $res1[2]);
      }
      
      $sql2 = "UPDATE map_lists_games SET rank=rank-1 WHERE id_commentable=? && rank > ?";
      $arg2 = array($this->_data['id_commentable'], $this->_data['rank']);
      $res2 = Database::secureWrite($sql2, $arg2, true);
      if(is_array($res2))
      {
         Database::rollback();
         throw new Exception('Unable to delete list item '.$this->_data['id_item'].' : '. $res2[2]);
      }
      
      Database::commit();
   }
}
