<?php

/**
* Library to handle user's buffer. A "buffer" is a temporary directory found in upload/tmp/, named 
* after the user's pseudonym, which can contain a limited amount of uploaded files. These files 
* stay in the buffer until the user posts a message or modifies an article, in which case the 
* uploaded files are "committed" into another folder of the upload/ directory where they will stay 
* permanently. This system is both simple and allows users to keep uploaded files even after 
* refreshing the page or losing connection, or when they decide to cancel some message but decide 
* to re-use the uploads elsewhere.
*/

class Buffer
{
   /*
   * Checks that directory upload/tmp/ contains the sub-folder [pseudo]/ where [pseudo] is the
   * pseudonym of the currently logged user. Creates it if it does not exist, with CHMOD 0711. 
   * Nothing is returned.
   *
   * N.B.: assumes that the calling code previously checked the user is logged.
   */

   public static function create()
   {
      $tmpDir = PathHandler::WWW_PATH.'upload/tmp/';
      if(!is_dir($tmpDir))
         return;
      
      $dirPath = $tmpDir.LoggedUser::$data['pseudo'];
      if(!is_dir($dirPath))
         mkdir($dirPath, 0711);
   }

   /*
   * Lists the content of upload/tmp/[pseudo]/ if this directory exists. The content is given as 
   * an array of 2 cells containing:
   * -[0]: array containing the file names of the (full size) uploads
   * -[1]: array containing the file names of the miniatures (pictures) for these uploads
   * An empty array is returned if the folder is empty, non-existing or contains unlikely content 
   * (e.g. an additionnal sub-folder; in such a case, it does not list files for security reasons).
   */

   public static function listContent()
   {
      $tmpDir = PathHandler::WWW_PATH.'upload/tmp/';
      if(!is_dir($tmpDir))
         return array(array(), array());
      
      $dirPath = $tmpDir.LoggedUser::$data['pseudo'];
      if(!is_dir($dirPath))
         return array(array(), array());

      $fullSize = array();
      $miniatures = array();
      
      /*
       * Developer's note about method to list files:
       * At first, I relied on scandir(), but it proved to be a bit too slow. glob() seems to be the 
       * fastest method in this case (other approaches relied on PHP default classes, but none of 
       * them performed faster). Note that I also used to use "is_dir()" to be sure that a listed 
       * file is not a directory, but since users are not supposed to be able to create a subfolder 
       * (even in a non-direct fashion), I don't think it's needed here. Maybe I'll put it back if 
       * someones finds a way to exploit this for malicious intent.
       */
      
      foreach(glob($dirPath."/*.{*}", GLOB_BRACE) as $filePath)
      {
         $fileName = substr(strrchr($filePath, '/'), 1);
      
         // Skips './' and '../'
         if($fileName === '.' || $fileName === '..')
            continue;
         
         // Checks extension
         $ext = strtolower(substr(strrchr($fileName, '.'), 1));
         if(in_array($ext, Utils::UPLOAD_OPTIONS['extensions']))
         {
            // Checks that a miniature is needed
            if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
            {
               $prefix = substr($fileName, 0, 5);
               if($prefix === 'mini_')
                  array_push($miniatures, $fileName);
               else if($prefix === 'full_')
                  array_push($fullSize, $fileName);
            }
            // Otherwise, just inserts the file name in the full-size array
            else
            {
               array_push($fullSize, $fileName);
            }
         }
         else
            return array(array(), array());
      }
      
      return array($fullSize, $miniatures);
   }

   /*
   * Lists the miniatures for a given list of (full size) uploads and returns an array which 
   * format is identical to the output of listContent(). The goal is to format a list of uploads 
   * taken from the database (i.e. from a message) just like the output of listContent(), such 
   * that other methods of this class (like render()) can be re-used.
   *
   * @param string $uploads[]   An array listing only the full size uploads
   * @return mixed[]            An array consisting of: [0] the full size uploads and [1] the 
   *                            miniatures (if needed) that should be associated with them
   */

