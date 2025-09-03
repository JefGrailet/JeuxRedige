<?php

/**
* This script deletes an uploaded file which is given by $_POST.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../libraries/Buffer.lib.php';
require '../model/Post.class.php';
require '../model/Segment.class.php';

if(!LoggedUser::isLoggedIn())
{
   exit();
}
else if(!(array_key_exists('pseudo', LoggedUser::$data) && strlen(LoggedUser::$data['pseudo']) > 0))
{
   exit();
}

/*
* Auxiliary function to remove the deleted file from an attachment.
*
* @param mixed[] $attachArr  The attachment array
* @param string $fileName    The file to remove
*/

function removeFromAttachment(&$attachArr, $fileName)
{
   for($i = 0; $i < count($attachArr); $i++)
   {
      if(substr($attachArr[$i], 0, 7) === 'uploads')
      {
         $splitted = explode(':', $attachArr[$i]);
         $uploads = explode(',', $splitted[1]);
         
         $newUploads = array();
         for($j = 0; $j < count($uploads); $j++)
         {
            if($uploads[$j] !== $fileName)
               array_push($newUploads, $uploads[$j]);
         }
         
         if(count($newUploads) == 0)
         {
            $newAttachArr = array();
            for($j = 0; $j < count($attachArr); $j++)
            {
               if($j != $i)
                  array_push($newAttachArr, $attachArr[$j]);
            }
            
            $attachArr = $newAttachArr;
            continue;
         }
         
         $attachArr[$i] = $splitted[0].':'.implode(',', $newUploads);
         break;
      }
   }
}

/*
* Main script.
*/

if(!empty($_POST['fileToDelete']))
{
   $fileToDelete = Utils::secure($_POST['fileToDelete']);
   $pos = strpos($fileToDelete, "/upload");
   if($pos == FALSE)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'fail';
      exit();
   }
   
   $filePath = substr($fileToDelete, $pos + 1);
   
   // Cannot go on if the given file is actually a folder or something else
   $lastSlash = strrchr($filePath, "/");
   if(strlen($lastSlash) <= 1)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'fail';
      exit();
   }
   
   $fileName = substr($lastSlash, 1);
   $fileDir = substr($filePath, 0, -strlen($fileName));
   
   /*
    * We now check if the upload is from the user's buffer, from an existing post or from a 
    * segment, and that the user is allowed to delete it or not.
    */
   
   $deleteOk = false;
   $post = NULL;
   $segment = NULL;
   
   // Upload belongs to user's personal folder, so it is OK
   $detailedPath = explode('/', $fileDir);
   if($detailedPath[1] === 'tmp' && $detailedPath[2] === LoggedUser::$data['pseudo'])
   {
      $deleteOk = true;
   }
   // Parse IDs of topic and post to check if the post exists and belongs to the user
   else if($detailedPath[1] === 'topics')
   {
      $fileNameSplitted = explode('_', $fileName, 2);
      $fileName = $fileNameSplitted[1];
      
      $postID = intval($fileNameSplitted[0]);
      $topicID = intval($detailedPath[2]);
      
      try
      {
         $post = new Post($postID);
         $author = $post->get('author');
         $isAllowedToDelete = $author === LoggedUser::$data['pseudo'];
         $isAllowedToDelete = $isAllowedToDelete || ($author === LoggedUser::$data['used_pseudo'] && Utils::check(LoggedUser::$data['can_upload']));
         $isAllowedToDelete = $isAllowedToDelete || Utils::check(LoggedUser::$data['can_edit_all_posts']);
         if($post->get('id_topic') == $topicID && $isAllowedToDelete)
            $deleteOk = true;
      }
      catch(Exception $e) { }
   }
   // Parse IDs of the article and segment to check if the segment exists and belongs to the user
   else if($detailedPath[1] === 'articles')
   {
      $segmentID = intval($detailedPath[3]);
      $articleID = intval($detailedPath[2]);
      
      try
      {
         $segment = new Segment($segmentID);
         $author = $segment->get('pseudo');
         $isAllowedToDelete = $author === LoggedUser::$data['pseudo'];
         $isAllowedToDelete = $isAllowedToDelete || Utils::check(LoggedUser::$data['can_edit_all_posts']);
         if($segment->get('id_article') == $articleID && $isAllowedToDelete)
            $deleteOk = true;
      }
      catch(Exception $e) { }
   }
   
   if(!$deleteOk)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'fail';
      exit();
   }
   
   $wwwFilePath = PathHandler::WWW_PATH().$filePath;
   if(!file_exists($wwwFilePath))
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'does not exist';
      exit();
   }
   
   // If the upload is attached to a post, it is removed from the attachment
   if($post != NULL)
   {
      $attachArr = explode('|', $post->get('attachment'));
      removeFromAttachment($attachArr, $fileName);
      try
      {
         $post->finalize(implode('|', $attachArr));
      }
      catch(Exception $e)
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'fail';
         exit();
      }
   }
   else if($segment != NULL)
   {
      $attachArr = explode('|', $segment->get('attachment'));
      removeFromAttachment($attachArr, $fileName);
      try
      {
         $segment->finalize(implode('|', $attachArr));
      }
      catch(Exception $e)
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'fail';
         exit();
      }
   }
   
   // Checks if there is a miniature to delete it too (if it exists).
   $toMiniatureFileName = $fileName;
   $uploader = "";
   if($post != NULL) // Only for topic uploads
   {
      $explodedFileName = explode('_', $toMiniatureFileName, 2);
      $uploader = $explodedFileName[0];
      $toMiniatureFileName = $explodedFileName[1];
   }
   
   $prefix = substr($toMiniatureFileName, 0, 5);
   if($prefix === 'full_')
   {
      $miniatureName = 'mini_'.substr($toMiniatureFileName, 5);
      $wwwMiniaturePath = "";
      if($post != NULL) // Only for topic uploads
         $wwwMiniaturePath = PathHandler::WWW_PATH().$fileDir.$postID.'_'.$uploader.'_'.$miniatureName;
      else
         $wwwMiniaturePath = PathHandler::WWW_PATH().$fileDir.$miniatureName;
      
      if(file_exists($wwwMiniaturePath))
      {
         $success = unlink($wwwMiniaturePath);
         if(!$success)
         {
            header('Content-Type: text/html; charset=UTF-8');
            echo 'fail';
            exit();
         }
      }
   }
   
   $success = unlink($wwwFilePath);
   if(!$success)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'fail';
      exit();
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo 'ok';
}

?>
