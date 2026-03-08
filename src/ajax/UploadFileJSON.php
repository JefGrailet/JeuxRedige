<?php

/**
* This script stores an uploaded JPEG, PNG or GIF picture (as a $_FILES array) and creates a
* miniature of it. If everything goes well, both images are stored in the folder upload/tmp/User/
* with the prefixes full_ and mini_ (respectively) and the absolute path to both images is given
* as [miniSizePath],[fullSizePath]. Otherwise, a message giving the reason of failure is printed.
* Also, this script is only available for logged users (for obvious reasons).
*/

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   http_response_code(405);
   echo json_encode(["error" => "Method Not Allowed"]);
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/Buffer.lib.php';
require '../libraries/Upload.lib.php';

if(!LoggedUser::isLoggedIn())
{
   http_response_code(401);
   echo json_encode(["error" => "Vous devez être connecté(e)"]);
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   echo json_encode(["error" => "Method Not Allowed"]);

   exit();
}

if(!empty($_FILES['newFile']))
{
   $uploaded = $_FILES['newFile'];

   // User reached upload limit
   $userBuffer = Buffer::listContent();
   if($userBuffer != NULL && count($userBuffer[0]) >= Utils::UPLOAD_OPTIONS['bufferLimit'])
   {
      echo json_encode(["error" => "buffer limit reached"]);

      exit();
   }

   $res = '';
   if($uploaded['size'] > 5 * 1024 * 1024)
      $res = ["error" => 'file too big'];
   elseif(($uploaded['size'] + Upload::directorySize('upload')) > (4 * 1024 * 1024 * 1024))
      $res = ["error" => 'no more space'];
   else
   {
      $ext = strtolower(substr(strrchr($uploaded['name'], '.'), 1));
      $originalName = substr($uploaded['name'], 0, (strlen($uploaded['name']) - strlen($ext) - 1));
      $miniSizeName = 'mini_'.$originalName;

      // Supported formats: JPEG, GIF, PNG, MP4 and WebM
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
               $res = ["error" => 'fail'];
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
                  $res = ["error" => 'fail'];

                  // Deletes the miniature to not pollute upload/
                  unlink('../upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName);
               }
               else
               {
                  $fullRelative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalFullName;
                  $full = PathHandler::HTTP_PATH().$fullRelative;
                  $fullOnDisk = PathHandler::WWW_PATH().$fullRelative;
                  $mini = PathHandler::HTTP_PATH().'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName;
                  $miniOnDisk = PathHandler::WWW_PATH().'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$finalMiniName;

                  list($widthMini, $heightMini) = getimagesize($miniOnDisk);
                  list($widthFull, $heightFull) = getimagesize($fullOnDisk);

                  $res = ["success" =>
                     [
                        "mini" => [
                           "src" => $mini,
                           "size" =>  [
                              "width" => $widthMini,
                              "height" => $heightMini,
                           ]
                        ],
                        "full" => [
                           "src" => $full,
                           "srcRelative" => $fullRelative,
                           "size" =>  [
                              "width" => $widthFull,
                              "height" => $heightFull,
                           ]
                        ],
                        "mediaType" => "image",
                        "filename" => basename($res1),
                        "uploadDate" => date('d/m/Y à H:i:s', filemtime(PathHandler::WWW_PATH().$fullRelative)),
                     ]
                  ];
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
               $res = ["error" => 'fail'];
            }
            else
            {
               // In case of success: path to the video is given
               $relative = 'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.substr(strrchr($res2, '/'), 1);
               $full = PathHandler::HTTP_PATH().$relative;

               $res = ["success" => [
                  "full" => [
                     "src" => $full,
                     "srcRelative" => $relative,
                     "size" =>  []
                  ],
                  "mediaType" => "video",
                  "mimeType" => $uploaded["type"],
                  "uploadDate" => date('d/m/Y à H:i:s', filemtime(PathHandler::WWW_PATH().$relative)),
               ]];
            }
         }
      }
      else
      {
         $res = ["error" => "not a supported format"];
      }
   }

   echo json_encode($res);
}
else
{
   echo json_encode(["error" => "file not loaded"]);
}

