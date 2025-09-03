<?php

/**
 * The PathHandler static class gathers everything needed to properly handle paths on the website. 
 * On the one hand, it provides WWW_PATH, used to reach files on the server via absolute paths, 
 * and HTTP_PATH on the other hand, the client side equivalent (i.e., to get full URLs). It also 
 * defines JS_EXTENSION to record the typical extension of JS files (.js or .min.js), which is 
 * given by the main configuration file of the website. All three of them are private static 
 * elements but can be accessed with public methods with the same names. PathHandler also provides 
 * some utilities as well as, such as methods for rewriting URLs to improve referencing.
 */

class PathHandler
{
   private static $WWW_PATH;
   private static $HTTP_PATH;
   private static $JS_EXTENSION;
   
   /**
    * Inits the public static variables of the class. Such variables used to be constants; to ease 
    * configuration of the website, they were turned into private static elements to allow setting 
    * them through the code while preventing their edition from outside. They are then accessed 
    * via (public) static methods. Indeed, it's forbidden by the language to set constants via 
    * variables (details here: https://www.php.net/manual/en/language.oop5.static.php).
    * 
    * @param string $autoWWW   The auto-determinated path to the root folder on this file system
    * @param string $autoHTTP  The auto-determinated prefix for all (absolute) URLs of the website
    * @param string $extJS     The extension of JavaScript (JS) files
    */
   
   public static function init($autoWWW, $autoHTTP, $extJS)
   {
      self::$WWW_PATH = $autoWWW;
      self::$HTTP_PATH = $autoHTTP;
      self::$JS_EXTENSION = $extJS;
   }
   
   public static function WWW_PATH() { return self::$WWW_PATH; }
   public static function HTTP_PATH() { return self::$HTTP_PATH; }
   public static function JS_EXTENSION() { return self::$JS_EXTENSION; }
   
   /**
    * Gets the absolute path to a user's avatar on the basis of its pseudonym.
    *
    * @param string $pseudo  The user's pseudonym
    * @return string         The absolute path to that user's avatar, or to a default one
    */

   public static function getAvatar($pseudo)
   {
      if(WebpageHandler::$miscParams['message_size'] === 'medium')
      {
         $avatarPath = self::$WWW_PATH.'avatars/'.$pseudo.'-medium.jpg';
         if(file_exists($avatarPath) == true)
            return self::$HTTP_PATH.'avatars/'.$pseudo.'-medium.jpg';
         return self::$HTTP_PATH.'defaultavatar-medium.jpg';
      }
      
      $avatarPath = self::$WWW_PATH.'avatars/'.$pseudo.'.jpg';
      if(file_exists($avatarPath) == true)
         return self::$HTTP_PATH.'avatars/'.$pseudo.'.jpg';
      return self::$HTTP_PATH.'defaultavatar.jpg';
   }
   
   /**
    * Gets the absolute path to the smallest version of one user's avatar.
    *
    * @param string $pseudo  The user's pseudonym
    * @return string         The absolute path to that user's avatar, small size
    */
   
   public static function getAvatarSmall($pseudo)
   {
      $avatarPath = self::$WWW_PATH.'avatars/'.$pseudo.'-small.jpg';
      if(file_exists($avatarPath) == true)
         return self::$HTTP_PATH.'avatars/'.$pseudo.'-small.jpg';
      return self::$HTTP_PATH.'defaultavatar-small.jpg';
   }
   
   /**
    * Gets the absolute path to the medium version of one user's avatar.
    *
    * @param string $pseudo  The user's pseudonym
    * @return string         The absolute path to that user's avatar, medium size
    */
   
   public static function getAvatarMedium($pseudo)
   {
      $avatarPath = self::$WWW_PATH.'avatars/'.$pseudo.'-medium.jpg';
      if(file_exists($avatarPath) == true)
         return self::$HTTP_PATH.'avatars/'.$pseudo.'-medium.jpg';
      return self::$HTTP_PATH.'defaultavatar-medium.jpg';
   }
   
   /**
    * Returns the absolute path to the thumbnail of a topic on the basis of its ID or name (i.e., 
    * a topic could use a thumbnail from a default library, found with a precise name). A default 
    * thumbnail is returned if no file could be found at the path.
    *
    * @param string $name     The name of the thumbnail
    * @param string $idTopic  The topic ID (to find the custom thumbnail if $name is CUSTOM)
    * @return string          The absolute path to a thumbnail for that topic
    */
   
