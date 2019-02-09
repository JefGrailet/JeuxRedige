<?php

/**
* This script creates a new trope icon (which must be in PNG, and fit in a 45x45 canvas) given a 
* $_FILES array containing a picture that will be resized in order to create the thumbnail. If 
* everything goes well, the thumbnail is stored in the folder upload/tmp and the absolute path is 
* printed as a response. Otherwise, a message giving the reason of failure is printed. Also, this 
* script is only available for logged and authorized users.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/Buffer.lib.php';
require '../libraries/Upload.lib.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_FILES['image']))
{
   $uploaded = $_FILES['image'];
   
   $res = '';
   if($uploaded['size'] > (1024 * 1024) || $uploaded['size'] == 0)
      $res = 'file too big';
   elseif(($uploaded['size'] + Upload::directorySize('upload')) > (4 * 1024 * 1024 * 1024))
      $res = 'no more space';
   else
   {
      $extension = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
      $filename = 'icon_'.substr($uploaded['name'], 0, -(strlen($extension) + 1));
      if($extension === 'png') // We only accept PNG
      {
         // Creates user's temporary directory if it does not exist yet
         Buffer::create();
         
         // Cleans away previous trope icons
         Buffer::cleanTropeIcons();
         
         $destDir = 'upload/tmp/'.LoggedUser::$data['pseudo'];
         
         // Stores and resize the picture
         $res = Upload::storeResizedPicture($uploaded, $destDir, 45, 45, $filename);
         
         // In case of success: no error message, hence the ""; response is the path to the image
         if($res !== "")
            $res = './upload/tmp/'.LoggedUser::$data['pseudo'].'/'.substr(strrchr($res, '/'), 1);
         else
            $res = 'fail';
      }
      else
         $res = 'not a PNG';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $res;
}
else
{
   header('Content-Type: text/html; charset=UTF-8');
   echo 'file not loaded';
}

?>
