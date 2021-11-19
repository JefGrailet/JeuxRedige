/**
* This file contains a generic library to handle the automatic preview of messages/content 
* featuring format code, such as forum posts and a segment of an article.
*/

var PreviewLib = {};
PreviewLib.previewEnabled = false;
PreviewLib.leftRightPreview = true;
PreviewLib.extraDetails = false; // Additional display below the edition zone for article edition
PreviewLib.delayedResize = null; // Involved in re-sizing the #previewFrame div

/*
* previewMode() modifies the variable "previewEnabled" and updates the display accordingly (a 
* single button). It also takes account of whether the user has picked the left/right or 
* top/bottom comparison of the editable zone and the preview zone.
*/

PreviewLib.previewMode = function()
{
   if(PreviewLib.previewEnabled)
   {
      PreviewLib.previewEnabled = false;
      $('#autoPreview').css("background-color", "#383a3f");
      $('#autoPreview').hover(function () {
         $('#autoPreview').css("background-color", "#557083");
      }, function(){
         $('#autoPreview').css("background-color", "#383a3f");
      });
      $('#autoPreview').click(function() {
         $('#autoPreview').css("background-color", "grey");
      });
      
      $('#previewZone').remove();
      $('#previewMode').remove();
      $('#previewInfo').remove();
      $('#textareaWrapper #editableZone').css('width', 'auto');
   }
   else
   {
      PreviewLib.previewEnabled = true;
      $('#autoPreview').css('background-color', '#557083');
      $('#autoPreview').hover(function () {
         $('#autoPreview').css("background-color", "#383a3f");
      }, function(){
         $('#autoPreview').css("background-color", "#557083");
      });
      $('#autoPreview').click(function() {
         $('#autoPreview').css("background-color", "grey");
      });
      
      // Additionnal span to control the preview display (left/right or top/bottom comparison)
      var prevMode = '<span id="previewMode">' + "\n";
      if(PreviewLib.leftRightPreview)
         prevMode += '<input type="radio" name="prevMode" value="LeftRight" id="prevModeLeftRight" checked/> ';
      else
         prevMode += '<input type="radio" name="prevMode" value="LeftRight" id="prevModeLeftRight"/> ';
      prevMode += '<label for="prevModeLeftRight">Comparaison gauche/droite</label> ';
      if(!PreviewLib.leftRightPreview)
         prevMode += '<input type="radio" name="prevMode" value="TopBottom" id="prevModeTopBottom" checked/> ';
      else
         prevMode += '<input type="radio" name="prevMode" value="TopBottom" id="prevModeTopBottom"/> ';
      prevMode += '<label for="prevModeTopBottom">Comparaison haut/bas</label> ';
      prevMode += "<br/>\n<br/>\n</span>";
      
      // Additional span to warn the user about format code for articles
      var prevInfo = "";
      if(PreviewLib.extraDetails)
      {
         prevInfo = '<span style="color: grey;" id="previewInfo"><strong>Remarque:</strong> ';
         prevInfo += 'l\'aperçu automatique du formatage propre aux articles est simplifié. ';
         prevInfo += 'Pensez à visualiser un aperçu complet d\'un article pour mieux ';
         prevInfo += "évaluer sa mise en page.<br/>\n<br/>\n</span>";
      }
      
      // Includes the #previewZone block and adjusts display for side by side comparison
      if(PreviewLib.leftRightPreview)
      {
         $('#textareaWrapper #editableZone').css('width', '50%');
         $('#editableZone textarea').css('margin-right', '5px');
      }
      else
      {
         $('#textareaWrapper').css('flex-wrap', 'wrap');
         $('#editableZone').css('margin-bottom', '10px');
      }
      $('#textareaWrapper').append(' <div id="previewZone"><div id="previewFrame"></div></div>');
      if(PreviewLib.leftRightPreview)
      {
         $('#previewZone').css('width', '50%');
         $('#previewFrame').css('margin-left', '5px');
      }
      else
      {
         $('#previewZone').css('width', '100%');
      }
      
      // Adds the additional preview display
      $('#textareaWrapper').nextAll('p:first').prepend(prevMode + prevInfo);
      
      // Binds events for switching between left/right and top/bottom mode
      $('input[type=radio][name=prevMode]').on('click', function()
      {
         var radioValue = $(this).val();
         if(PreviewLib.leftRightPreview && radioValue == 'TopBottom')
         {
            // General adjustements (to have the zones stacked)
            $('#textareaWrapper #editableZone').css('width', '100%');
            $('#previewZone').css('width', '100%');
            $('#textareaWrapper').css('flex-wrap', 'wrap');
            
            // Margin adjustements (for aesthetical purposes)
            $('#editableZone textarea').css('margin-right', '17px');
            $('#previewFrame').css('margin-left', '17px');
            $('#editableZone').css('margin-bottom', '10px');
            
            PreviewLib.leftRightPreview = false;
         }
         else if(!PreviewLib.leftRightPreview && radioValue == 'LeftRight')
         {
            // General adjustements (to have the zones next to each)
            $('#textareaWrapper').css('flex-wrap', 'nowrap');
            $('#textareaWrapper #editableZone').css('width', '50%');
            $('#previewZone').css('width', '50%');
            
            // Margin adjustements (for aesthetical purposes)
            $('#editableZone textarea').css('margin-right', '5px');
            $('#previewFrame').css('margin-left', '5px');
            $('#editableZone').css('margin-bottom', '0px');
            
            PreviewLib.leftRightPreview = true;
         }
      });
      PreviewLib.preview();
   }
}

