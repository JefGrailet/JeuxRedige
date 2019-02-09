<?php

/**
* Library to handle mails. For now, it's only used to send an e-mail in a reliable way.
*/

class Mailing
{
   /*
   * Sends a mail to the destinations $dest with title $title and text $content. The main interest 
   * in defining this function is to not rewrite headers each time a mail has to be sent. 
   * Moreover, these headers must be carefully written in order to pass through some filters (e.g. 
   * Microsoft SmartScreen), by adding the date or the server's IP, for example.
   *
   * @param string $dest      The destination(s)
   * @param string $title     Title of the e-mail
   * @param string $text      The text (formatted in HTML if necessayr) inside the e-mail
   * @param string $encoding  The encoding of the e-mail (default: UTF-8) (optional)
   * @return bool             True if the e-mail has been successfully sent, false otherwise
   */

   public static function send($dest, $title, $content, $encoding = "UTF-8")
   {   
      $headers  = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'From: "Project AG" <noreply@projectag.org>' . "\r\n";
      $headers .= 'Reply-to: "Project AG" <noreply@projectag.org>' . "\r\n";
      $headers .= 'Content-Type: text/html; charset="'.$encoding.'"' . "\r\n";
      $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
      $headers .= 'Date: ' . date('r', $_SERVER['REQUEST_TIME']) . "\r\n";
      $headers .= 'X-Mailer: PHP v' . phpversion() . "\r\n";
      $headers .= 'X-Originating-IP: ' . $_SERVER['SERVER_ADDR'] . "\r\n";

      return mail($dest, $title, $content, $headers);
   }
}

?>