   public static function listMiniatures($uploads)
   {
      $miniatures = array();
      
      for($i = 0; $i < count($uploads); $i++)
      {
         // Checks extension
         $ext = strtolower(substr(strrchr($uploads[$i], '.'), 1));
         if(in_array($ext, Utils::UPLOAD_OPTIONS['extensions']))
         {
            if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
            {
               $exploded = explode('_', $uploads[$i], 2);
               // No uploader
               if($exploded[0] === 'full')
               {
                  array_push($miniatures, 'mini_'.$exploded[1]);
               }
               // Checks prefix besides the uploader (format is [uploader]_[full|mini]_file)
               else
               {
                  // Checks prefix 
                  $prefix = substr($exploded[1], 0, 5);
                  $withoutPrefix = substr($exploded[1], 5);
                  
                  // Prefix is checked just in case
                  if($prefix === 'full_')
                     array_push($miniatures, $exploded[0].'_mini_'.$withoutPrefix);
               }
            }
         }
      }

      return array($uploads, $miniatures);
   }

   /*
   * Renders the typical output of listContent() in HTML. It returns an empty string if this 
   * output is empty or faulty. It was made generic because there are different conventions for 
   * different parts of the website (path to uploads from topics is not the same as path to 
   * uploads attached to an article).
   *
   * @param mixed $uploadsList[]  A double array formatted like the output of listContent()
   * @param string $path          Path to the pictures in the upload/ folder (might also contain a 
   *                              prefix to the file name)
   * @param bool $uploaderPseudo  True if the uploader's pseudo is in the file name (convention to 
   *                              avoid playing with the metadata); false by default
   * @return string               The HTML code rendering the uploads
   */

   private static function renderGeneric($uploadsList, $path, $uploaderName = false)
   {
      $renderedUploads = '';
      if($uploadsList != NULL)
      {
         $fullInput = array();
         for($i = 0; $i < count($uploadsList[0]); $i++)
         {
            /*
             * Depending on whether uploads are in user's buffer or not, one has to be careful 
             * with the "uploader" part of the naming convention for publicly available uploads.
             */
            
            $uploader = LoggedUser::$data['used_pseudo'];
            $upload = $uploadsList[0][$i];
            
            if($uploaderName)
            {
               $exploded = explode('_', $uploadsList[0][$i], 2);
               $uploader = $exploded[0];
               $upload = $exploded[1];
            }
            
            // Checks the extension
            $ext = strtolower(substr(strrchr($upload, '.'), 1));
            
            // Miniature of the uploaded file should exist in this case
            if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
            {
               $prefix = substr($upload, 0, 5);
               if($prefix === 'full_')
               {
                  $miniature = 'mini_'.substr($upload, 5);
                  if($uploaderName)
                     $miniature = $uploader.'_'.$miniature;
                  
                  if(in_array($miniature, $uploadsList[1]))
                  {
                     $httpPathPrefix = PathHandler::HTTP_PATH.'upload/'.$path;
                     $wwwPathPrefix = PathHandler::WWW_PATH.'upload/'.$path;
                     $relativePrefix = 'upload/'.$path;
                     
                     // Security (avoids disgracious errors)
                     if(!file_exists($wwwPathPrefix.$uploadsList[0][$i]))
                        continue;
                     
                     $dimFull = getimagesize($wwwPathPrefix.$uploadsList[0][$i]);
                     $dimMini = getimagesize($wwwPathPrefix.$miniature);
                     $deleteButton = Utils::check(LoggedUser::$data['can_upload']) ? 'yes' : '';
                     
                     $tplInput = array('fullSize' => $httpPathPrefix.$uploadsList[0][$i], 
                     'dimensions' => 'yes||'.$dimFull[0].'|'.$dimFull[1], 
                     'uploader' => $uploader, 
                     'uploadDate' => date('d/m/Y à H:i:s', filemtime($wwwPathPrefix.$uploadsList[0][$i])), 
                     'fullSizeRelative' => $relativePrefix.$uploadsList[0][$i],
                     'delete' => $deleteButton, 
                     'content' => 'picture||'.$httpPathPrefix.$miniature.'|'.$dimMini[0].'|'.$dimMini[1]);
                     
                     array_push($fullInput, $tplInput);
                  }
               }
            }
            // For other formats (video), there is neither a miniature, neither a prefix to consider
            else
            {
               $httpPathPrefix = PathHandler::HTTP_PATH.'upload/'.$path;
               $wwwPathPrefix = PathHandler::WWW_PATH.'upload/'.$path;
               $relativePrefix = 'upload/'.$path;
               
               // Security (avoids disgracious errors)
               if(!file_exists($wwwPathPrefix.$uploadsList[0][$i]))
                  continue;
               
               $deleteButton = Utils::check(LoggedUser::$data['can_upload']) ? 'yes' : '';
                     
               $tplInput = array('fullSize' => $httpPathPrefix.$uploadsList[0][$i], 
               'dimensions' => '', 
               'uploader' => $uploader, 
               'uploadDate' => date('d/m/Y à H:i:s', filemtime($wwwPathPrefix.$uploadsList[0][$i])), 
               'fullSizeRelative' => $relativePrefix.$uploadsList[0][$i],
               'delete' => $deleteButton, 
               'content' => 'video||'.$httpPathPrefix.$uploadsList[0][$i].'|'.$ext);
               
               array_push($fullInput, $tplInput);
            }
         }
         
         if(count($fullInput) > 0)
         {
            $tplOutput = TemplateEngine::parseMultiple('view/content/Upload.item.edition.ctpl', $fullInput);
            if(!TemplateEngine::hasFailed($tplOutput))
            {
               for($i = 0; $i < count($tplOutput); $i++)
                  $renderedUploads .= $tplOutput[$i];
            }
            else
               $renderedUploads = 'fail';
         }
      }
      
      return $renderedUploads;
   }

