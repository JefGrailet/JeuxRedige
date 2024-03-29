/**
* This file contains functions to handle specific functionalities required for segment edition, 
* such as segment header creation (via upload).
*/

/******************
* Segment preview *
*******************/

var SegmentEditorLib = {};

/**********
* Uploads *
***********/

/*
* Updates an upload progress bar, given an event evt.
*
* @param mixed evt  The event giving the amount of bytes loaded (plus total to upload)
*/

SegmentEditorLib.update_progress = function(evt, target, additionnalText)
{
   if(evt.lengthComputable)
   {
      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
      if(percentLoaded <= 100)
      {
          $(target + ' .windowContent').html("<div class=\"progressBar\">" +
          "<span style=\"width:" + percentLoaded +"%\"></span>" +
          "</div>" +
          "<p style=\"text-align: center;\">" + percentLoaded + '%' + additionnalText + '</p>');
      }
   }
}

/*
* Loads a file sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a miniature of it. Afterwards, the display at the user is updated with the
* new miniature (or icon, for certain files) or an error message.
*/

SegmentEditorLib.loadFile = function()
{
   var file = $('#uploadFile');
   
   if((file)[0].files.length === 0)
   {
      alert('Sélectionnez un fichier.');
      return;
   }
   
   // Gets the form to put it back later, after the upload
   var titleWindow = $('#fileUpload .windowTop').html();
   var form = $('#fileUpload .windowContent').html();
   
   var nbFiles = (file)[0].files.length;
   var i = 0;
   var needsToStop = false;
   
   /*
    * This function ensures each AJAX request is completed one after another (since AJAX is 
    * asynchronous). During an iteration, only the progress bar is modified in the display.
    */
   
   function iteration() 
   {
      if(i < nbFiles && !needsToStop)
      {
         // Only the first file is considered here
         var actualFile = (file)[0].files[i];
         var formData = new FormData();
         formData.append("newFile", actualFile);
         
         // "Now loading..."
         var progressFiles = "";
         if(nbFiles > 0)
            progressFiles = "(" + (i + 1).toString() + "/" + nbFiles.toString() + ")";
         $('#fileUpload .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
         $('#fileUpload .windowContent').html("<p style=\"text-align: center;\">0% " + progressFiles + "</p>");
         
         // Handles an upload progress bar (thanks Danielo)
         var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
         if(xhr.upload)
         {
            xhr.upload.addEventListener('progress', function(e)
            {
               SegmentEditorLib.update_progress(e, "#fileUpload", progressFiles);
            }, false);
         }
         provider = function() { return xhr; }
         
         if(DefaultLib.isHandlingAJAX())
            return;
         
         $.ajax({
         type: 'POST',
         url: DefaultLib.httpPath + 'ajax/UploadFile.php', 
         data: formData,
         xhr: provider,
         timeout: 30000,
         success: function(text)
         {
            DefaultLib.doneWithAJAX();
            if(text == "file not loaded")
            {
               alert("Le fichier n'a été chargé: vérifiez son intégrité.");
            }
            else if(text == "buffer limit reached")
            {
               alert("Vous ne pouvez pas uploader davantage de fichiers pour le même message.");
               needsToStop = true;
            }
            else if(text == "file too big")
            {
               alert("Le fichier soumis excède 5 Mo.");
            }
            else if(text == "no more space")
            {
               alert("Le serveur n'a plus la capacité suffisante pour charger ce fichier.\n"
               + "Contactez un administrateur ou réessayez plus tard.");
            }
            else if(text == "not a supported format")
            {
               alert("Le format du fichier soumis ne fait pas partie des formats supportés.");
            }
            else if(text == "fail")
            {
               alert("Une erreur lors du traitement du fichier est survenue. Veuillez vérifier son format.");
            }
            else if(text == "fail2")
            {
               alert("Une erreur inconnue est survenue. L'upload a eu lieu mais la création de son aperçu a échoué.");
            }
            // Success; display is going to be updated
            else
            {
               // First, we get what is already in the container and adapt it for the new upload
               var uploadsList = $('.uploadsView').html();
               if(uploadsList.replace(/\s|\n+/g, '').length > 10) // Adds a space between each picture
                  uploadsList += " ";
               
               // Second, we explode the "text" var to create the new view
               var exploded = text.split(",");
               var fullSize = exploded[1];
               var newUploadView = exploded[2];

               $('.uploadsView').html(uploadsList + newUploadView);

               // Updates click events
               $('.buttonShowUpload').on('click', function()
               {
                  DefaultLib.showUpload($(this).parent());
               });
               
               $('.buttonIntegrateUpload').on('click', function()
               {
                  var relativeFilePath = $(this).attr('data-relative-path');
                  SegmentEditorLib.addUploadFormatCode(relativeFilePath);
               });
               
               $('.buttonDeleteUpload').on('click', function()
               {
                  var filePath = $(this).closest('.uploadView').attr('data-file');
                  SegmentEditorLib.deleteUpload(filePath);
               });
            }
            
            // Next file
            i++;
            iteration();
         },
         error: function(xmlhttprequest, textstatus, message)
         {
            DefaultLib.doneWithAJAX();
            DefaultLib.diagnose(textstatus, message);
            
            // Next file
            i++;
            iteration();
         },
         processData: false,
         contentType: false
         });
      }
      else
      {
         // Dialog is closed and form is put back in order to upload a new file (if necessary)
         DefaultLib.closeAndUpdateDialog(function()
         {
            $('#fileUpload .windowTop').html(titleWindow);
            $('#fileUpload .windowContent').html(form);
            
            // Restores events
            $('#fileUpload .triggerDialog').on('click', SegmentEditorLib.loadFile);
            $("#fileUpload .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
         });
      }
   }
   
   // First iteration
   iteration();
}

/*
* Deletes an upload and removes it from the display. The deletion is carried out by AJAX, just 
* like the upload.
*/

SegmentEditorLib.deleteUpload = function(target)
{
   var uploadView = $('.uploadView[data-file="' + target + '"]');
   
   var formData = new FormData();
   formData.append("fileToDelete", target);
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeleteUploadedFile.php', 
   data: formData,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text == "does not exist")
      {
         alert("Ce fichier n'existe pas ou plus.");
      }
      else if(text == "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
      }
      // Success
      else
      {
         // The display is now being updated.
         uploadView.animate({opacity: 0.0}, 300, function()
         {
            uploadView.remove();
            
            // If a container is empty, resets its
            var uploadsList = $('.uploadsView');
            var firstContainerEmpty = false;
            if(uploadsList.html().replace(/\s|\n+/g, '').length < 10)
            {
               uploadsList.html('');
               firstContainerEmpty = true;
            }
            
            var previousUploadsList = $('.previousUploadsList');
            var secondContainerEmpty = false;
            if(previousUploadsList.length)
            {
               if(previousUploadsList.html().replace(/\s|\n+/g, '').length < 10)
               {
                  previousUploadsList.html('');
                  secondContainerEmpty = true;
               }
            }
            else
               secondContainerEmpty = true;
            
            if(firstContainerEmpty && secondContainerEmpty)
               $('.uploadOptions').html('');
         });
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   },
   processData: false,
   contentType: false
   });
}

