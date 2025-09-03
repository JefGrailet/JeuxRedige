<?php

/**
* PostHistory class models a archived version of a message in a topic. It is analoguous to the 
* "Post" class, but is much simpler since the only interaction with an archived post is the 
* ability to censor it (which is only accessible to administrators/moderators).
*/

class PostHistory
{
   private $_data;
   
   /*
   * Constructor. It has the particularity of instantiating the object on the basis of a pair 
   * [post_id,version] also provided as an array, which is disambiguated from a whole array by 
   * checking the size.
   *
   * @param mixed $arg[]  Existing array corresponding to the post or a pair [post_id,version]
   * @throws Exception    If the message cannot be found or does not exist, or if the provided 
   *                      input does not comply with the specification
   */
   
   public function __construct($arg)
   {
      if(is_array($arg) && count($arg) > 2)
      {
         $this->_data = $arg;
      }
      else if(is_array($arg) && count($arg) == 2)
      {
         $sql = "SELECT * FROM posts_history WHERE id_post=? && version=?";
         $this->_data = Database::secureRead($sql, $arg, true);
         
         if($this->_data == NULL)
            throw new Exception('Post does not exist.');
         else if(sizeof($this->_data) == 3)
            throw new Exception('Post could not be found: '. $this->_data[2]);
      }
      else
         throw new Exception('Please provide a valid input.');
   }
   
   /*
   * Static method to insert a new archived message in the database.
   *
   * @param mixed $post[]     The post entry to be archived, as an array
   * @throws Exception        When the insertion of the archived message in the database fails, 
   *                          with the actual SQL error inside
   */
   
   public static function insert($post)
   {
      $sql = "INSERT INTO posts_history VALUES(:id_post, :version, :id_topic, :author, :ip_author, 
              :editor, :posted_as, :date, :content, :attachment, :censorship)";
   
      $nbEditions = intval($post['nb_edits']);
      $badScore = intval($post['bad_score']);
      
      $versionNumber = $nbEditions + 1;
      $versionEditor = $post['author'];
      $versionDate = $post['date'];
      $versionCensorship = 'no';
      
      if($nbEditions > 0)
      {
         $versionEditor = $post['last_editor'];
         $versionDate = $post['last_edit'];
      }
      
      if($badScore >= 10)
         $versionCensorship = 'yes';
   
      $arg = array('id_post' => $post['id_post'], 
      'version' => $versionNumber, 
      'id_topic' => $post['id_topic'], 
      'author' => $post['author'], 
      'ip_author' => $post['ip_author'], 
      'editor' => $versionEditor, 
      'posted_as' => $post['posted_as'], 
      'date' => $versionDate, 
      'content' => $post['content'], 
      'attachment' => $post['attachment'], 
      'censorship' => $versionCensorship);
      
      $res = Database::secureWrite($sql, $arg);
      if($res != NULL)
         throw new Exception('Post could not be archived: '. $res[2]);
   }
   
   // Accessers
   public function get($field) { return $this->_data[$field]; }
   public function getAll() { return $this->_data; }
   
   /*
   * Method to censor an archived post. Censorship consists here in raising a flag which will have 
   * two consequences upon rendering the post:
   * -its content will be hidden at first,
   * -every image or video format code will be removed. 
   * Unlike votes in the Post class, this operation cannot be reverted. No argument is required.
   *
   * @return integer      1 if the post was censored, 0 if it was already censored and -1 if the 
   *                      user is not allowed to censor this archived post.
   * @throws Exception    If the archived post could not be updated (will contain the SQL error)
   */
   
   public function censor()
   {
      if(!LoggedUser::isLoggedIn() || !Utils::check(LoggedUser::$data['can_edit_all_posts']))
         return -1;
   
      if(Utils::check($this->_data['censorship']))
         return 0;
   
      $sql = "UPDATE posts_history SET censorship='yes' WHERE id_post=? && version=?";
      $arg = array($this->_data['id_post'], $this->_data['version']);
      $res = Database::secureWrite($sql, $arg);
      
      if($res != NULL)
         throw new Exception('Archived post could not be censored: '. $res[2]);
      
      $this->_data['censorship'] = 'yes';
      return 1;
   }
}

?>
