<?php

/**
* Notification is the simplest type of Ping. It is a one-way message sent to some user to notify 
* him/her about a modification of his account carried out by an admin, a review of one of his/her 
* articles, etc. The emitter, in this case, is always the person who triggered the event being 
* notified (for instance, the admin).
*/

require './model/Ping.class.php';

class Notification extends Ping
{
   /*
   * Static method to insert a new notification in the database. As two SQL requests are needed, 
   * a SQL transaction is performed.
   *
   * @param string $receiver  The pseudonym of the user who will receive the message
   * @param string $title     Title of the notification
   * @param string $message   Message of the notification
   * @throws Exception        When the insertion of the notification in the database fails, with 
   *                          the actual SQL error inside
   */
   
   public static function insert($receiver, $title, $message)
   {
      Database::beginTransaction();
      
      try
      {
         $currentDate = Utils::toDatetime(Utils::SQLServerTime());
         $toInsert = array('emitter' => LoggedUser::$data['used_pseudo'], 
         'receiver' => $receiver, 
         'title' => $title, 
         'message' => $message, 
         'emission_date' => $currentDate);
         
         $sql = "INSERT INTO pings VALUES(0, :emitter, :receiver, 'notification', 'archived', 
                 :title, :message, :emission_date)";
         
         $res = Database::secureWrite($sql, $toInsert);
         if($res != NULL)
            throw new Exception('Could not insert new notification: '. $res[2]);
            
         $newPingID = Database::newId();
         
         $toInsertPart = array('id_ping' => $newPingID,
         'pseudo' => $receiver,
         'last_update' => $currentDate);
         
         $sqlPart = "INSERT INTO map_pings VALUES(:pseudo, :id_ping, 'no', :last_update)";
         
         $resPart = Database::secureWrite($sqlPart, $toInsertPart);
         if($resPart != NULL)
            throw new Exception('Could not add receiver: '. $resPart[2]);
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         throw $e;
      }
      
      // Nothing is returned in the case of a notification, because it's a one-way message
   }

}

?>