/*
* Add a format code to display the given upload to the first textarea found in the body of the 
* page.
*/

SegmentEditorLib.addUploadFormatCode = function(target)
{
   $('input[type="text"][name="url_img"]').val(target);
   DefaultLib.openDialog('#integrateImg');
}

/*****************
* Segment header *
******************/

/*
* A segment header is similar to a thumbnail in the sense that it's another picture with a given, 
* fixed name in the containing folder and only in JPEG format. It is different because it is much 
* larger in dimensions (1920 pixels in width to fit all resolutions up to full HD) and that the 
* original file is expected to be larger than 1920 pixels to this end.
*/

/*
* Loads an image sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a segment header with it. Afterwards, the display at the user is updated with 
* the new (scaled down) header or an error message.
*/

SegmentEditorLib.loadSegmentHeader = function()
{
   var file = $('#uploadHeader');
   
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
   var titleWindow = $('#segmentHeader .windowTop').html();
   var form = $('#segmentHeader .windowContent').html();
   
   // "Now loading..."
   $('#segmentHeader .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
   $('#segmentHeader .windowContent').html("<p style=\"text-align: center;\">0%</p>");
   
   // Handles an upload progress bar
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         SegmentEditorLib.update_progress(e, "#segmentHeader", "");
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateSegmentHeader.php', 
   data: formData,
   xhr: provider,
   timeout: 30000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      var restoreDialog = false;
      if(text == "file not loaded")
      {
         alert("Le fichier n'a été chargé: vérifiez son intégrité.");
         restoreDialog = true;
      }
      else if(text == "file too big")
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
         // Dialog is closed and form is put back in order to create a new header (if necessary)
         DefaultLib.closeAndUpdateDialog(function()
         {
            $('#segmentHeader .windowTop').html(titleWindow);
            $('#segmentHeader .windowContent').html(form);
            
            // Restores events
            $('#segmentHeader .triggerDialog').on('click', SegmentEditorLib.loadSegmentHeader);
            $("#segmentHeader .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
         });
      
         // The old image fades away and the new image appears in the reverse way
         $('#previewHeader').animate({opacity: 0.0}, 600).promise().done(function()
         {
            $('#previewHeader').attr('src', DefaultLib.httpPath + text.substr(2));
            $('#previewHeader').animate({opacity: 1.0}, 600);
         });
         
         $('input[name=header]').attr('value', text);
      }
      
      if(restoreDialog)
      {
         // Restores window content
         $('#segmentHeader .windowTop').html(titleWindow);
         $('#segmentHeader .windowContent').html(form);

         // Restores events
         $('#segmentHeader .triggerDialog').on('click', SegmentEditorLib.loadSegmentHeader);
         $("#segmentHeader .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#segmentHeader .windowTop').html(titleWindow);
      $('#segmentHeader .windowContent').html(form);
      
      // Restores events
      $('#segmentHeader .triggerDialog').on('click', SegmentEditorLib.loadSegmentHeader);
      $("#segmentHeader .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/*********************
* Binding all events *
**********************/

$(document).ready(function()
{
   if($('#previewHeader').length)
   {
      $('#previewHeader').on('click', function()
      {
         if($('#segmentHeader').length)
            DefaultLib.openDialog('#segmentHeader');
         else
            alert('Vous n\'avez pas les droits pour uploader de nouveaux fichiers.');
      });
   }
   
   if($('#segmentHeader').length)
   {
      $('#segmentHeader .triggerDialog').on('click', SegmentEditorLib.loadSegmentHeader);
      $("#segmentHeader .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   }
   
   if($('#uploadMenu').length)
   {
      $('#uploadMenu p a').on('click', function() { DefaultLib.openDialog('#fileUpload'); });
   }
   
   if($('#fileUpload').length)
   {
      $('#fileUpload .triggerDialog').on('click', SegmentEditorLib.loadFile);
      $("#fileUpload .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   }
   
   $('.buttonShowUpload').on('click', function()
   {
      DefaultLib.showUpload($(this).parent());
   });
   
   $('.buttonIntegrateUpload').on('click', function()
   {
      var relativeFilePath = $(this).attr('data-relative-path');
      SegmentEditorLib.addUploadFormatCode(relativeFilePath);
   });
   
   $('.buttonDeleteUpload').on('click', function()
   {
      var filePath = $(this).closest('.uploadView').attr('data-file');
      SegmentEditorLib.deleteUpload(filePath);
   });
});
