<?php

/**
* This script stores an uploaded JPEG, PNG or GIF picture (as a $_FILES array) and creates a 
* miniature of it. If everything goes well, both images are stored in the folder upload/tmp/User/
* with the prefixes full_ and mini_ (respectively) and the absolute path to both images is given
* as [miniSizePath],[fullSizePath]. Otherwise, a message giving the reason of failure is printed. 
* Also, this script is only available for logged users (for obvious reasons).
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

if(!empty($_FILES['newFile']))
{
   $uploaded = $_FILES['newFile'];
   
   // User reached upload limit
   $userBuffer = Buffer::listContent();
   if($userBuffer != NULL && count($userBuffer[0]) >= Utils::UPLOAD_OPTIONS['bufferLimit'])
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'buffer limit reached';
      exit();
   }
   
   $res = '';
   if($uploaded['size'] > 5 * 1024 * 1024)
      $res = 'file too big';
   elseif(($uploaded['size'] + Upload::directorySize('upload')) > (4 * 1024 * 1024 * 1024))
      $res = 'no more space';
   else
   {
      $ext = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
      $originalName = substr($uploaded['name'], 0, (strlen($uploaded['name']) - strlen($ext) - 1));
      $miniSizeName = 'mini_'.$originalName;
      
      // Supported formats: JPEG, GIF and PNG
      if(in_array($ext, Utils::UPLOAD_OPTIONS['extensions']))
      {
         // Creates user's temporary directory if it does not exist yet
         Buffer::create();
         $destDir = 'upload/tmp/'.LoggedUser::$data['pseudo'];
         
         // Extensions for which a miniature is required (i.e., images)
         if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
         {
            // Renames the uploaded file, creates the miniature and stores it
            $res1 = Upload::storeResizedPicture($uploaded, $destDir, 225, 0, $miniSizeName);
            
            if($res1 === "")
               $res = 'fail';
            else
            {
               // Stores the full image
               $fullSizeName = 'full_'.substr(strrchr($res1, '/'), 6);
               $fullSizeName = substr($fullSizeName, 0, (strlen($fullSizeName) - strlen($ext) - 1));
               $res2 = Upload::storeFile($uploaded, $destDir, $fullSizeName);
               
               $finalMiniName = substr(strrchr($res1, '/'), 1);
               $finalFullName = substr(strrchr($res2, '/'), 1);
               
               if($res2 === "")
               {
                  $res = 'fail';
                  
                  // Deletes the miniature to not pollute upload/
                  unlink('../upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName);
               }
               else
               {
                  $fullRelative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalFullName;
                  $full = PathHandler::HTTP_PATH.$fullRelative;
                  $fullOnDisk = PathHandler::WWW_PATH.$fullRelative;
                  $mini = PathHandler::HTTP_PATH.'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName;
                  $miniOnDisk = PathHandler::WWW_PATH.'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName;
               
                  // In case of success: paths to both images are given and separated with a comma
                  $res = $mini.','.$full;
                  
                  // The full HTML code displaying the image is also given.
                  $dimMini = getimagesize($miniOnDisk);
                  $dimFull = getimagesize($fullOnDisk);
                  $deleteButton = Utils::check(LoggedUser::$data['can_upload']) ? 'yes' : '';
                  
                  $tplInput = array('fullSize' => $full,
                  'dimensions' => 'yes||'.$dimFull[0].'|'.$dimFull[1], 
                  'uploader' => LoggedUser::$data['used_pseudo'],
                  'uploadDate' => date('d/m/Y à H:i:s', filemtime(PathHandler::WWW_PATH.$fullRelative)),
                  'fullSizeRelative' => $full,
                  'delete' => $deleteButton,
                  'content' => 'picture||'.$mini.'|'.$dimMini[0].'|'.$dimMini[1]);
                  
                  $tplOutput = TemplateEngine::parse('view/content/Upload.item.edition.ctpl', $tplInput);
                  if(!TemplateEngine::hasFailed($tplOutput))
                  {
                     $res .= ',';
                     $res .= $tplOutput;
                  }
                  else
                     $res = 'fail2';
               }
            }
         }
         else
         {
            /*
             * Any other extension (currently, videos): no miniature, the <video> tag will display 
             * the first frame by itself.
             */
         
            $res2 = Upload::storeFile($uploaded, $destDir);
            if($res2 === "")
            {
               $res = 'fail';
            }
            else
            {
               // In case of success: path to the video is given
               $relative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.substr(strrchr($res2, '/'), 1);
               $full = PathHandler::HTTP_PATH.$relative;
               $res = 'video,'.$full;
               
               $deleteButton = Utils::check(LoggedUser::$data['can_upload']) ? 'yes' : '';
               $tplInput = array('fullSize' => $full, 
               'dimensions' => '', 
               'uploader' => LoggedUser::$data['used_pseudo'], 
               'uploadDate' => date('d/m/Y à H:i:s', filemtime(PathHandler::WWW_PATH.$relative)), 
               'fullSizeRelative' => $full,
               'delete' => $deleteButton, 
               'content' => 'video||'.$full.'|'.$ext);
               
               $tplOutput = TemplateEngine::parse('view/content/Upload.item.edition.ctpl', $tplInput);
               if(!TemplateEngine::hasFailed($tplOutput))
               {
                  $res .= ',';
                  $res .= $tplOutput;
               }
               else
                  $res = 'fail';
            }
         }
      }
      else
         $res = 'not a supported format';
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