   /*
   * Renders the typical output of listContent() in HTML for uploads found in topics.
   *
   * @param mixed $uploadsList[]  A double array formatted like the output of listContent()
   * @param integer $topicID      ID of the topic where the files were uploaded (optional, if = 0 
   *                              we look into user's buffer)
   * @param integer $postID       ID of the post to which the files were attached (optional too)
   * @return string               The HTML code rendering the uploads
   */

   public static function render($uploadsList, $topicID = 0, $postID = 0)
   {
      $pathInUploadFolder = '';
      $hasUploaderName = false;
      if($topicID != 0)
      {
         $pathInUploadFolder = 'topics/'.$topicID.'/'.$postID.'_';
         $hasUploaderName = true;
      }
      else
         $pathInUploadFolder = 'tmp/'.LoggedUser::$data['pseudo'].'/';
      
      return self::renderGeneric($uploadsList, $pathInUploadFolder, $hasUploaderName);
   }

   /*
   * Renders the typical output of listContent() in HTML for uploads found in articles.
   *
   * @param mixed $uploadsList[]  A double array formatted like the output of listContent()
   * @param integer $articleID    ID of the article where the files were uploaded (optional, if 
   *                              = 0 we look into user's buffer)
   * @param integer $segmentID    ID of the segment to which the files were attached (optional too)
   * @return string               The HTML code rendering the uploads
   */

   public static function renderForSegment($uploadsList, $articleID = 0, $segmentID = 0)
   {
      $pathInUploadFolder = '';
      if($articleID == 0)
         $pathInUploadFolder = 'tmp/'.LoggedUser::$data['pseudo'].'/';
      else
         $pathInUploadFolder = 'articles/'.$articleID.'/'.$segmentID.'/';
      
      return self::renderGeneric($uploadsList, $pathInUploadFolder);
   }

