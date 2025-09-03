<?php

/**
* This script creates a new emoticon given a $_FILES array containing a proper picture (120x90 
* maximum, JP(E)G, PNG or GIF) and some additionnal data (a name and a shortcut). If everything 
* goes well, the emoticon is first created in the database and then moved to the directory 
* uploads/emoticons/. A rendered thumbnail is also printed as a responsive. Otherwise, a short 
* message giving the reason of failure is printed. This script is only available for logged users.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/Upload.lib.php';
require '../model/Emoticon.class.php';
require '../view/intermediate/EmoticonThumbnail.ir.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

if(!empty($_FILES['image']) && !empty($_POST['name']) && !empty($_POST['shortcut']))
{
   $uploaded = $_FILES['image'];
   $gotName = Utils::secure($_POST['name']);
   $gotShortcut = Utils::secure($_POST['shortcut']);
   
   $res = '';
   if(!Emoticon::hasGoodFormat($gotShortcut))
   {
      $res = 'bad shortcut';
   }
   if($uploaded['size'] > ((1024 * 1024) / 2) || $uploaded['size'] == 0)
   {
      $res = 'file too big';
   }
   elseif(($uploaded['size'] + Upload::directorySize('upload')) > (4 * 1024 * 1024 * 1024))
   {
      $res = 'no more space';
   }
   else
   {
      $extension = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
      $dimensions = getimagesize($uploaded['tmp_name']);
      $acceptedExts = array('jpeg', 'jpg', 'png', 'gif');
      if($dimensions[0] > 120 || $dimensions[1] > 90)
      {
         $res = 'bad dimensions';
      }
      else if(!in_array($extension, $acceptedExts))
      {
         $res = 'bad file format';
      }
      else
      {
         $finalEmoticonPath = Upload::storeFile($uploaded, 'upload/emoticons/'); // Temporar in case of failure
         if($finalEmoticonPath !== "")
         {
            // Now that the picture is compliant and stored, we can deal with the DB.
            $newEmoticon = null;
            try
            {
               $finalFileName = substr(strrchr($finalEmoticonPath, '/'), 1);
               $newEmoticon = Emoticon::insert($finalFileName, $gotName, $gotShortcut);
            }
            catch(Exception $e)
            {
               unlink($finalEmoticonPath);
               if(strstr($e->getMessage(), 'uplicat') != FALSE)
                  $res = 'duplicate shortcut';
               else
                  $res = 'DB error';
            }
            
            // No error is generated if the mapping fails; there is no reason to stop creation
            if($newEmoticon != null)
            {
               try
               {
                  $newEmoticon->mapTo(LoggedUser::$data['pseudo']);
               }
               catch(Exception $e) { }
               
               $emoticonIR = EmoticonThumbnailIR::process($newEmoticon->getAll());
               $res = TemplateEngine::parse('view/user/Emoticon.item.ctpl', $emoticonIR);
            }
         }
         else
         {
            $res = 'fail';
         }
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
