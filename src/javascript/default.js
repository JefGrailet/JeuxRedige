/**
* This file contains JavaScript functions which should be present on every page of the site (hence 
* the name "default.js").
*/

// Defines the namespace of this script and its "globals"
var DefaultLib = {};
DefaultLib.httpPath = "http://localhost/pag/";
DefaultLib.blockKeypresses = false; // Prevents issues with the lightbox by pressing keys multiple times
DefaultLib.usingAJAX = false; // Used to prevent multiple AJAX requests in some cases (e.g. upvote)

/*
* Next functions open/close a dialog. For the open function, the ID is given by the "target" variable.
*/

DefaultLib.openDialog = function(target)
{
   $('#blackScreen').fadeIn(200);
   $('#blackScreen').click(function(e) { DefaultLib.closeDialog(); });
   $(target).fadeIn(450);
}

DefaultLib.closeDialog = function()
{
   $('#blackScreen').fadeOut(200);
   $('.window:visible').fadeOut(450);
}

// Variant of closeDialog to complete an operation on the target after the last fadeOut.

DefaultLib.closeAndUpdateDialog = function(operation)
{
   $('#blackScreen').fadeOut(200);
   $('.window:visible').fadeOut(450).promise().done(operation);
}

// addslashes() equivalent (source: http://locutus.io/php/strings/addslashes/)