   /*
   * Moves one file located at upload/tmp/[pseudo]/ to $dir/ in order to permanently saves it. 
   * Optionnaly, the calling code can give a new name to that file.
   *
   * N.B.: just like makeBuffer(), assumes the calling code checked that the user is logged in.
   *
   * @param string $dir   The directory where the temporary file should be permanently saved
   * @param string $file  The file to save (without path because located in the buffer)
   * @param string $name  The new name of the file being permanently saved (optional)
   * @return bool         True if the file has been successfully "saved" (actually, it is just 
   *                      moved) and false in case of failure
   */

   public static function save($dir, $file, $name = "")
   {
      $filePath = PathHandler::WWW_PATH.'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$file;
      if(!file_exists($filePath))
         return FALSE;
      
      $newFilePath = "";
      if($name !== "")
      {
         $extension = strrchr($file, '.');
         $newFilePath = PathHandler::WWW_PATH.$dir.'/'.$name.$extension;
      }
      else
         $newFilePath = PathHandler::WWW_PATH.$dir.$file;
         
      return rename($filePath, $newFilePath);
   }

   /*
   * Generic function to save the whole user's buffer, such that the code can be re-used for 
   * saveInTopic() (for posts) and saveInSegment() (for articles).
   *
   * @param string $uploads[]   Typical output of listContent()
   * @param string $folder      Part to append to upload/
   * @param string $prefix      Part to prepend to the file name (actual file name)
   * @param string $prefixBis   Part to prepend to the file name (for the list in the DB)
   * @param integer $max        If non zero, the maximum amount of pictures which will be saved 
   *                            (optional) (must be positive)
   * @return string             The list of the files that have been saved (empty string if 
   *                            nothing has been saved
   */

   private static function saveBufferGeneric($uploads, $folder, $prefix, $prefixBis, $max = 0)
   {
      $limit = Utils::UPLOAD_OPTIONS['bufferLimit'];
      if($max > 0 && $max < Utils::UPLOAD_OPTIONS['bufferLimit'])
         $limit = $max;
      
      $guardian = false;
      $uploadsToStr = '';
      for($i = 0; $i < count($uploads[0]) && $i < $limit; $i++)
      {
         $fileName = $uploads[0][$i];
         
         $fileNameWithoutExt = substr($fileName, 0, strrpos($fileName, '.'));
         $finalName = $prefix.$fileNameWithoutExt;
         if(self::save('upload/'.$folder, $fileName, $finalName))
         {
            if($guardian)
               $uploadsToStr .= ',';
            else
               $guardian = true;
            $uploadsToStr .= $prefixBis.$fileName;
            
            // Saves miniature if it exists
            $extension = strtolower(substr(strrchr($fileName, '.'), 1));
            if(in_array($extension, Utils::UPLOAD_OPTIONS['miniExtensions']))
            {
               $miniName = $prefix.'mini_'.substr($fileNameWithoutExt, 5);
               $miniFullName = 'mini_'.substr($fileName, 5);
               if(in_array($miniFullName, $uploads[1]))
               {
                  self::save('upload/'.$folder, $miniFullName, $miniName);
               }
            }
         }
      }
      
      return $uploadsToStr;
   }

   /*
   * Moves user's whole buffer into the directory of a given topic, with all files being prefixed 
   * with the ID of the post (+ pseudonym of the user).
   *
   * @param string  $uploads[] Typical output of listContent()
   * @param integer $topicID   ID of the topic for which these uploads are saved
   * @param integer $postID    ID of the post for which these uploads are saved
   * @param bool $truePseudo   True if the script should ignore the pseudonym being used and use 
   *                           the main pseudonym to label the uploaded files (optional)
   * @param integer $max       If non zero, the maximum amount of pictures which will be saved 
   *                           (optional) (must be positive)
   * @return string            The list of the files that have been saved (empty string if nothing 
   *                           has been saved
   */

