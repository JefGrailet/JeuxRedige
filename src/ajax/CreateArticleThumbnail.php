<?php

/**
* This script creates a new article thumbnail (which is slightly bigger than a topic thumbnail, 
* i.e., 370x208 pixels) given a $_FILES array containing a picture that will be resized in order 
* to create an article thumbnail. If everything goes well, the thumbnail is stored in the folder 
* thumbnails/custom/tmp as article_thumbnail.jpg and the absolute path is printed as a response. 
* Otherwise, a message giving the reason of failure is printed. Also, this script is only 
* available for logged users.
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
      $filename = 'article_thumbnail_'.substr($uploaded['name'], 0, -(strlen($extension) + 1));
      if($extension === 'jpeg' || $extension === 'jpg') // We only accept JP(E)G
      {
         // Creates user's temporary directory if it does not exist yet
         Buffer::create();
         
         // Cleans away previous thumbnails
         Buffer::cleanArticleThumbnails();
         
         $destDir = 'upload/tmp/'.LoggedUser::$data['pseudo'];
         
         // Stores and resize the picture
         $res = Upload::storeResizedPicture($uploaded, $destDir, 370, 208, $filename);
         
         // In case of success: no error message, hence the ""; response is the path to the image
         if($res !== "")
            $res = './upload/tmp/'.LoggedUser::$data['pseudo'].'/'.substr(strrchr($res, '/'), 1);
         else
            $res = 'fail';
      }
      else
         $res = 'not a JPEG';
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
