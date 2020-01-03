<?php

/**
* This library defines handles user uploads (pictures and short clips so far). Not to confuse with 
* the "management" library, on the other side, which purpose is to handle the various folders used 
* to store uploads temporarily (user's buffer) or permanently.
*/

require realpath(dirname(__FILE__)."/external/GIFResizer.class.php");

class Upload
{
   /*
   * Gives the size (in octets) of a given directory where files should be uploaded. Returns -1 if 
   * the path does not correspond to a directory. The given path should also be relative to the 
   * root directory.
   *
   * @param string $dir  The path to a directory to evaluate
   * @return number      The size of this directory in octets or -1 if this is not a directory
   */

   public static function directorySize($dir)
   {
      $dirPath = PathHandler::WWW_PATH().$dir;

      $size = -1;
      if(is_dir($dirPath))
      {
         $size = 0;
         foreach(glob($dirPath . '/*.*') as $file)
         {
           $size += filesize($file);
         }
      }
      return $size;
   }

   /*
   * Stores a file given as an array (assumed to be obtained from a global $_FILES) in given 
   * directory, possibly with a new name. The function also checks that the file name is not 
   * already taken (if yes, it will generate a slightly edited name until storage is possible) and 
   * that the submitted file is in a supported format (for now, pictures and short video clips).
   *
   * @param mixed $arr[]  The array containing the picture (previously a $_FILES global)
   * @param string $dir   The path to the directory where this picture must be stored
   * @param string $name  The new name of the uploaded file after storage (optional)
   * @return string       The path to the stored picture (relative to the root directory) or an 
   *                      empty string if the format of the file is not supported
   */

   public static function storeFile($arr, $dir, $name = "")
   {
      $dirPath = PathHandler::WWW_PATH().$dir;
      
      $chains = explode(".", $arr['name']);
      $fileName = $chains[0];
      $extension = strtolower($chains[1]);

      if(!in_array($extension, Utils::UPLOAD_OPTIONS['extensions']))
         return "";
      
      if($extension === 'jpeg')
         $extension = 'jpg';

      if(strlen($name) == 0)
      {
         $name = $fileName;
         $name = PathHandler::formatForFilesystem($name);
         while(file_exists($dirPath .'/'. $name .'.'. $extension))
         {
            $uniqueSeq = substr(md5(uniqid(rand(), true)), 0, 5);
            $name = $fileName .'-'. $uniqueSeq;
         }
      }
      
      $filePath = $dirPath .'/'. $name .'.'. $extension;
      if(move_uploaded_file($arr['tmp_name'], $filePath))
         return $filePath;
      return "";
   }

   /*
   * Stores a picture in a similar fashion than that of storeFile(), but also resizes it. Two 
   * additionnal parameters are required and determine the size of the miniature. The policy is 
   * the following :
   *
   * -If $h = 0, $w is the new size of the greatest dimension of the picture (the other dimension 
   *  is resized with respect to the original proportions of the picture)
   * -If $h > 0, the function will select the greatest zone inside the image that respects the 
   *  proportions of an image of $w x $h pixels and resize it
   *
   * @param mixed $arr[]  The array containing the picture (previously a $_FILES global)
   * @param string $dir   The path to the directory where this picture must be stored
   * @param number $w     The width of the miniature OR the new size of the greatest dimension
   * @param number $h     The height of the miniature (= 0 for the first case)
   * @param string $name  The new name of the uploaded file after storage (optional)
   * @return string       The path to the stored picture (relative to the root directory) or an 
   *                      empty string if the file is not a JPEG/GIF/PNG, if $w or $h are negative 
   *                      or if the creation of the miniature with imagecopyresampled() fails
   */

   public static function storeResizedPicture($arr, $dir, $w, $h, $name = "")
   {
      $dirPath = PathHandler::WWW_PATH().$dir;

      if($w < 0 || $h < 0)
         return "";

      $chains = explode(".", $arr['name']);
      $fileName = $chains[0];
      $extension = strtolower($chains[1]);

      $validExtensions = array('jpg', 'jpeg', 'gif', 'png');
      if(!in_array($extension, $validExtensions))
         return "";
      
      if($extension === 'jpeg')
         $extension = 'jpg';
      
      if(strlen($name) == 0)
      {
         $name = $fileName;
         $name = PathHandler::formatForFilesystem($name);
         while(file_exists($dirPath .'/'. $name .'.'. $extension))
         {
            $uniqueSeq = substr(md5(uniqid(rand(), true)), 0, 5);
            $name = $fileName .'-'. $uniqueSeq;
         }
      }
      
      // Computing the new dimensions of the picture
      $dim = getimagesize($arr['tmp_name']);
      $newW = 0;
      $newH = 0;
      $wBestFit = 0;
      $hBestFit = 0;
      $padding = array(0, 0); // Padding on x/y axes, respectively, in the original picture
      if($h == 0)
      {
         $wBestFit = $dim[0];
         $hBestFit = $dim[1];
         if($dim[0] >= $dim[1])
         {
            $newW = $w;
            $newH = ($w / $dim[0]) * $dim[1];
         }
         else
         {
            $newH = $w;
            $newW = ($w / $dim[1]) * $dim[0];
         }
      }
      else
      {
         $newW = $w;
         $newH = $h;
         
         // Computes the zone that fits best the proportions of the miniature inside the original
         $ratioW = $dim[0] / $newW;
         $ratioH = $dim[1] / $newH;

         if($ratioW > $ratioH)
         {
            $hBestFit = $dim[1];
            $wBestFit = ($dim[1] / $newH) * $newW;
            $padding[0] = ceil(($dim[0] - $wBestFit) / 2);
         }
         else
         {
            $wBestFit = $dim[0];
            $hBestFit = ($dim[0] / $newW) * $newH;
            $padding[1] = ceil(($dim[1] - $hBestFit) / 2);
         }
      }
      
      // The creation of the miniature starts here
      $finalFilePath = $dirPath .'/'. $name .'.'. $extension;
      
      /*
       * GIF pictures are a particular case, as they consist of several frames (usually, since GIFs 
       * are most of the time animated). To handle this case, the class GIFResizer (obtained from 
       * the website PHPClasses) is being used.
       */
      
      if($extension === 'gif')
      {
         $gr = new GIFResizer;
         $gr->temp_dir = $dirPath;
         $gr->resize($arr['tmp_name'], $finalFilePath, $newW, $newH);
      }
      // For PNG image, a transparent background must be created.
      else if($extension === 'png')
      {
         $original = @imagecreatefrompng($arr['tmp_name']);
         if($original == FALSE)
            return "";
         
         $resized = imagecreatetruecolor($newW, $newH);
         imagealphablending($resized, false);
         imagesavealpha($resized, true);
         $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
         imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
         
         if(!imagecopyresampled($resized, $original, 0, 0, $padding[0], $padding[1], $newW, $newH, $wBestFit, $hBestFit))
            return "";
         
         imagepng($resized, $finalFilePath);
      }
      // Regular JP(E)G resizing.
      else
      {
         $original = @imagecreatefromjpeg($arr['tmp_name']);
         if($original == FALSE)
            return "";

         $resized = imagecreatetruecolor($newW, $newH);
         if(!imagecopyresampled($resized, $original, 0, 0, $padding[0], $padding[1], $newW, $newH, $wBestFit, $hBestFit))
            return "";
      
         imagejpeg($resized, $finalFilePath, 100);
      }
      
      return $finalFilePath;
   }
}

?>