DefaultLib.addslashes = function(inputString)
{
   return (inputString + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

/*
 * Handles the lightbox before being displayed, in a simple case (that is, without slideshow and 
 * additionnal details such as the uploader's pseudonym).
 *
 * @param string ext      Extension of the file
 * @param string file     The path to the uploaded file being displayed (picture or video)
 * @param int    width    The full width (in pixels) of the item being displayed
 * @param int    height   The full height (in pixels) of the item being displayed
 * @param string comment  Optional comment (can be empty)
 */

DefaultLib.updateLightboxSimple = function(ext, file, width, height, comment)
{
   var isVideo = (ext == 'mp4' || ext == 'webm');
   var resized = false;
   var greatestDim = width;
   var ratio = 0.0;
   if(height > greatestDim)
   {
      greatestDim = height;
      ratio = width / height;
   }
   else
      ratio = height / width;
   
   // First comes the selection of a threshold for resizing (depends on user's screen)
   var resizeThreshold = 1000; // Default
   if(width >= height)
   {
      if(screen.width > 1980)
         resizeThreshold = 2000;
      else if(screen.width > 1300)
         resizeThreshold = 1300;
   }
   else
   {
      if(screen.height > 1080)
         resizeThreshold = 800;
      else if(screen.height > 768)
         resizeThreshold = 650;
      else
         resizeThreshold = 550;
   }
   if(ratio > 0.7)
      resizeThreshold *= 0.6;
   
   // If necessary, new dimensions are computed
   if(greatestDim > resizeThreshold)
   {
      if(width > height)
      {
         height = (resizeThreshold / width) * height;
         width = resizeThreshold;
      }
      else
      {
         width = (resizeThreshold / height) * width;
         height = resizeThreshold;
      }
      resized = true;
   }
   
   // The content of the lightbox is updated
   var media = "";
   if(isVideo)
   {
      media = "<video width=\"" + width + "\" height=\"" + height + "\" ";
      media += "muted=\"true\" controls autoplay>\n";
      media += "<source src=\"" + file + "\" format=\"video/" + ext + "\">";
      media += "</video";
   }
   else
   {
      media = '<img src="' + file + '" alt="Aperçu" ';
      media += 'width="' + width + '" height="' + height + '" />';
   }
   $('#lightbox .lightboxContent').html(media);
   
   // Then, lightbox can be resized properly
   var newLightboxWidth = width + 10;
   var newLightboxHeight = height + 50;
   
   // Default size/positioning
   var editedStyle = 'width: 500px; ';
   editedStyle += 'margin-left: -250px; ';
   editedStyle += 'margin-top: -' + (newLightboxHeight / 2).toString() + 'px;';
   
   // Resizing/positioning if the window is big enough
   if(width > 490)
   {
      editedStyle = 'width: ' + newLightboxWidth.toString() + 'px; ';
      editedStyle += 'margin-left: -' + (newLightboxWidth / 2).toString() + 'px; ';
      editedStyle += 'margin-top: -' + (newLightboxHeight / 2).toString() + 'px;';
   }
   
   $('#lightbox').attr('style', editedStyle);
   $('#lightbox').attr('data-cur-file', file);
   
   // "Legend" of the picture (insertion code or comment + link to full size, if necessary)
   var legend = '';
   if(comment !== '')
   {
      legend += comment;
   }
   else
   {
      var explodedLink = file.split('upload/');
      legend += 'upload/' + explodedLink[1];
   }
   if(resized)
   {
      if(isVideo)
         legend += ' (<a href="' + file + '" target="blank">Voir dans un nouvel onglet</a>)';
      else
         legend += ' (<a href="' + file + '" target="blank">Taille réelle</a>)';
   }
   $('#lightbox .lightboxBottom .LBCenter').html('<p>' + legend + '</p>');
}

/*
 * Handles the lightbox before being displayed, in the case where the lightbox is enriched with 
 * data about the uploader and upload date and a slide show.
 *
 * @param string ext         Extension of the file
 * @param string file        The path to the uploaded file being displayed (picture or video)
 * @param int    width       The full width (in pixels) of the item being displayed
 * @param int    height      The full height (in pixels) of the item being displayed
 * @param string uploader    The uploader's pseudonym
 * @param string uploadDate  The date at which the file was uploaded
 */

DefaultLib.updateLightboxDetailed = function(ext, file, width, height, uploader, uploadDate)
{
   var isVideo = (ext == 'mp4' || ext == 'webm');
   var resized = false;
   var greatestDim = width;
   var ratio = 0.0;
   if(height > greatestDim)
   {
      greatestDim = height;
      ratio = width / height;
   }
   else
      ratio = height / width;
   
   // First comes the selection of a threshold for resizing (depends on user's screen)
   var resizeThreshold = 1000; // Default
   if(width >= height)
   {
      if(screen.width > 1920)
         resizeThreshold = 1700;
      else if(screen.width > 1500)
         resizeThreshold = 1250;
   }
   else
   {
      if(screen.height > 1080)
         resizeThreshold = 800;
      else if(screen.height > 768)
         resizeThreshold = 650;
      else
         resizeThreshold = 550;
   }
   if(ratio > 0.7)
      resizeThreshold *= 0.6;
   
   // If necessary, new dimensions are computed
   if(greatestDim > resizeThreshold)
   {
      if(width > height)
      {
         height = (resizeThreshold / width) * height;
         width = resizeThreshold;
      }
      else
      {
         width = (resizeThreshold / height) * width;
         height = resizeThreshold;
      }
      resized = true;
   }
   
   // The content of the lightbox is updated
   var media = "";
   if(isVideo)
   {
      media = "<video width=\"" + width + "\" height=\"" + height + "\" ";
      media += "muted=\"true\" controls autoplay>\n";
      media += "<source src=\"" + file + "\" format=\"video/" + ext + "\">";
      media += "</video";
   }
   else
   {
      media = '<img src="' + file + '" alt="Aperçu" ';
      media += 'width="' + width + '" height="' + height + '" />';
   }
   $('#lightbox .lightboxContent').html(media);
   
   // Then, lightbox can be resized properly
   var newLightboxWidth = width + 10;
   var newLightboxHeight = height + 50;
   
   // Default size/positioning
   var editedStyle = 'width: 500px; ';
   editedStyle += 'margin-left: -250px; ';
   editedStyle += 'margin-top: -' + (newLightboxHeight / 2).toString() + 'px;';
   
   // Resizing/positioning if the window is big enough
   if(width > 490)
   {
      editedStyle = 'width: ' + newLightboxWidth.toString() + 'px; ';
      editedStyle += 'margin-left: -' + (newLightboxWidth / 2).toString() + 'px; ';
      editedStyle += 'margin-top: -' + (newLightboxHeight / 2).toString() + 'px;';
   }
   
   $('#lightbox').attr('style', editedStyle);
   $('#lightbox').attr('data-cur-file', file);
   
   // "Legend" of the picture (author + link to full size if needed)
   var legend = 'Uploadé par ' + uploader + ' le ' + uploadDate;
   if(resized)
   {
      if(isVideo)
         legend += ' (<a href="' + file + '" target="blank">Voir dans un nouvel onglet</a>)';
      else
         legend += ' (<a href="' + file + '" target="blank">Taille réelle</a>)';
   }
   
   $('#lightbox .lightboxBottom .LBCenter').html('<p>' + legend + '</p>');
   
   // Next and previous buttons (only for "uploadDisplay" blocks)
   var correspondingDiv = $('.uploadDisplay[data-file="' + file + '"]');
   if(correspondingDiv.length > 0)
   {
      var IDComponents = correspondingDiv.attr('id').split('_');
      var thisFileID = parseInt(IDComponents[2]);
      
      // Linking multiple galleries with the "data-slideshow-" attributes (for Uploads.php)
      var previousPost = IDComponents[1];
      if(correspondingDiv.attr('data-slideshow-previous'))
         previousPost = correspondingDiv.attr('data-slideshow-previous');
      var nextPost = IDComponents[1];
      if(correspondingDiv.attr('data-slideshow-next'))
         nextPost = correspondingDiv.attr('data-slideshow-next');
      
      var prefixPrevious = '.uploadDisplay[id="' + IDComponents[0] + '_' + previousPost + '_';
      var prefixNext = '.uploadDisplay[id="' + IDComponents[0] + '_' + nextPost + '_';
      
      if($(prefixPrevious + (thisFileID - 1).toString() + '"]').length > 0)
      {
         var previousButton = '<p><a href="#lightbox">Précédent</a></p>';
         $('#lightbox .lightboxBottom .LBLeft').html(previousButton);
         $('#lightbox .lightboxBottom .LBLeft a').on('click', function() { DefaultLib.slideShow(true); });
      }
      else
      {
         $('#lightbox .lightboxBottom .LBLeft').html('');
      }
      
      if($(prefixNext + (thisFileID + 1).toString() + '"]').length > 0)
      {
         var nextButton = '<p><a href="#lightbox">Suivant</a></p>';
         $('#lightbox .lightboxBottom .LBRight').html(nextButton);
         $('#lightbox .lightboxBottom .LBRight a').on('click', function() { DefaultLib.slideShow(false); });
      }
      else
      {
         $('#lightbox .lightboxBottom .LBRight').html('');
      }
   }
}

DefaultLib.showUpload = function(uploadBlock)
{
   if(!(uploadBlock.length))
      return;

   if(DefaultLib.blockKeypresses)
      return;

   /*
    * "uploadBlock" is the block containing the upload (picture or video) to display. The way the 
    * upload (which the path is found in field "data-file" of uploadBlock) will be displayed 
    * depends on the type of block we got. So far, there are 3 possible classes: uploadDisplay, 
    * uploadView and miniature).
    */
   
   var slideShow = true;
   var filePath = uploadBlock.attr('data-file');
   if(filePath.length == 0)
      return;
   
   // No slide show in this case
   if(uploadBlock.attr('class') == 'miniature')
      slideShow = false;
      
   // Checking the extension: upload treatment is not the same for videos and pictures
   var ext = filePath.substr((filePath.lastIndexOf('.') + 1)).toLowerCase();
   
   if(ext == 'webm' || ext == 'mp4')
   {
      // Already puts the video in the lightbox, because we need to load metadata to adjust size.
      var media = "<video muted=\"true\" controls autoplay>\n";
      media += "<source src=\"" + filePath + "\" format=\"video/" + ext + "\">";
      media += "</video";
      
      $('#lightbox .lightboxContent').html(media);
      $('#lightbox .lightboxContent video').bind("loadedmetadata", function ()
      {
         var width = this.videoWidth;
         var height = this.videoHeight;
         
         // Then only, one can properly display the video.
         if(slideShow)
         {
            var uploader = uploadBlock.attr('data-uploader');
            var uploadDate = uploadBlock.attr('data-upload-date');
            
            DefaultLib.updateLightboxDetailed(ext, filePath, width, height, uploader, uploadDate);
         }
         else
         {
            var commentAttr = uploadBlock.attr('data-comment');
            var comment = '';
            if(typeof commentAttr !== typeof undefined && commentAttr !== false)
               comment = commentAttr;
            
            DefaultLib.updateLightboxSimple(ext, filePath, width, height, comment);
         }
         
         $('#blackScreen').fadeIn(500);
         $('#blackScreen').click(function(e) { DefaultLib.stopShowingLightbox(); });
         $('#lightbox').fadeIn(1000);
      });
   }
   // For pictures: the dimensions are already available
   else
   {
      var width = parseInt(uploadBlock.attr('data-width'));
      var height = parseInt(uploadBlock.attr('data-height'));
      if(slideShow)
      {
         var uploader = uploadBlock.attr('data-uploader');
         var uploadDate = uploadBlock.attr('data-upload-date');
         
         DefaultLib.updateLightboxDetailed(ext, filePath, width, height, uploader, uploadDate);
      }
      else
      {
         var commentAttr = uploadBlock.attr('data-comment');
         var comment = '';
         if(typeof commentAttr !== typeof undefined && commentAttr !== false)
            comment = commentAttr;
      
         DefaultLib.updateLightboxSimple(ext, filePath, width, height, comment);
      }

      $('#blackScreen').fadeIn(500);
      $('#blackScreen').click(function(e) { DefaultLib.stopShowingLightbox(); });
      $('#lightbox').fadeIn(1000);
   }
}

DefaultLib.slideShow = function(previousOrNext)
{
   DefaultLib.blockKeypresses = true;

   // Checking the lightbox is being shown
   if($('#lightbox .lightboxContent').html().length == 0)
   {
      DefaultLib.blockKeypresses = false;
      return;
   }
   
   // Checks there is indeed a block corresponding to the current file
   var curFile = $('#lightbox').attr('data-cur-file');
   var correspondingDiv = $('.uploadDisplay[data-file="' + curFile + '"]');
   if(correspondingDiv.length == 0)
   {
      DefaultLib.blockKeypresses = false;
      return;
   }
   
   // Computes what should be the id attribute of the previous/next picture
   var IDComponents = correspondingDiv.attr('id').split('_');
   var thisPictureID = parseInt(IDComponents[2]);
   var component = '.uploadDisplay[id="' + IDComponents[0] + '_';
   if(previousOrNext)
   {
      if(correspondingDiv.attr('data-slideshow-previous'))
         component += correspondingDiv.attr('data-slideshow-previous');
      else
         component += IDComponents[1];
      component += '_' + (thisPictureID - 1).toString() + '"]';
   }
   else
   {
      if(correspondingDiv.attr('data-slideshow-next'))
         component += correspondingDiv.attr('data-slideshow-next');
      else
         component += IDComponents[1];
      component += '_' + (thisPictureID + 1).toString() + '"]';
   }
   
   // Retrieves the data of the picture to show
   var newFilePath = "";
   if($(component).length > 0)
   {
      newFilePath = $(component).attr('data-file');
      
      // The extension of the next file is checked to do the proper update
      var ext = newFilePath.substr((newFilePath.lastIndexOf('.') + 1)).toLowerCase();
      if(ext == 'webm' || ext == 'mp4')
      {
         $('#lightbox').fadeOut(300, function()
         {
            var media = "<video muted=\"true\" controls autoplay>\n";
            media += "<source src=\"" + newFilePath + "\" format=\"video/" + ext + "\">";
            media += "</video";
            
            $('#lightbox .lightboxContent').html(media);
            $('#lightbox .lightboxContent video').bind("loadedmetadata", function ()
            {
               var newWidth = this.videoWidth;
               var newHeight = this.videoHeight;
               var newUploader = $(component).attr('data-uploader');
               var newUploadDate = $(component).attr('data-upload-date');
               
               DefaultLib.updateLightboxDetailed(ext, newFilePath, newWidth, newHeight, newUploader, newUploadDate);
               $('#lightbox').fadeIn(300, function() { DefaultLib.blockKeypresses = false; });
            });
         });
      }
      else
      {
         var newWidth = parseInt($(component).attr('data-width'));
         var newHeight = parseInt($(component).attr('data-height'));
         var newUploader = $(component).attr('data-uploader');
         var newUploadDate = $(component).attr('data-upload-date');
         
         DefaultLib.updateLightboxDetailed(ext, newFilePath, newWidth, newHeight, newUploader, newUploadDate);
         DefaultLib.blockKeypresses = false;
      }
   }
   else
   {
      DefaultLib.blockKeypresses = false;
      return;
   }
}

// Stops showing any upload.
DefaultLib.stopShowingLightbox = function()
{
   // Checking the lightbox is indeed being shown
   if($('#lightbox .lightboxContent').html().length == 0)
      return;
   
   DefaultLib.blockKeypresses = true;

   $('#blackScreen').fadeOut(500);
   $('#lightbox').attr('data-cur-file', 'none');
   $('#lightbox').fadeOut(1000, function()
   {
      // Empties the container
      $('#lightbox .lightboxContent').html('');
      $('#lightbox .lightboxBottom .LBLeft').html('');
      $('#lightbox .lightboxBottom .LBCenter').html('');
      $('#lightbox .lightboxBottom .LBRight').html('');
      DefaultLib.blockKeypresses = false;
   });
}

/*
* Replaces a video block by the embedded video upon click. This function used to be defined in 
* topic_interaction.js and ping_interaction.js, but was put in common here.
*
* @param string idVideo  ID of the video to show
* @param string idPost   ID of the post where the video appears
*/

DefaultLib.showVideo = function(idVideo, idPost)
{
   var type = $('.videoThumbnail[data-post-id="' + idPost + '"][data-video-id="' + idVideo + '"]').attr('data-video-type');
   var trueID = $('.videoThumbnail[data-post-id="' + idPost + '"][data-video-id="' + idVideo + '"]').attr('data-video-true-id');
   
   if(type === 'youtube')
   {
      var embedded = "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/";
      embedded += trueID + "\" frameborder=\"0\" allowfullscreen></iframe>";
      
      $('.videoWrapper' + idPost + '-' + idVideo).animate({opacity: 0.0}, 500, function()
      {
         $('.videoWrapper' + idPost + '-' + idVideo).html(embedded);
         $('.videoWrapper' + idPost + '-' + idVideo).animate({opacity: 1.0}, 500);
      });
   }
}

/*
* Shows a spoiler, and edits the button to show/hide it depending on the state.
*
* @param idSpoiler  The ID of the spoiler to show/hide
*/

DefaultLib.showSpoiler = function(idSpoiler)
{
   var visibleBlock = $('#' + idSpoiler + ':visible');
   if(visibleBlock.length == 0)
   {
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour masquer");
   }
   else
   {
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour afficher");
   }
   $('#' + idSpoiler).toggle(100);
}

/*
* Diagnoses an error after trying to send an AJAX request.
*
* @param String textstatus  The same textstatus as in the function in error:
* @param String message     Ditto but for message/errorthrown
*/

DefaultLib.diagnose = function(textstatus, message)
{
   if(textstatus === 'timeout')
   {
      alert('Impossible d\'obtenir la réponse pour le moment.'
      + '\r\nRéessayez plus tard ou contactez un administrateur.');
   }
   else if(textstatus === 'error' && message === 'Not Found')
   {
      alert('Le script traitant votre requête est impossible à atteindre.'
      + '\r\nRéessayez plus tard ou contactez un administrateur.');
   }
   else
   {
      alert('Un problème est survenu lors du traitement de la requête.'
      + '\r\nMerci de réessayer plus tard.');
   }
}

/*
* Function to check if an AJAX request is being handled. If not, it sets usingAJAX to true and 
* returns false, otherwise it returns true.
*
* @return boolean  False if there is no AJAX request being handled
*/

DefaultLib.isHandlingAJAX = function()
{
   if(DefaultLib.usingAJAX)
      return true;
   
   DefaultLib.usingAJAX = true;
   
   clearTimeout($.data(this, 'timerAJAX'));
   var bubbleFadeIn = setTimeout(function()
   {
      if(!DefaultLib.usingAJAX)
         return;
      
      $('#bubble').html('<img src="' + DefaultLib.httpPath + 'res_icons/loading.gif" alt="Chargement" title="En attente de la réponse..."/>');
      $('#bubble').fadeIn(200);
   }, 300);
   
   return false;
}

/*
* Similarly, function to reset usingAJAX global to false. It returns nothing.
*/

DefaultLib.doneWithAJAX = function()
{
   DefaultLib.usingAJAX = false;
   
   if($('#bubble').is(":visible"))
   {
      $('#bubble').fadeOut(200, function() {
         $('#bubble').html('');
      });
   }
   else
   {
      clearTimeout($.data(this, 'timerAJAX'));
   }
}

/*
* Sets the click events related to the default library.
*/

$(document).ready(function()
{
   if($('.connectionLink').length)
   {
      $('.connectionLink').on('click', function() { DefaultLib.openDialog('#connection'); });
      $('#connection .closeDialog').on('click', function() { DefaultLib.closeDialog(); });
   }
   
   // User menu
   if($('#showUserMenu').length)
   {
      $('#showUserMenu').on('mouseover', function() { $('#userMenu').css('display', 'block'); });
      $('#showUserMenu').on('mouseout', function() { $('#userMenu').css('display', 'none'); });
   }
   
   // Private messages menu
   if($('#showPings').length)
   {
      $('#showPings').on('mouseover', function() { $('#pings').css('display', 'block'); });
      $('#showPings').on('mouseout', function() { $('#pings').css('display', 'none'); });
   }
   
   // Show a password
   if($('input[type=password]').length)
   {
      $('input[type=password]').each(function()
      {
         var name = $(this).attr('name');
         if($('input[type=checkbox][name=' + name + '_show]').length)
         {
            $('input[type=checkbox][name=' + name + '_show]').on('click', function()
            {
               if($('input[type=password][name=' + name + ']').length)
                  $('input[name=' + name + ']').attr('type', 'text');
               else
                  $('input[name=' + name + ']').attr('type', 'password');
            });
         }
      });
   }
   
   /* 
   * Code to re-align scroll correctly on page load when they are anchors. The difficulty is that 
   * images might not be fully loaded at the time, resulting in a bad scroll. To mitigate this, a 
   * small timeout of 0,5s is being used.
   */
   
   if(window.location.hash && window.location.hash !== '#main')
   {
      setTimeout(function()
      {
         if($(window.location.hash).length)
            $(document).scrollTop($(window.location.hash).offset().top - 95);
      }, 500);
   }
});

// Handles keypresses for dialogs and the lightbox
$(document).keydown(function(e)
{
   if(e.keyCode == 13) // Enter
   {
      if($('.window:visible .triggerDialog').length)
      {
         e.preventDefault();
         $('.window:visible .triggerDialog').trigger('click');
      }
   }
   if(e.keyCode == 27) // Esc
   {
      if($('#lightbox').is(':visible') && !DefaultLib.blockKeypresses)
         DefaultLib.stopShowingLightbox();
      else if($('.window:visible').length)
         DefaultLib.closeDialog();
   }
   else if (e.keyCode == 37) // Left
   {
      if($('#lightbox').is(':visible') && !DefaultLib.blockKeypresses)
         DefaultLib.slideShow(true);
   }
   else if (e.keyCode == 39) // Right
   {
      if($('#lightbox').is(':visible') && !DefaultLib.blockKeypresses)
         DefaultLib.slideShow(false);
   }
});

/*
 * Performs smooth scrolling. Not from me; taken right from CSS-tricks:
 * https://css-tricks.com/snippets/jquery/smooth-scrolling/
 */

$(function()
{
   // Smooth scrolling
   $('a[href*="#"]:not([href="#"])').click(function() {
      if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname)
      {
         var target = $(this.hash);
         target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
         if (target.length)
         {
            var offset = 0;
            if(target !== 'main')
               offset = 95;
            
            $('html, body').animate({
               scrollTop: target.offset().top - offset
            }, 1000);
            return false;
         }
      }
   });
});