/*
* preview() takes the content of a textarea named "message" (content of a segment) and sends it to 
* some PHP script which will produce HTML code corresponding to a preview of the segment. This new 
* HTML code is placed inside a textarea named "previewZone".
*/

PreviewLib.preview = function()
{
   var content = encodeURIComponent($('textarea[name=message]').val());
   var contentType = encodeURIComponent($('#textareaWrapper').attr('data-preview-type'));
   
   // Nothing happens if the content in the form is empty
   if(content == "")
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/Preview.php', 
   data: 'what=' + contentType + '&message=' + content,
   timeout: 5000,
   success: function(formattedText)
   {
      $('#previewFrame').html('<p>' + formattedText + '</p>');
      
      // Ensures the "dynamic" parts of the formatting are working
      $('#previewFrame .spoiler a:first-child').on('click', function()
      {
         var spoilerId = $(this).attr('data-id-spoiler');
         DefaultLib.showSpoiler(spoilerId);
      });
      
      $('#previewFrame .miniature').on('click', function()
      {
         DefaultLib.showUpload($(this));
      });
      
      $('#previewFrame .videoThumbnail').on('click', function()
      {
         var index = $(this).attr('data-id-post');
         var videoId = $(this).attr('data-id-video');
         DefaultLib.showVideo(videoId, index);
      });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      // DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* adjustPreviewFrame() resizes the #previewFrame div such that it matches the height of the text 
* area found in #editableZone. It is called when the height of said text area is changed by the 
* user with the mouse, but it first checks the preview is enabled and a #previewZone div exists 
* before doing anything. Doing so avoids to bind/unbind events (mouseup and mousedown) when the 
* preview is switched on and off, so that said events can be bound only once.
*/

PreviewLib.adjustPreviewFrame = function()
{
   if(!PreviewLib.previewEnabled || $('#previewZone').length == 0)
      return;
   
   var textareaHeight = parseInt($('#editableZone textarea').css('height'));
   var adjustedFrameHeight = textareaHeight - 8; // 2 * (3 + 1) pixels (padding plus border)
   $('#previewFrame').css('height', adjustedFrameHeight.toString());
}

/*****************
* Binding events *
******************/

$(document).ready(function()
{
   $("#autoPreview").on('click', PreviewLib.previewMode);
   
   if(!$('#textareaWrapper').attr('data-preview-type') !== undefined)
   {
      var typeOfContent = $('#textareaWrapper').attr('data-preview-type');
      if(typeOfContent === 'segment')
         PreviewLib.extraDetails = true;
   }

   $('textarea[name="message"]').on('keydown', function(e)
   {
      if(PreviewLib.previewEnabled)
      {
         clearTimeout($.data(this, 'timerPreview'));
         var keystrokeEnd = setTimeout(PreviewLib.preview, 1000);
         $(this).data('timerPreview', keystrokeEnd);
      }
   });
   
   // Events involved in re-sizing the #previewFrame div (based on: https://jsfiddle.net/gbouthenot/D2bZd/)
   $('textarea[name="message"]').on('mousedown', function(e)
   {
      PreviewLib.delayedResize = setInterval(PreviewLib.adjustPreviewFrame, 20); // 50 times per second (1000 / 50)
   });
   
   $(window).on('mouseup', function(e)
   {
      if(PreviewLib.delayedResize !== null)
         clearInterval(PreviewLib.delayedResize);
      PreviewLib.adjustPreviewFrame();
   });
   
   // Synchronized scroll when scrolling the editable zone
   $('textarea[name="message"]').on('scroll', function(e)
   {
      // Nothing happens if preview is not enabled
      if(!PreviewLib.previewEnabled || $('#previewZone').length == 0)
         return;
      
      let selector = '#previewFrame', p = this.scrollTop / (this.scrollHeight - this.offsetHeight);
      $(selector)[0].scrollTop = p * ($(selector)[0].scrollHeight - $(selector)[0].offsetHeight);
   });
});
