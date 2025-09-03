<?php

/**
 * The Utils static class gathers a bunch of utility (static) methods. For instance, it gathers 
 * every static method tied to handling time (as DATETIME or as a timestamp).
 */

class Utils
{
   private static $bufferedTime = 0;
   
   const UPLOAD_OPTIONS = array(
   'bufferLimit' => 15, // Max amount of uploads per message (in the user's buffer)
   'extensions' => array('jpeg', 'jpg', 'gif', 'png', 'mp4', 'webm'), // Available extensions
   'miniExtensions' => array('jpeg', 'jpg', 'gif', 'png'), // Extensions with custom miniatures
   'displayPolicies' => array('default', 
                              'spoiler', 
                              'nsfw', 
                              'noshow', 
                              'noshownsfw', 
                              'noshowspoiler') // Display policies (upload gallery below message)
   );
   
   // [0] => French name, [1] = gender in French, [2] = additional meta keywords
   const ARTICLES_CATEGORIES = array(
   'review' => array('Critique', 'f', 'Critique, Test, Review'), 
   'preview' => array('Aperçu', 'm', 'Aperçu, Preview'), 
   'opinion' => array('Humeur', 'f', 'Humeur, Opinion'), 
   'chronicle' => array('Chronique', 'f', 'Chronique, Chronicle'), 
   'guide' => array('Guide', 'm', 'Guide, Astuces, Tips'), 
   'misc' => array('Hors Jeu', 'm', 'Hors Jeu, Divers, Inclassable')
   );
   
   /**
    * Shortens the title of some content if that title is longer than 40 characters. The method 
    * also incorporates a simple heuristic: if the "-" character is present, then it keeps 
    * everything after that character and shortens what comes before.
    *
    * @param string $title  Title to shorten
    * @return string        Shortened title, or the same string if no need to shorten the title
    */
   
   public static function shortenTitle($title)
   {
      if(strlen($title) < 40)
         return $title;
         
      $shortened = '';
      if(strpos($title, ' - ') !== FALSE)
      {
         $exploded1 = explode(' - ', $title);
         $exploded2 = explode(' ', $exploded1[0]);
         $shortened .= $exploded2[0];
         $next = 1;
         while(strlen($shortened.' '.$exploded2[$next].' - '.$exploded1[1]) < 40)
         {
            $shortened .= ' '.$exploded2[$next];
            $next++;
         }
         $shortened .= '... - '.$exploded1[1];
      }
      else
      {
         $exploded = explode(' ', $title);
         $shortened .= $exploded[0];
         $next = 1;
         while(strlen($shortened.' '.$exploded[$next]) < 40)
         {
            $shortened .= ' '.$exploded[$next];
            $next++;
         }
         $shortened .= '...';
      }
      return $shortened;
   }
   
   /**
    * Processes an input string to prevent security issues such as JavaScript code injection.
    *
    * @param string $text  The input string
    * @return string       The same string processed to prevent the use of HTML or JavaScript
    */
   
   public static function secure($text)
   {
      return htmlspecialchars(preg_replace('(javascript\s{0,}:)iUs','',$text), ENT_NOQUOTES);
   }
   
   /**
    * Tests a field from a row extracted from the database that is either "yes" or "no".
    *
    * @param string $yesOrNo  A string (extracted from the database) saying "yes" or "no"
    * @param bool             True if the string equals "yes"
    */
   
   public static function check($yesOrNo)
   {
      return $yesOrNo === 'yes';
   }
   
   /**
    * Returns the UNIX timestamp of the SQL server. It is preferred to the PHP function time() 
    * because what it returns can vary wildly under some hosting solutions.
    *
    * @return number  The UNIX timestamp of the SQL server
    */
   
   public static function SQLServerTime()
   {
      if(self::$bufferedTime != 0)
         return self::$bufferedTime;
      
      $time = Database::hardRead("SELECT UNIX_TIMESTAMP() AS time", true);
      self::$bufferedTime = $time['time'];
      return $time['time'];
   }
   
   /**
    * Converts a UNIX timestamp into the corresponding DATETIME format for SQL queries.
    *
    * @param integer $t  A UNIX timestamp
    * @return string     The same date in DATETIME format
    */
   
   public static function toDatetime($t)
   {
      return date('Y-m-d H:i:s', $t);
   }
   
   /**
    * Performs the reverse operation of toDatetime().
    *
    * @param string $dbTime  A date in DATETIME format
    * @return integer        The same date as a UNIX timestamp
    */
   
   public static function toTimestamp($dbTime)
   {
      $arr = array('y' => substr($dbTime, 0, 4),
      'm' => substr($dbTime, 5, 2),
      'd' => substr($dbTime, 8, 2),
      'h' => substr($dbTime, 11, 2),
      'min' => substr($dbTime, 14, 2),
      's' => substr($dbTime, 17, 2));
      
      return mktime($arr['h'], $arr['min'], $arr['s'], $arr['m'], $arr['d'], $arr['y']);
   }
   
