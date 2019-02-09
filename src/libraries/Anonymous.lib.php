<?php

/**
* This library deals with the anonymous users and their temporary pseudonyms. Anonymous users are 
* identified with their IP, and a pseudonym is "reserved" for an IP until 12 hours have passed 
* since the last message from that user. IPs and pseudonyms are known through the table "posts".
*
* It is also worth noting that computing the time since the last message of one anonymous user can
* be useful to prevent him or her from spamming/boosting a topic (a captcha is also used to block
* bots, but this is not relevant in this library).
*/

class Anonymous
{
   /*
   * Gets the pseudonym of the current (anonymous) user on the basis of its IP, in a certain delay 
   * (anonymous pseudonyms used more than 12 hours before now are not considered).
   *
   * @return string  The pseudonym associated with this user or an empty string (no pseudonym)
   */

   public static function getPseudo()
   {
      $timeMinus12h = timestampToDateTime(Utils::SQLServerTime() - 43200);
      $sql = "SELECT author FROM posts WHERE date > ? && ip_author=? && posted_as='anonymous' ORDER BY date DESC LIMIT 1";
      $pseudo = Database::secureRead($sql, array($timeMinus12h, $_SERVER['REMOTE_ADDR']), true);
      
      if($pseudo != NULL && sizeof($pseudo) == 1)
         return $pseudo['author'];
      
      // Because of SQL error, User will be allowed to choose another pseudonym
      return '';
   }

   /*
   * Checks that a pseudonym has not been used by another anonymous user during the last 12 hours.
   *
   * @param string $pseudo  The pseudonym to check
   * @return bool           True if the pseudonym is available, false otherwise
   */

   public static function isAvailable($pseudo)
   {
      $timeMinus12h = timestampToDateTime(Utils::SQLServerTime() - 43200);
      $sql = "SELECT COUNT(*) AS nb FROM posts WHERE date > ? && author=? && posted_as='anonymous' ORDER BY date DESC";
      $nbPosts = Database::secureRead($sql, array($timeMinus12h, $pseudo), true);
      
      if($nbPosts != NULL && sizeof($nbPosts) == 1 && $nbPosts['nb'] > 0)
         return false;
      
      return true;
   }

   /*
   * Computes the number of seconds since the last message of a given pseudonym.
   *
   * @param string $pseudo  The pseudonym for which we want a delay in seconds
   * @return number         The desired number OR -1 if there is no message with such pseudonym
   */

   public static function lastActivity($pseudo)
   {
      $currentTime = Utils::SQLServerTime();

      $sql = "SELECT date FROM posts WHERE author=? && posted_as='anonymous' ORDER BY date DESC LIMIT 1";
      $res = Database::secureRead($sql, array($pseudo), true);
      
      if($res != NULL && sizeof($res) == 1)
         return $currentTime - Utils::toTimestamp($res['date']);
      
      return -1;
   }
}

?>