   public static function saveInTopic($uploads, $topicID, $postID, $truePseudo = false, $max = 0)
   {
      $uploaderPseudo = LoggedUser::$data['used_pseudo'];
      if($truePseudo)
         $uploaderPseudo = LoggedUser::$data['pseudo'];
      
      return self::saveBufferGeneric($uploads, 'topics/'.$topicID, $postID.'_'.$uploaderPseudo.'_', $uploaderPseudo.'_', $max);
   }

   /*
   * Accomplishes more or less the same task but for a segment, therefore slightly simplified.
   *
   * @param string  $uploads[]  Typical output of listContent()
   * @param integer $articleID  ID of the article for which these uploads are saved
   * @param integer $segmentID  ID of the segment for which these uploads are saved
   * @param integer $max        If non zero, the maximum amount of pictures which will be saved 
   *                            (optional) (must be positive)
   * @return string             The list of the files that have been saved (empty string if nothing 
   *                            has been saved
   */

   public static function saveInSegment($uploads, $articleID, $segmentID, $max = 0)
   {
      return self::saveBufferGeneric($uploads, 'articles/'.$articleID.'/'.$segmentID, '', '', $max);
   }

   /*
   * Generic method to get a particular file in user's buffer, provided a prefix for the file 
   * name. I.e., if the user created a thumbnail but did not submit the topic, the thumbnail is 
   * still in his/her buffer and can be used elsewhere if (s)he wishes to. The single parameter 
   * is for said prefix.
   *
   * @param string $prefix  Prefix of the file to retrieve (e.g.: thumbnail_, header_)
   * @return string         Path to the first file featuring this prefix, or empty string if there 
   *                        is no such file buffer.
   */

   private static function getByPrefix($prefix)
   {
      $tmpDir = PathHandler::WWW_PATH.'upload/tmp/';
      if(!is_dir($tmpDir))
         return;
      
      $dirPath = $tmpDir.LoggedUser::$data['pseudo'];
      if(!is_dir($dirPath))
         return;
      
      foreach(glob($dirPath."/".$prefix."*.{*}", GLOB_BRACE) as $filePath)
      {
         $withHttpPath = PathHandler::HTTP_PATH.substr($filePath, strlen(PathHandler::WWW_PATH));
         return $withHttpPath;
      }
      
      return "";
   }

   /*
   * Next methods are just public applications of getByPrefix().
   */

   public static function getThumbnail() { return self::getByPrefix('thumbnail_'); }
   public static function getArticleThumbnail() { return self::getByPrefix('article_thumbnail_'); }
   public static function getSegmentHeader() { return self::getByPrefix('header_'); }
   public static function getHighlight() { return self::getByPrefix('highlight_'); }
   public static function getTropeIcon() { return self::getByPrefix('icon_'); }

   /*
   * Generic method to remove specific files from user's buffer, provided a prefix for the file 
   * name. The principle and usage is the same as getByPrefix().
   *
   * @param string $prefix  Prefix of the file to remove (e.g.: thumbnail_, header_)
   */

   private static function cleanByPrefix($prefix)
   {
      $tmpDir = PathHandler::WWW_PATH.'upload/tmp/';
      if(!is_dir($tmpDir))
         return;
      
      $dirPath = $tmpDir.LoggedUser::$data['pseudo'];
      if(!is_dir($dirPath))
         return;
      
      foreach(glob($dirPath."/".$prefix."*.{*}", GLOB_BRACE) as $filePath)
      {
         unlink($filePath);
      }
   }

   /*
   * Next methods are just public applications of cleanByPrefix().
   */

   public static function cleanThumbnails() { self::cleanByPrefix('thumbnail_'); }
   public static function cleanArticleThumbnails() { self::cleanByPrefix('article_thumbnail_'); }
   public static function cleanSegmentHeaders() { self::cleanByPrefix('header_'); }
   public static function cleanHighlights() { self::cleanByPrefix('highlight_'); }
   public static function cleanTropeIcons() { self::cleanByPrefix('icon_'); }
}
?>
