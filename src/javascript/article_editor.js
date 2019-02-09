/**
* This file defines functions specially designed for article edition pages (that is, edition of 
* the structure of the article). Most of them are similar to other scripts (like uploads.js) but 
* are slightly adapted to articles.
*/

var ArticleEditorLib = {};

/***************************
* Thumbnail/highlight part *
***************************/

/*
* Updates an upload progress bar, given an event evt, a target div.
*
* @param mixed evt      The event giving the amount of bytes loaded (plus total to upload)
* @param string target  Name of the target div where the progress bar is showned
*/

ArticleEditorLib.update_progress = function(evt, target)
{
   if (evt.lengthComputable)
   {
      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
      if (percentLoaded <= 100)
      {
          $(target + ' .windowContent').html("<div class=\"progressBar\">" +
          "<span style=\"width:" + percentLoaded +"%\"></span>" +
          "</div>" +
          "<p style=\"text-align: center;\">" + percentLoaded + '%</p>');
      }
   }
}

/*
* Loads an image sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a thumbnail with it. Afterwards, the display at the user is updated with the
* new thumbnail or an error message.
*/

ArticleEditorLib.loadThumbnail = function()
{
   var file = $('#uploadThumbnail');
   
   if((file)[0].files.length === 0)
   {
      alert('Sélectionnez un fichier.');
      return;
   }

   // Only the first file is considered here
   var actualFile = (file)[0].files[0];
   var formData = new FormData();
   formData.append("image", actualFile);
   
   // Gets the form to put it back later, after the upload
   var titleWindow = $('#customThumbnail .windowTop').html();
   var form = $('#customThumbnail .windowContent').html();
   
   // "Now loading..."
   $('#customThumbnail .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
   $('#customThumbnail .windowContent').html("<p style=\"text-align: center;\">0%</p>");
   
   // Handles an upload progress bar
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         ArticleEditorLib.update_progress(e, '#customThumbnail');
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // AJAX request sent to the PHP script that will generate the thumbnail
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateArticleThumbnail.php', 
   data: formData,
   xhr: provider,
   timeout: 30000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text == "file too big")
      {
         alert("Le fichier soumis excède 1 Mo.");
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);
      }
      else if(text == "no more space")
      {
         alert("Le serveur n'a plus la capacité suffisante pour charger ce fichier.\n"
         + "Contactez un administrateur ou réessayez plus tard.");
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);
      }
      else if(text == "not a JPEG")
      {
         alert("Le fichier soumis n'est pas un JPEG/JPG.");
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);
      }
      else if(text == "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);
      }
      // Success
      else
      {
         // Dialog is closed and form is put back in order to create a new thumbnail (if necessary)
         $('#blackScreen').fadeOut(100);
         $('#customThumbnail').fadeOut(100);
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);
      
         // The old image fades away and the new image appears in the reverse way
         $('#previewThumbnail').animate({opacity: 0.0}, 600).promise().done(function()
         {
            $('#previewThumbnail').attr('src', DefaultLib.httpPath + text.substr(2));
            $('#previewThumbnail').animate({opacity: 1.0}, 600);
         });
         
         $('input[name=thumbnail]').attr('value', text);
      }
      
      // Rebinds events
      $('#customThumbnail .triggerDialog').on('click', ArticleEditorLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#customThumbnail .windowTop').html(titleWindow);
      $('#customThumbnail .windowContent').html(form);
      
      // Rebinds events
      $('#customThumbnail .triggerDialog').on('click', ArticleEditorLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/*
* Loads an image sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a highlight with it. Afterwards, the display at the user is updated with the
* new highlight or an error message. It is very similar to loadThumbnail, with a few differences.
*/

ArticleEditorLib.loadHighlight = function()
{
   var file = $('#uploadHighlight');
   
   if((file)[0].files.length === 0)
   {
      alert('Sélectionnez un fichier.');
      return;
   }

   // Only the first file is considered here
   var actualFile = (file)[0].files[0];
   var formData = new FormData();
   formData.append("image", actualFile);
   
   // Gets the form to put it back later, after the upload
   var titleWindow = $('#customHighlight .windowTop').html();
   var form = $('#customHighlight .windowContent').html();
   
   // "Now loading..."
   $('#customHighlight .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
   $('#customHighlight .windowContent').html("<p style=\"text-align: center;\">0%</p>");
   
   // Handles an upload progress bar
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         ArticleEditorLib.update_progress(e, '#customHighlight');
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // AJAX request sent to the PHP script that will generate the highlight picture
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateArticleHighlight.php', 
   data: formData,
   xhr: provider,
   timeout: 30000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      var restoreDialog = false;
      if(text == "file too big")
      {
         alert("Le fichier soumis excède 5 Mo.");
         restoreDialog = true;
      }
      else if(text == "no more space")
      {
         alert("Le serveur n'a plus la capacité suffisante pour charger ce fichier.\n"
         + "Contactez un administrateur ou réessayez plus tard.");
         restoreDialog = true;
      }
      else if(text == "not a JPEG")
      {
         alert("Le fichier soumis n'est pas un JPEG/JPG.");
         restoreDialog = true;
      }
      else if(text == "bad dimensions")
      {
         alert("Le fichier soumis ne respecte pas les dimensions attendues.");
         restoreDialog = true;
      }
      else if(text == "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
         restoreDialog = true;
      }
      // Success
      else
      {
         // Dialog is closed and form is put back in order to create a new highlight picture (if necessary)
         $('#blackScreen').fadeOut(100);
         $('#customHighlight').fadeOut(100);
         $('#customHighlight .windowTop').html(titleWindow);
         $('#customHighlight .windowContent').html(form);
      
         // The old image fades away and the new image appears in the reverse way
         $('#previewHighlight').animate({opacity: 0.0}, 600).promise().done(function()
         {
            $('#previewHighlight').attr('src', DefaultLib.httpPath + text.substr(2));
            $('#previewHighlight').animate({opacity: 1.0}, 600);
         });
         
         $('input[name=highlight]').attr('value', text);
      }
      
      if(restoreDialog)
      {
         $('#customHighlight .windowTop').html(titleWindow);
         $('#customHighlight .windowContent').html(form);
      }
      
      // Rebinds events
      $('#customHighlight .triggerDialog').on('click', ArticleEditorLib.loadHighlight);
      $("#customHighlight .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#customHighlight .windowTop').html(titleWindow);
      $('#customHighlight .windowContent').html(form);
      
      // Rebinds events
      $('#customHighlight .triggerDialog').on('click', ArticleEditorLib.loadHighlight);
      $("#customHighlight .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}


/************************
* Segment ordering part *
************************/

// Moves a segment, given its current position and a "direction" (>= 0 means up, down otherwise).

ArticleEditorLib.moveSegment = function(posToMove, direction)
{
   var segmentID1 = -1;
   if(direction >= 0)
      segmentID1 = parseInt($('tr[data-pos=' + posToMove + '] .moveUp').attr('data-segment-id'));
   else
      segmentID1 = parseInt($('tr[data-pos=' + posToMove + '] .moveDown').attr('data-segment-id'));
   
   if(segmentID1 == -1)
      return;
   
   var nextPos = -1;
   var segmentID2 = -1;
   if(direction >= 0) // Up
   {
      if(posToMove == 1)
         return;
      
      nextPos = (posToMove - 1);
      if($('tr[data-pos=' + nextPos + '] .moveDown').length)
         segmentID2 = parseInt($('tr[data-pos=' + nextPos + '] .moveDown').attr('data-segment-id'));
   }
   else // Down
   {
      nextPos = (posToMove + 1);
      if($('tr[data-pos=' + nextPos + '] .moveUp').length)
         segmentID2 = parseInt($('tr[data-pos=' + nextPos + '] .moveUp').attr('data-segment-id'));
   }
   
   if(segmentID2 == -1)
      return;
   
   var findID = $('form:first').attr('action').split('?id_article=');
   var articleID = findID[1];
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/SwitchSegments.php', 
   data: 'id_article=' + articleID + '&id_segment1=' + segmentID1 + '&id_segment2=' + segmentID2,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      
      if(text.startsWith("OK\n"))
      {
         var splitted = text.substr(3).split("\nSplit\n");
         
         // Replaces HTML
         $('tr[data-pos=' + posToMove + ']').fadeOut("slow", function()
         {
            $('tr[data-pos=' + posToMove + ']').replaceWith($(splitted[0]));
            
            // Rebinds events
            $('tr[data-pos=' + posToMove + '] .moveUp').on('click', function()  { ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), 1);  });
            $('tr[data-pos=' + posToMove + '] .moveDown').on('click', function() { ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), -1); });
            $('tr[data-pos=' + posToMove + '] .deleteSegment').on('click', function() { ArticleEditorLib.deleteSegment(parseInt($(this).attr('data-segment-id'))); });
            
            $('tr[data-pos=' + posToMove + ']').fadeIn("slow");
         });
         
         $('tr[data-pos=' + nextPos + ']').fadeOut("slow", function()
         {
            $('tr[data-pos=' + nextPos + ']').replaceWith($(splitted[1]));
            
            // Rebinds events
            $('tr[data-pos=' + nextPos + '] .moveUp').on('click', function() { ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), 1); });
            $('tr[data-pos=' + nextPos + '] .moveDown').on('click', function() { ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), -1); });
            $('tr[data-pos=' + nextPos + '] .deleteSegment').on('click', function() { ArticleEditorLib.deleteSegment(parseInt($(this).attr('data-segment-id'))); });
            
            $('tr[data-pos=' + nextPos + ']').fadeIn("slow");
         });
      }
      else
      {
         alert('Une erreur est survenue lors de la mise à jour. Réessayez plus tard ou prévenez un administrateur.');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Deletes a segment based on its ID. A confirmation is requested beforehand.

ArticleEditorLib.deleteSegment = function(IDToRemove)
{
   if(!confirm('Êtes-vous sûr ? Le contenu du segment ne pourra être récupéré.'))
     return;

   var posToRemove = -1;
   if($('.deleteSegment[data-segment-id=' + IDToRemove + ']').closest('tr').length)
      posToRemove = parseInt($('.deleteSegment[data-segment-id=' + IDToRemove + ']').closest('tr').attr('data-pos'));
   else
      return;
   
   var findID = $('form:first').attr('action').split('?id_article=');
   var articleID = findID[1];
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeleteSegment.php', 
   data: 'id_article=' + articleID + '&id_segment=' + IDToRemove,
   timeout: 5000,
   success: function(text)
   {
      if(text === 'OK')
      {
         $('tr[data-pos=' + posToRemove + ']').hide('slow', function()
         {
            $('tr[data-pos=' + posToRemove + ']').remove();
            if($('#segmentsList tr').length > 0) // If there remain rows
            {
               $('tr').each(function()
               {
                  var curPos = parseInt($(this).attr('data-pos'));
                  if(curPos > posToRemove)
                  {
                     $(this).attr('data-pos', curPos - 1);
                     var curPosStr = (curPos - 1).toString();
                     $(this).find('td:first').html('<strong>' + curPosStr + '</strong>');
                  }
               });
               
               if($('tr:last .moveDown').length)
                  $('tr:last .moveDown').remove();
            }
            else
            {
               $('#segmentsList').remove();
               $('#fullPreviewButton').remove();
               
               if($('#publiRequest').length)
               {
                  $('#publiMenuTitle').html('Suppression');
                  $('#publiDetails').remove();
                  $('#publiRequest').remove();
                  $('#publiDelete input[type=submit]').attr('name', 'delete');
               }
            }
         });
      }
      else
         alert('Une erreur est survenue lors de la mise à jour. Réessayez plus tard ou prévenez un administrateur.');
      
      DefaultLib.doneWithAJAX();
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Binds events

$(document).ready(function()
{
   $('#customThumbnail .triggerDialog').on('click', ArticleEditorLib.loadThumbnail);
   $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   $('#customHighlight .triggerDialog').on('click', ArticleEditorLib.loadHighlight);
   $("#customHighlight .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   $('.moveUp').on('click', function()
   {
      ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), 1);
   });
   
   $('.moveDown').on('click', function()
   {
      ArticleEditorLib.moveSegment(parseInt($(this).closest('tr').attr('data-pos')), -1);
   });
   
   $('.deleteSegment').on('click', function()
   {
      ArticleEditorLib.deleteSegment(parseInt($(this).attr('data-segment-id')));
   });
});