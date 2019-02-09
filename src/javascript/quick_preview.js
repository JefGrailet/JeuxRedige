/**
* This file contains functions to handle the quick preview feature while posting, and consists in 
* just replacing the basic format code (e.g., bold text). It is, by usage, mutually exclusive with 
* preview.js.
*/

var QuickPreviewLib = {};
QuickPreviewLib.enabled = false; // Set to true if activated

/*
* enable() modifies the variable "enabled" and updates the display accordingly (a single button).
*/

QuickPreviewLib.enable = function()
{
   if(QuickPreviewLib.enabled)
   {
      QuickPreviewLib.enabled = false;
      $('#quickPreview').css("background-color", "#383a3f");
      $('#quickPreview').hover(function () {
         $('#quickPreview').css("background-color", "#557083");
      }, function(){
         $('#quickPreview').css("background-color", "#383a3f");
      });
      $('#quickPreview').click(function() {
         $('#quickPreview').css("background-color", "grey");
      });
      
      $('#previewZone').remove();
      $('#textareaWrapper p').css('width', '98.5%');
   }
   else
   {
      QuickPreviewLib.enabled = true;
      $('#quickPreview').css('background-color', '#557083');
      $('#quickPreview').hover(function () {
         $('#quickPreview').css("background-color", "#383a3f");
      }, function(){
         $('#quickPreview').css("background-color", "#557083");
      });
      $('#quickPreview').click(function() {
         $('#quickPreview').css("background-color", "grey");
      });
      
      $('#textareaWrapper p').css('width', '48.5%');
      $('#textareaWrapper').append(' <div id="previewZone"><p></p></div>');
      QuickPreviewLib.preview();
   }
}

/*
* preview() takes the content of a textarea named "message" and sent it to some PHP script 
* which will produce HTML code corresponding to a quick preview of that message (with the format 
* code being parsed, of course, except for more dynamic stuff like images and clips). This new 
* HTML code is placed inside a textarea named "previewZone".
*/

QuickPreviewLib.preview = function()
{
   var content = encodeURIComponent($('textarea[name=message]').val());
   
   // Nothing happens if the content in the form is empty
   if(content == "")
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/QuickPreview.php', 
   data: 'message='+content,
   timeout: 5000,
   success: function(text)
   {
      $('#previewZone p').html(text);
      
      // Ensures the "dynamic" parts of the formatting are working
      $('#previewZone .spoiler a:first-child').on('click', function()
      {
         var spoilerId = $(this).attr('data-id-spoiler');
         DefaultLib.showSpoiler(spoilerId);
      });
      
      $('#previewZone .miniature').on('click', function()
      {
         DefaultLib.showUpload($(this));
      });
      
      $('#previewZone .videoThumbnail').on('click', function()
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
   $("#quickPreview").on('click', QuickPreviewLib.enable)
   $('textarea[name="message"]').keydown(function(e)
   {
      if(QuickPreviewLib.enabled)
      {
         clearTimeout($.data(this, 'timerPreview'));
         var keystrokeEnd = setTimeout(QuickPreviewLib.preview, 1000);
         $(this).data('timerPreview', keystrokeEnd);
      }
   });
});