   /**
    * Formats the date as a string. It removes the year when it's the current one.
    * 
    * @param string $dbTime  A date in DATETIME format
    * @return string         The formatted date
    */

   public static function printDate($dbTime)
   {
      $curTime = Utils::SQLServerTime();
      $arr = array('y' => substr($dbTime, 0, 4),
      'm' => substr($dbTime, 5, 2),
      'd' => substr($dbTime, 8, 2),
      'h' => substr($dbTime, 11, 2),
      'min' => substr($dbTime, 14, 2),
      's' => substr($dbTime, 17, 2));
      
      $res = $arr['d'].'/'.$arr['m'];
      if(date('Y', $curTime) !== $arr['y'])
         $res .= '/'.$arr['y'];
      $res .= ' à '.$arr['h'].':'.$arr['min'].':'.$arr['s'];
      return $res;
   }
   
   /**
    * In a sequence of arrays with dates, removes the seconds from arrays which are unique up to 
    * the minutes (or further). Consecutives arrays where only seconds vary keep the seconds part.
    *
    * @param mixed[] $arrays  The sequence of arrays (2D array, each array has the "date" key)
    * @return mixed[]         The updated arrays;
    */
   
   public static function removeSeconds($arrays)
   {
      $prev = '';
      $prevHasSeconds = false;
      for($i = 0; $i < count($arrays) - 1; $i++)
      {
         $cur = $arrays[$i + 1]['date'];
         if($prev === '')
            $prev = $arrays[$i]['date'];
         
         $exploded1 = explode(':', $cur);
         $exploded2 = explode(':', $prev);
         if($exploded1[0] !== $exploded2[0] || $exploded1[1] !== $exploded2[1])
         {
            if(!$prevHasSeconds)
               $arrays[$i]['date'] = $exploded2[0].':'.$exploded2[1];
            else
               $prevHasSeconds = false;
            if($i == (count($arrays) - 2))
               $arrays[$i + 1]['date'] = $exploded1[0].':'.$exploded1[1];
         }
         else
            $prevHasSeconds = true;
         
         $prev = $cur;
      }
      return $arrays;
   }
   
   /**
    * Returns a string suitable for the template engine to allow picking a category for an article 
    * via a <select> tag. It essentially mixes together the values of 'cat_db' and 'cat_fr_names'.
    *
    * @param bool withNone   An optional boolean parameter to set to true if an additional choice 
    *                        allowing to not consider a specific category (e.g. in search forms) 
    *                        should be added (default is false)
    * @return string         A string feeding the categories for a <select> tag
    */
   
   public static function makeCategoryChoice($withNone = false)
   {
      $selectStr = '';
      $categories = array_keys(self::ARTICLES_CATEGORIES);
      for ($i = 0; $i < count($categories); $i++)
      {
         if ($i > 0)
            $selectStr .= '|';
         $selectStr .= $categories[$i].','.self::ARTICLES_CATEGORIES[$categories[$i]][0];
      }
      if ($withNone)
         $selectStr = 'all,Toute catégorie confondue|'.$selectStr;
      return $selectStr;
   }
   
   /**
    * Returns a HTML string to print the categories of articles as a succession of "pretty links" 
    * for any given page where it's possible to browse by article category. The idea is to avoid 
    * modifying each template involving such links when a new category is being added or renamed.
    *
    * @param string $curPage   The page where the links will be inserted
    * @param string $selected  A category that has been selected (optional; empty by default)
    * @return string           A string listing the links
    */
   
   public static function makeCategoryLinks($curPage, $selected = '')
   {
      $linksHtml = '';
      $categories = array_keys(self::ARTICLES_CATEGORIES);
      $categorySelected = false;
      for ($i = 0; $i < count($categories); $i++)
      {
         if ($categories[$i] === $selected)
         {
            $linksHtml .= '<span class="prettyText">';
            $linksHtml .= self::ARTICLES_CATEGORIES[$categories[$i]][0];
            if ($i < (count($categories) - 1))
               $linksHtml .= 's';
            $linksHtml .= '</span> '."\n";
            $categorySelected = true;
         }
         else
         {
            $linksHtml .= '<a class="prettyLink '.$categories[$i].'" ';
            $linksHtml .= 'href="'.$curPage.'?article_category='.$categories[$i].'">';
            $linksHtml .= self::ARTICLES_CATEGORIES[$categories[$i]][0];
            if ($i < (count($categories) - 1))
               $linksHtml .= 's';
            $linksHtml .= '</a> '."\n";
         }
      }
      if ($categorySelected)
         $linksHtml = '<a class="prettyLink" href="'.$curPage.'">Tout</a> '."\n".$linksHtml;
      else
         $linksHtml = '<span class="prettyText">Tout</span> '."\n".$linksHtml;
      return $linksHtml;
   }
}
