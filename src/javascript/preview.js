/**
* This file contains functions to handle the preview feature in the advanced mode while posting. 
* It is, by usage, mutually exclusive with quick_preview.js.
*/

var PreviewLib = {};
PreviewLib.auto = false; // Set to true if auto-preview is activated

/*
* previewMode() modifies the global variable "autoPreview" and updates the display accordingly 
* (a single button).
*/

PreviewLib.previewMode = function()
{
   if(PreviewLib.auto)
   {
      PreviewLib.auto = false;
      $('#autoPreview').css("background-color", "#383a3f");
      $('#autoPreview').hover(function () {
         $('#autoPreview').css("background-color", "#557083");
      }, function(){
         $('#autoPreview').css("background-color", "#383a3f");
      });
      $('#autoPreview').click(function() {
         $('#autoPreview').css("background-color", "grey");
      });
   }
   else
   {
      PreviewLib.auto = true;
      $('#autoPreview').css('background-color', '#557083');
      $('#autoPreview').hover(function () {
         $('#autoPreview').css("background-color", "#383a3f");
      }, function(){
         $('#autoPreview').css("background-color", "#557083");
      });
      $('#autoPreview').click(function() {
         $('#autoPreview').css("background-color", "grey");
      });
   }
}

/*
* preview() takes the content of a textarea named "message" and sent it to some PHP script which
* will produce HTML code corresponding to a preview of that message (with the format code being
* parsed, of course). This new HTML code is placed inside a div named "preview".
*/

PreviewLib.preview = function()
{
   var content = encodeURIComponent($('textarea[name=message]').val());
   
   // Nothing happens if the content in the form is empty
   if(content == "")
      return;
   
   var author = $('#preview').attr('data-author');
   var rank = $('#preview').attr('data-rank');
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/PreviewPost.php', 
   data: 'author='+author+'&rank='+rank+'&message='+content,
   timeout: 5000,
   success: function(text)
   {
      if($('#preview').html())
      {
         $('#preview').html(text);
      }
      else
      {
         $('#preview').attr('style', 'display: block; opacity: 0.0;'); // #preview becomes "block" if it was "none" before
         $('#preview').html(text);
         $('#preview').animate({opacity: 1.0}, 1000);
      }
      
      // Ensures the "dynamic" parts of the formatting are working
      $('.spoiler a:first-child').on('click', function()
      {
         var spoilerId = $(this).attr('data-id-spoiler');
         DefaultLib.showSpoiler(spoilerId);
      });
      
      $('.miniature').on('click', function()
      {
         DefaultLib.showUpload($(this));
      });
      
      $('.videoThumbnail').on('click', function()
      {
         var index = $(this).attr('data-post-id');
         var videoId = $(this).attr('data-video-id');
         DefaultLib.showVideo(videoId, index);
      });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      // DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Binds the events.

$(document).ready(function()
{
   $("#manualPreview").on('click', PreviewLib.preview);
   $("#autoPreview").on('click', PreviewLib.previewMode);
   $('textarea[name="message"]').keydown(function(e)
   {
      if(PreviewLib.auto)
      {
         clearTimeout($.data(this, 'timerPreview'));
         var keystrokeEnd = setTimeout(PreviewLib.preview, 1000);
         $(this).data('timerPreview', keystrokeEnd);
      }
   });
});