   public static function getTopicThumbnail($name, $idTopic)
   {
      if($name === 'CUSTOM')
         $suffix = 'upload/topics/'.$idTopic.'/thumbnail.jpg';
      else
         $suffix = $name;
      
      $thumbnailPath = self::$WWW_PATH.$suffix;
      if(file_exists($thumbnailPath) == true)
         return self::$HTTP_PATH.$suffix;
      return self::$HTTP_PATH.'defaultthumbnail.jpg';
   }
   
   /**
    * Formats a string to fit in a URL, i.e. the formatted string will only contain characters in 
    * class a-zA-Z0-9 and -. Can also be used for formatting a string into a proper filename.
    *
    * @param string $input  The string to format
    * @return string        The formatted string
    */
   
   public static function formatForURL($input)
   {
      $accents = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 
      'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 
      'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 
      'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 
      'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 
      'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 
      'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 
      'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y');
      $output = strtr($input, $accents);
      
      $output = str_replace(" ", "-", $output);
      $output = preg_replace("([^a-zA-Z0-9-])", "", $output);
      
      while(substr($output, -1) == '-')
         $output = substr($output, 0, strlen($output) - 1); // Removes trailing -
      
      return $output;
   }
   
   /*
    * The rest of this class consists of "[something]URL()" static methods. The principle is 
    * always the same: the method takes the array which matches the content for which the URL must 
    * be generated, possibly with additional variables to denote a specific section or page. The 
    * output is always a string URL. Having such methods is a way to have a bottleneck to handle 
    * content URLs, e.g. if the URL has to be updated because of a new URL rewriting policy.
    */
   
   public static function topicURL($topic, $page = '')
   {
      $URL = './Topic.php?id_topic='.$topic['id_topic'];
      if($page !== '' && intval($page) != 1)
         $URL .= '&page='.$page;
      return $URL;
   }
   
   public static function uploadsURL($topic, $page = '')
   {
      $URL = './Uploads.php?id_topic='.$topic['id_topic'];
      if($page !== '' && intval($page) != 1)
         $URL .= '&page='.$page;
      return $URL;
   }
   
   public static function popularPostsURL($topic, $page = '')
   {
      $URL = './PopularPosts.php?id_topic='.$topic['id_topic'];
      if($page !== '' && intval($page) != 1)
         $URL .= '&page='.$page;
      return $URL;
   }

   public static function unpopularPostsURL($topic, $page = '')
   {
      $URL = './PopularPosts.php?section=unpopular&id_topic='.$topic['id_topic'];
      if($page !== '' && intval($page) != 1)
         $URL .= '&page='.$page;
      return $URL;
   }
   
   public static function gameURL($game, $section = '', $page = '')
   {
      $URL = './Game.php?game='.urlencode($game['tag']);
      if(in_array($section, array('articles', 'trivia', 'lists', 'topics')))
      {
         $URL .= '&section='.$section;
         if($page !== '' && intval($page) != 1)
            $URL .= '&page='.$page;
      }
      return $URL;
   }
   
   public static function articleURL($article, $segment = '')
   {
      $titleFormatted = self::formatForURL($article['title'].' '.$article['subtitle']);
      $URL = 'articles/'.$article['id_article'].'/'.$titleFormatted.'/';
      if($segment !== '' && intval($segment) != 1)
         $URL .= $segment.'/';
      
      return self::$HTTP_PATH.$URL;
   }
   
   public static function triviaURL($trivia)
   {
      $URL = 'trivia/'.$trivia['id_commentable'].'/'.self::formatForURL($trivia['game']).'/';
      $URL .= PathHandler::formatForURL($trivia['title']).'/';
      
      return self::$HTTP_PATH.$URL;
   }
   
   public static function listURL($list)
   {
      $URL = 'lists/'.$list['id_commentable'].'/'.self::formatForURL($list['title']).'/';
      
      return self::$HTTP_PATH.$URL;
   }
   
   public static function userURL($pseudo)
   {
      return './User.php?user='.urlencode($pseudo);
   }
}
