/**
* This file contains functions to handle various functionalities to ensure a short piece of 
* content being written is correctly previewed.
*/

var ContentEditorLib = {};
ContentEditorLib.previewEnabled = false;
ContentEditorLib.leftRightPreview = true; // Default

/*
* Shows a spoiler, and edits the button to show/hide it depending on the state.
*
* @param idSpoiler  The ID of the spoiler to show/hide
*/

ContentEditorLib.showSpoiler = function(idSpoiler)
{
   var visibleBlock = $('#' + idSpoiler + ':visible');
   if(visibleBlock.length == 0)
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour masquer");
   else
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour afficher");
   $('#' + idSpoiler).toggle(100);
}

/*
* previewMode() modifies the variable "previewEnabled" and updates the display accordingly (a 
* single button). It also takes account of whether the user has picked the left/right or 
* top/bottom comparison of the editable text and the preview.
*/

ContentEditorLib.previewMode = function()
{
   if(ContentEditorLib.previewEnabled)
   {
      ContentEditorLib.previewEnabled = false;
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
      $('#textareaWrapper #editableWrapper').css('width', '98.5%');
   }
   else
   {
      ContentEditorLib.previewEnabled = true;
      $('#autoPreview').css('background-color', '#557083');
      $('#autoPreview').hover(function () {
         $('#autoPreview').css("background-color", "#383a3f");
      }, function(){
         $('#autoPreview').css("background-color", "#557083");
      });
      $('#autoPreview').click(function() {
         $('#autoPreview').css("background-color", "grey");
      });
      
      // Additionnal span's to control the preview display and advertise the user about features
      var prevMode = '<span id="previewMode">' + "\n";
      if(ContentEditorLib.leftRightPreview)
         prevMode += '<input type="radio" name="prevMode" value="LeftRight" checked/> ';
      else
         prevMode += '<input type="radio" name="prevMode" value="LeftRight"/> ';
      prevMode += '<label for="prevMode">Comparaison gauche/droite</label> ';
      if(!ContentEditorLib.leftRightPreview)
         prevMode += '<input type="radio" name="prevMode" value="TopBottom" checked/> ';
      else
         prevMode += '<input type="radio" name="prevMode" value="TopBottom"/> ';
      prevMode += '<label for="prevMode">Comparaison haut/bas</label> ';
      prevMode += "<br/>\n<br/>\n</span>";
      
      if(ContentEditorLib.leftRightPreview)
         $('#textareaWrapper #editableWrapper').css('width', '48.5%');
      $('#textareaWrapper').append(' <div id="previewZone"><p></p></div>');
      if(!ContentEditorLib.leftRightPreview)
      {
         $('#previewZone').css('width', '98%');
         $('#previewZone').css('margin-left', '15px');
         $('#previewZone').css('margin-top', '10px');
      }
      $('#textareaWrapper').nextAll('p:first').prepend(prevMode);
      $('input[type=radio][name=prevMode]').on('click', function()
      {
         var radioValue = $(this).val();
         if(ContentEditorLib.leftRightPreview && radioValue == 'TopBottom')
         {
            $('#textareaWrapper #editableWrapper').css('width', '98.5%');
            $('#previewZone').css('width', '98%');
            $('#previewZone').css('margin-left', '15px');
            $('#previewZone').css('margin-top', '10px');
            ContentEditorLib.leftRightPreview = false;
         }
         else if(!ContentEditorLib.leftRightPreview && radioValue == 'LeftRight')
         {
            $('#textareaWrapper #editableWrapper').css('width', '48.5%');
            $('#previewZone').css('width', '48.5%');
            $('#previewZone').css('margin-left', '0px');
            $('#previewZone').css('margin-top', '0px');
            ContentEditorLib.leftRightPreview = true;
         }
      });
      ContentEditorLib.preview();
   }
}

/*
* preview() takes the content of a textarea named "message" (content of a segment) and sends it to 
* some PHP script which will produce HTML code corresponding to a preview of the review (which the 
* formatting policy is identical to a forum post). This new HTML code is placed inside a textarea 
* named "previewZone".
*/

ContentEditorLib.preview = function()
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
      $('.spoiler a:first-child').on('click', function()
      {
         var spoilerId = $(this).attr('data-id-spoiler');
         ContentEditorLib.showSpoiler(spoilerId);
      });
      
      $('.miniature').on('click', function()
      {
         DefaultLib.showUpload($(this));
      });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      // DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*********************
* Binding all events *
**********************/

$(document).ready(function()
{
   $("#autoPreview").on('click', ContentEditorLib.previewMode);

   $('textarea[name="message"]').keydown(function(e)
   {
      if(ContentEditorLib.previewEnabled)
      {
         clearTimeout($.data(this, 'timerPreview'));
         var keystrokeEnd = setTimeout(ContentEditorLib.preview, 1000);
         $(this).data('timerPreview', keystrokeEnd);
      }
   });
   
   $('.spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      ContentEditorLib.showSpoiler(spoilerId);
   });
   
   $('.miniature').on('click', function()
   {
      DefaultLib.showUpload($(this));
   });
});
