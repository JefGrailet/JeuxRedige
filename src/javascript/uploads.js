/**
* This file defines methods to handle the upload of files and the creation of custom thumbnails 
* for topics without reloading the page, by using AJAX. While the thumbnail creation only occurs 
* when creating/updating a topic, file upload can potentially occur when posting/editing any 
* message. However, both are put together in the same file to easily have the update_progress() 
* method in common and easily maintain the changes regarding HTML code, since it is similar.
*/

var UploadsLib = {};

/*
* Updates an upload progress bar, given an event evt, a target div. Some additionnal can also be 
* added (useful, for instance, to advertise the progress of uploading multiple files).
*
* @param mixed evt               The event giving the amount of bytes loaded (plus total to upload)
* @param string target           Name of the target div where the progress bar is showned
* @param string additionnalText  Optional text to write next to the percentage of progress
*/

UploadsLib.update_progress = function(evt, target, additionnalText)
{
   if (evt.lengthComputable)
   {
      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
      if (percentLoaded <= 100)
      {
          $(target + ' .windowContent').html("<div class=\"progressBar\">" +
          "<span style=\"width:" + percentLoaded +"%\"></span>" +
          "</div>" +
          "<p style=\"text-align: center;\">" + percentLoaded + '%' + additionnalText + '</p>');
      }
   }
}

/*
* Loads an image sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a thumbnail with it. Afterwards, the display at the user is updated with the
* new thumbnail or an error message.
*/

UploadsLib.loadThumbnail = function()
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
         UploadsLib.update_progress(e, "#customThumbnail", "");
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: './ajax/CreateThumbnail.php', 
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
         alert("Le fichier soumis excède 1 Mo.");
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
      else if(text == "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
         restoreDialog = true;
      }
      // Success
      else
      {
         // Dialog is closed and form is put back in order to create a new thumbnail (if necessary)
         DefaultLib.closeAndUpdateDialog(function()
         {
            $('#customThumbnail .windowTop').html(titleWindow);
            $('#customThumbnail .windowContent').html(form);
            
            // Restores events
            $('#customThumbnail .triggerDialog').on('click', UploadsLib.loadThumbnail);
            $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
         });
      
         // The old image fades away and the new image appears in the reverse way
         $('#previewThumbnail').animate({opacity: 0.0}, 600).promise().done(function()
         {
            $('#previewThumbnail').attr('src', DefaultLib.httpPath + text.substr(2));
            $('#previewThumbnail').animate({opacity: 1.0}, 600);
         });
         
         $('input[name=thumbnail]').attr('value', text);
      }
      
      if(restoreDialog)
      {
         // Restores window content
         $('#customThumbnail .windowTop').html(titleWindow);
         $('#customThumbnail .windowContent').html(form);

         // Restores events
         $('#customThumbnail .triggerDialog').on('click', UploadsLib.loadThumbnail);
         $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#customThumbnail .windowTop').html(titleWindow);
      $('#customThumbnail .windowContent').html(form);
      
      // Restores events
      $('#customThumbnail .triggerDialog').on('click', UploadsLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/*
* Loads a file sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating a miniature of it. Afterwards, the display at the user is updated with the
* new miniature (or icon, for certain files) or an error message.
*/

UploadsLib.loadFile = function()
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
               UploadsLib.update_progress(e, "#fileUpload", progressFiles);
            }, false);
         }
         provider = function() { return xhr; }
         
         if(DefaultLib.isHandlingAJAX())
            return;
         
         $.ajax({
         type: 'POST',
         url: './ajax/UploadFile.php', 
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
               alert("Une erreur inconnue est survenue. L'upload a eu lieu mais la création de\n"
               + "son aperçu a échoué.");
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
                  UploadsLib.addUploadFormatCode(relativeFilePath);
               });
               
               $('.buttonDeleteUpload').on('click', function()
               {
                  var filePath = $(this).closest('.uploadView').attr('data-file');
                  UploadsLib.deleteUpload(filePath);
               });
               
               // If options are not visible, makes them appears
               var uploadOptions = $('.uploadOptions');
               if(uploadOptions.html().length < 10)
               {
                  var optionsStr = "<label for=\"upload_display_policy\">Affichage des uploads:"
                  + "</label>\n"
                  + "<select name=\"upload_display_policy\">\n"
                  + "<option value=\"default\">En dessous de mon message</option>\n"
                  + "<option value=\"spoiler\">En dessous de mon message, masqué (spoilers)"
                  + "</option>\n"
                  + "<option value=\"nsfw\">En dessous de mon message, masqué (contenu mature)"
                  + "</option>\n"
                  + "<option value=\"noshow\">J'intègrerai mes uploads dans mon message"
                  + "</option>\n"
                  + "<option value=\"noshownsfw\">J'intègrerai mes uploads dans mon message"
                  + " (NSFW dans la galerie)</option>\n"
                  + "<option value=\"noshowspoiler\">J'intègrerai mes uploads dans mon message"
                  + " (spoiler dans la galerie)</option>\n"
                  + "</select><br/>\n<br/>\n";
                  
                  uploadOptions.html(optionsStr);
               }
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
            $('#fileUpload .triggerDialog').on('click', UploadsLib.loadFile);
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

UploadsLib.deleteUpload = function(target)
{
   var uploadView = $('.uploadView[data-file="' + target + '"]');
   
   var formData = new FormData();
   formData.append("fileToDelete", target);
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: './ajax/DeleteUploadedFile.php', 
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
            
            var previousUploadsList = $('.previousUploadsView');
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

UploadsLib.addUploadFormatCode = function(target)
{
   $('input[type="text"][name="url_img"]').val(target);
   DefaultLib.openDialog('#integrateImg');
}

// Binds events

$(document).ready(function()
{
   // Click events to open custom thumbnail/file upload dialogs
   if($('#previewThumbnail').length)
   {
      $('#previewThumbnail').on('click', function() { DefaultLib.openDialog('#customThumbnail'); });
   }
   
   if($('#uploadMenu').length)
   {
      $('#uploadMenu p a').on('click', function() { DefaultLib.openDialog('#fileUpload'); });
   }
   
   // Click events to close/finish dialog for custom thumbnail (if present)
   if($('#customThumbnail').length)
   {
      $('#customThumbnail .triggerDialog').on('click', UploadsLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   }
   
   // Click events to close/finish dialog for file upload (if present)
   if($('#fileUpload').length)
   {
      $('#fileUpload .triggerDialog').on('click', UploadsLib.loadFile);
      $("#fileUpload .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   }
   
   // Click events for each (displayed) upload div
   $('.buttonShowUpload').on('click', function()
   {
      DefaultLib.showUpload($(this).parent());
   });
   
   $('.buttonIntegrateUpload').on('click', function()
   {
      var relativeFilePath = $(this).attr('data-relative-path');
      UploadsLib.addUploadFormatCode(relativeFilePath);
   });
   
   $('.buttonDeleteUpload').on('click', function()
   {
      var filePath = $(this).closest('.uploadView').attr('data-file');
      UploadsLib.deleteUpload(filePath);
   });
});
