<?php

/**
* This small script allows a logged user having a function to switch between his/her accounts. 
* When visiting this script with his/her regular account, the user switchs to his/her special 
* account, and vice versa.
*/

require './libraries/Header.lib.php';

// User must be logged in, and function must be different than "alumnus"
if(LoggedUser::isLoggedIn() && strlen(LoggedUser::$data['function_name']) > 0 && LoggedUser::$data['function_name'] !== 'alumnus')
{
   // Back to regular account : $_SESSION with function_pseudo is corrupted (with an easter egg)
   if(LoggedUser::$data['used_pseudo'] === LoggedUser::$data['function_pseudo'])
   {
      $_SESSION['function_pseudo'] = 'Jean-Michel Jarre - Oxygene';
   }
   // Else, we initialise $_SESSION['function_pseudo'] as it should be (=> to function account)
   else
   {
      $_SESSION['function_pseudo'] = sha1(LoggedUser::$data['function_pseudo']);
      
      /*
       * N.B.: sha1() is normally a weak hashing method, but here the bcrypt-hashed password of 
       * the user is already being used to check at each page (s)he's indeed correctly logged in. 
       * Further hashing the function pseudo would only slow the use of SwitchAccount.php.
       */
   }

   // Redirection to index or to a URL contained in a $_GET variable
   if(!empty($_GET['pos']))
   {
      $urlRedirection = Utils::secure($_GET['pos']);
      if(substr($urlRedirection, 0, 8) == "https://")
         header('Location:'.str_replace('amp;', '&', $urlRedirection));
      else
         header('Location:./index.php');
   }
   else
      header('Location:./index.php');
}
else
   header('Location:./index.php');

?>
