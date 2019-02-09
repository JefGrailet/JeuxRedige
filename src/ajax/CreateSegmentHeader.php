<?php

/**
* This script creates a new segment header given a $_FILES array containing a picture that will be 
* resized in order to create the header. If everything goes well, the header is stored in the 
* folder upload/tmp and the absolute path is printed as a response. Otherwise, a message giving 
* the reason of failure is printed. Also, this script is only available for logged users.
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
   if($uploaded['size'] > 5 * (1024 * 1024) || $uploaded['size'] == 0)
      $res = 'file too big';
   elseif(($uploaded['size'] + Upload::directorySize('upload')) > (4 * 1024 * 1024 * 1024))
      $res = 'no more space';
   else
   {
      $extension = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
      $filename = 'header_'.substr($uploaded['name'], 0, -(strlen($extension) + 1));
      if($extension === 'jpeg' || $extension === 'jpg') // We only accept JP(E)G
      {
         // Quick check of the dimensions
         $dimensions = getimagesize($uploaded['tmp_name']);
         if($dimensions[0] < 1920 || $dimensions[1] < 576)
         {
            $res = 'bad dimensions';
         }
         else
         {
            // Creates user's temporary directory if it does not exist yet
            Buffer::create();
            
            // Cleans away previous thumbnails/headers
            Buffer::cleanSegmentHeaders();
            
            $destDir = './upload/tmp/'.LoggedUser::$data['pseudo'];
            
            // Stores and resize the picture
            $res = Upload::storeResizedPicture($uploaded, $destDir, 1920, 576, $filename);
            
            // In case of success: no error message, hence the ""; response is the path to the image
            if($res !== "")
               $res = './upload/tmp/'.LoggedUser::$data['pseudo'].'/'.substr(strrchr($res, '/'), 1);
            else
               $res = 'fail';
         }
      }
      else
      {
         $res = 'not a JPEG';
      }
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
