/**
* This file defines various functions to handle emoticons in a convenient fashion. This sometimes 
* requires additionnal dialogs, handled with custom windows.
*/

var EmoticonsLib = {};
EmoticonsLib.selected = 0; // Maintains ID of selected emoticon when mapping/editing an emoticon

// Functions to prepare the various dialogs (avoids repeating some code).

EmoticonsLib.prepareMapping = function(block)
{
   EmoticonsLib.selected = block.attr('data-id-emoticon');
   var shortcut = $('.emoticonBlock[data-id-emoticon="' + EmoticonsLib.selected + '"] .suggestedShortcut').text();
   $('#mapEmoticon input[name="map_emoticon_shortcut"]').val(shortcut);
   DefaultLib.openDialog('#mapEmoticon');
}

EmoticonsLib.prepareEdition = function(block)
{
   EmoticonsLib.selected = block.attr('data-id-emoticon');
   var emoticonName = $('.emoticonBlock[data-id-emoticon="' + EmoticonsLib.selected + '"] .emoticonName').text();
   var shortcut = $('.emoticonBlock[data-id-emoticon="' + EmoticonsLib.selected + '"] .suggestedShortcut').text();
   $('#editEmoticon input[name="edit_emoticon_name"]').val(emoticonName);
   $('#editEmoticon input[name="edit_emoticon_shortcut"]').val(shortcut);
   DefaultLib.openDialog('#editEmoticon');
}

EmoticonsLib.prepareShortcutEdition = function(block)
{
   EmoticonsLib.selected = block.attr('data-id-emoticon');
   var shortcut = $('.emoticonBlock[data-id-emoticon="' + EmoticonsLib.selected + '"] .usedShortcut').text();
   $('#editEmoticonShortcut input[name="edit_emoticon_shortcut2"]').val(shortcut);
   DefaultLib.openDialog('#editEmoticonShortcut');
}

/*
* Updates an upload progress bar, given an event evt.
*
* @param mixed evt  The event giving the amount of bytes loaded (plus total to upload)
*/

EmoticonsLib.update_progress = function(evt, target, additionnalText)
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
* Loads an emoticon in the most regular fashion (i.e., like in uploads.js).
*/

EmoticonsLib.loadEmoticon = function()
{
   var file = $('#uploadEmoticon');
   var name = $('input[name="new_emoticon_name"]').val();
   var shortcut = $('input[name="new_emoticon_shortcut"]').val();
   
   if((file)[0].files.length === 0)
   {
      alert('Sélectionnez un fichier.');
      return;
   }
   
   if(name.length == 0)
   {
      alert('Indiquez un nom pour cette émoticône.');
      return;
   }
   
   if(shortcut.length == 0)
   {
      alert('Indiquez un nom pour cette émoticône.');
      return;
   }
   
   // Quick check of the shortcut
   var firstCharCode = shortcut.substring(0, 1);
   if(firstCharCode !== ';' && firstCharCode !== ':')
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   var shortCutCode = '';
   var lastCharCode = shortcut.substring(shortcut.length - 1);
   if(lastCharCode === ':')
      shortcutCode = shortcut.substring(1, shortcut.length - 2);
   else 
      shortcutCode = shortcut.substring(1);
      
   var matches = shortcutCode.match(/^[a-zA-Z0-9\(\)\|\[\]\\\^_-]{1,29}$/);
   if(matches === null || matches.length != 1)
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }

   // Only the first file is considered here
   var actualFile = (file)[0].files[0];
   var formData = new FormData();
   formData.append("image", actualFile);
   formData.append("name", name);
   formData.append("shortcut", shortcut);
   
   // Gets the form to put it back later, after the upload
   var titleWindow = $('#newEmoticon .windowTop').html();
   var form = $('#newEmoticon .windowContent').html();
   
   // "Now loading..."
   $('#newEmoticon .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
   $('#newEmoticon .windowContent').html("<p style=\"text-align: center;\">0%</p>");
   
   // Handles an upload progress bar
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         EmoticonsLib.update_progress(e, "#newEmoticon", "");
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // AJAX request sent to the PHP script that will generate the thumbnail
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateEmoticon.php', 
   data: formData,
   xhr: provider,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      var restoreDialog = true;
      // Handles errors sent by the PHP script
      if(text === "file not loaded")
      {
         alert("Le fichier n'a été chargé: vérifiez son intégrité.");
      }
      else if(text === "file too big")
      {
         alert("Le fichier soumis excède 500 Ko.");
      }
      else if(text === "no more space")
      {
         alert("Le serveur n'a plus la capacité suffisante pour charger ce fichier.\n"
         + "Contactez un administrateur ou réessayez plus tard.");
      }
      else if(text === "bad file format")
      {
         alert("Le fichier soumis n'est pas un JPEG/JPG, GIF ou PNG.");
      }
      else if(text === "bad dimensions")
      {
         alert("Le fichier soumis excède les dimensions maximales autorisées: 120 x 90 pixels.");
      }
      else if(text === "bad shortcut")
      {
         alert("Le code pour cette émoticône est mal formaté.");
      }
      else if(text === "duplicate shortcut")
      {
         alert("Le code proposé est déjà utilisé par une autre émoticône.");
      }
      else if(text === "DB error")
      {
         alert("Une erreur est survenue lors de l'enregistrement dans la base de données.\n"
         + "Contactez un administrateur ou réessayez plus tard.");
      }
      else if(text === "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
      }
      // Success
      else
      {
         // Dialog is closed and form is put back in order to create a new thumbnail (if necessary)
         DefaultLib.closeAndUpdateDialog(function()
         {
            $('#newEmoticon .windowTop').html(titleWindow);
            $('#newEmoticon .windowContent').html(form);
            
            // Restores events
            $('#newEmoticon .triggerDialog').on('click', EmoticonsLib.loadEmoticon);
            $("#newEmoticon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
         });
      
         // Add the emoticon to the current page
         $('#emoticonsPool').append(text);
         
         // Binds the events
         $('.buttonUnmap').last().on('click', function()
         {
            var idEmoticon = $(this).attr('data-id-emoticon');
            EmoticonsLib.unmap(idEmoticon);
         });
         
         if($('.buttonDelete').last().length)
         {
            $('.buttonDelete').last().on('click', function()
            {
               var idEmoticon = $(this).attr('data-id-emoticon');
               EmoticonsLib.deleteEmoticon(idEmoticon);
            });
         }
         
         if($('.buttonEdit').last().length)
         {
            $('.buttonEdit').last().on('click', function()
            {
               EmoticonsLib.prepareEdition($(this));
            });
         }
         
         if($('.buttonEditShortcut').last().length)
         {
            $('.buttonEditShortcut').last().on('click', function()
            {
               EmoticonsLib.prepareShortcutEdition($(this));
            });
         }
         
         restoreDialog = false; // Because already done
      }
      
      if(restoreDialog)
      {
         // Restores window content
         $('#newEmoticon .windowTop').html(titleWindow);
         $('#newEmoticon .windowContent').html(form);

         // Restores events
         $('#newEmoticon .triggerDialog').on('click', EmoticonsLib.loadEmoticon);
         $("#newEmoticon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      // Restores window content
      $('#newEmoticon .windowTop').html(titleWindow);
      $('#newEmoticon .windowContent').html(form);
      
      // Restores events
      $('#newEmoticon .triggerDialog').on('click', EmoticonsLib.loadEmoticon);
      $("#newEmoticon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/*
* Sends an AJAX request to map the user to some emoticon, which ID has been previously stored in 
* the variable "EmoticonsLib.selected".
*/

EmoticonsLib.map = function()
{
   if(EmoticonsLib.selected == 0)
      return;

   var idEmoticon = EmoticonsLib.selected;
   var shortcut = $('input[name="map_emoticon_shortcut"]').val();

   if(shortcut.length == 0)
   {
      alert('Indiquez un code pour cette émoticône.');
      return;
   }
   
   // Quick check of the shortcut
   var firstCharCode = shortcut.substring(0, 1);
   if(firstCharCode !== ';' && firstCharCode !== ':')
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   var shortCutCode = '';
   var lastCharCode = shortcut.substring(shortcut.length - 1);
   if(lastCharCode === ':')
      shortcutCode = shortcut.substring(1, shortcut.length - 2);
   else 
      shortcutCode = shortcut.substring(1);
      
   var matches = shortcutCode.match(/^[a-zA-Z0-9\(\)\|\[\]\\\^_-]{1,29}$/);
   if(matches === null || matches.length != 1)
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/MapEmoticon.php', 
   data: 'id_emoticon='+idEmoticon+'&shortcut='+shortcut,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === "bad shortcut")
         {
            alert("Le code pour cette émoticône est mal formaté.");
         }
         else if(data === "duplicate shortcut")
         {
            alert("Le code proposé est déjà utilisé par une autre émoticône.");
         }
         else if(data === "DB error")
         {
            alert("Une erreur est survenue lors de l'enregistrement dans la base de données.\n"
            + "Contactez un administrateur ou réessayez plus tard.");
         }
         else
         {
            DefaultLib.closeAndUpdateDialog(function()
            {
               $('input[name="map_emoticon_shortcut"]').val('');
            });
         
            // Adds the user's own shortcut
            var eBlock = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"]');
            var pToEdit = eBlock.find(".emoticonDetails p");
            var splittedContent = pToEdit.html().split("\n");
            var newContent = "\n" + splittedContent[1];
            newContent += "\n<strong>Code (utilisé):</strong> <span class=\"usedShortcut\">" + shortcut + "</span><br/>";
            for(i = 2; i < splittedContent.length; i++)
               newContent += "\n" + splittedContent[i];
            pToEdit.html(newContent);
         
            // Changes the buttons and binds the new events
            eBlock.find("h1 div").html(data);
            $('.buttonUnmap[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
            {
               var idEmoticon = $(this).attr('data-id-emoticon');
               EmoticonsLib.unmap(idEmoticon);
            });
            
            if($('.buttonDelete[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonDelete[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  var idEmoticon = $(this).attr('data-id-emoticon');
                  EmoticonsLib.deleteEmoticon(idEmoticon);
               });
            }
            
            if($('.buttonEdit[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonEdit[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  EmoticonsLib.prepareEdition($(this));
               });
            }
            
            if($('.buttonEditShortcut[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonEditShortcut[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  EmoticonsLib.prepareShortcutEdition($(this));
               });
            }
            
            EmoticonsLib.selected = 0;
         }
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Sends an AJAX request to unmap the user from an emoticon which ID is provided as an argument.
*/

EmoticonsLib.unmap = function(idEmoticon)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/UnmapEmoticon.php',
   data: 'id_emoticon='+idEmoticon,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         var library = $('#selectedLibrary').attr('data-library');
         var eBlock = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"]');
         
         // If we are in user's library, remove the whole block
         if(library === 'user')
         {
            eBlock.animate({opacity: 0.0}, 600, function()
            {
               eBlock.remove();
               
               if(!($('.emoticonBlock').length))
               {
                  $('#emoticonsPool').html('<p class="poolError">Vous n\'avez actuellement '
                  + 'aucune émoticône.<br/><br/><br/></p>');
               }
            });
         }
         // If we are in the global library...
         else
         {
            // Removes the user's own shortcut
            var pToEdit = eBlock.find(".emoticonDetails p");
            var splittedContent = pToEdit.html().split("\n");
            var newContent = "\n" + splittedContent[1];
            for(i = 3; i < splittedContent.length; i++)
               newContent += "\n" + splittedContent[i];
            pToEdit.html(newContent);
         
            // Changes the buttons and binds the new events
            eBlock.find("h1 div").html(data);
            $('.buttonMap[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
            {
               EmoticonsLib.prepareMapping($(this));
            });
            
            if($('.buttonDelete[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonDelete[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  var idEmoticon = $(this).attr('data-id-emoticon');
                  EmoticonsLib.deleteEmoticon(idEmoticon);
               });
            }
            
            if($('.buttonEdit[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonEdit[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  EmoticonsLib.prepareEdition($(this));
               });
            }
            
            if($('.buttonEditShortcut[data-id-emoticon="' + idEmoticon + '"]').length)
            {
               $('.buttonEditShortcut[data-id-emoticon="' + idEmoticon + '"]').on('click', function()
               {
                  EmoticonsLib.prepareShortcutEdition($(this));
               });
            }
         }
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Sends an AJAX request to delete an emoticon which ID is provided as an argument.
*/

EmoticonsLib.deleteEmoticon = function(idEmoticon)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeleteEmoticon.php', 
   data: 'id_emoticon='+idEmoticon,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === 'OK')
         {
            var eBlock = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"]');
            eBlock.animate({opacity: 0.0}, 600, function()
            {
               eBlock.remove();
               
               if(!($('.emoticonBlock').length))
               {
                  $('#emoticonsPool').html('<p class="poolError">Il n\'y a actuellement aucune '
                  + 'émoticône dans la librairie.<br/><br/><br/></p>');
               }
            });
         }
         else
         {
            alert('Une erreur est survenue avec la base de données. Réessayez plus tard.');
         }
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Sends an AJAX request to change some emoticon and updates the page accordingly in case of 
* success.
*/

EmoticonsLib.edit = function()
{
   if(EmoticonsLib.selected == 0)
      return;

   var idEmoticon = EmoticonsLib.selected;
   var name = $('input[name="edit_emoticon_name"]').val();
   var shortcut = $('input[name="edit_emoticon_shortcut"]').val();

   if(name.length == 0)
   {
      alert('Indiquez un nom pour cette émoticône.');
      return;
   }
   
   if(shortcut.length == 0)
   {
      alert('Indiquez un code pour cette émoticône.');
      return;
   }
   
   // Checks if the name and shortcut actually changed
   var curName = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .emoticonName').text();
   var curShortcut = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .suggestedShortcut').text();
   
   if(name === curName && shortcut === curShortcut)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   // Quick check of the shortcut
   var firstCharCode = shortcut.substring(0, 1);
   if(firstCharCode !== ';' && firstCharCode !== ':')
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   var shortCutCode = '';
   var lastCharCode = shortcut.substring(shortcut.length - 1);
   if(lastCharCode === ':')
      shortcutCode = shortcut.substring(1, shortcut.length - 2);
   else 
      shortcutCode = shortcut.substring(1);
      
   var matches = shortcutCode.match(/^[a-zA-Z0-9\(\)\|\[\]\\\^_-]{1,29}$/);
   if(matches === null || matches.length != 1)
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // Sends the AJAX request
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/EditEmoticon.php', 
   data: 'id_emoticon='+idEmoticon+'&name=' + name + '&shortcut='+shortcut,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === "bad shortcut")
         {
            alert("Le code pour cette émoticône est mal formaté.");
         }
         else if(data === "duplicate shortcut")
         {
            alert("Le code proposé est déjà utilisé par une autre émoticône.");
         }
         else if(data === "forbidden operation")
         {
            alert("Vous n'avez pas les droits nécessaires pour modifier cette émoticône.");
         }
         else if(data === "DB error")
         {
            alert("Une erreur est survenue lors de l'enregistrement dans la base de données.\n"
            + "Contactez un administrateur ou réessayez plus tard.");
         }
         else
         {
            DefaultLib.closeAndUpdateDialog(function()
            {
               $('input[name="edit_emoticon_name"]').val('');
               $('input[name="edit_emoticon_shortcut"]').val('');
            });
         
            // Updates emoticon name and shortcut
            $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .emoticonName').text(name);
            $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .suggestedShortcut').text(shortcut);
            if($('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .usedShortcut').length)
               $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .usedShortcut').text(shortcut);
            
            EmoticonsLib.selected = 0;
         }
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Sends an AJAX request to change the shortcut used by some user who is mapped to a given emoticon.
*/

EmoticonsLib.editShortcut = function()
{
   if(EmoticonsLib.selected == 0)
      return;

   var idEmoticon = EmoticonsLib.selected;
   var shortcut = $('input[name="edit_emoticon_shortcut2"]').val();

   if(shortcut.length == 0)
   {
      alert('Indiquez un code pour cette émoticône.');
      return;
   }
   
   // Checks if the name and shortcut actually changed
   var curShortcut = $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .usedShortcut').text();
   
   if(shortcut === curShortcut)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   // Quick check of the shortcut
   var firstCharCode = shortcut.substring(0, 1);
   if(firstCharCode !== ';' && firstCharCode !== ':')
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   var shortCutCode = '';
   var lastCharCode = shortcut.substring(shortcut.length - 1);
   if(lastCharCode === ':')
      shortcutCode = shortcut.substring(1, shortcut.length - 2);
   else 
      shortcutCode = shortcut.substring(1);
      
   var matches = shortcutCode.match(/^[a-zA-Z0-9\(\)\|\[\]\\\^_-]{1,29}$/);
   if(matches === null || matches.length != 1)
   {
      alert('Le code proposé n\'est pas valide.');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/EditEmoticonShortcut.php', 
   data: 'id_emoticon='+idEmoticon+'&shortcut='+shortcut,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === "bad shortcut")
         {
            alert("Le code pour cette émoticône est mal formaté.");
         }
         else if(data === "duplicate shortcut")
         {
            alert("Le code proposé est déjà utilisé par une autre émoticône.");
         }
         else if(data === "no mapping")
         {
            alert("Cette émoticône n'est pas dans votre librairie personnelle.");
         }
         else if(data === "DB error")
         {
            alert("Une erreur est survenue lors de l'enregistrement dans la base de données.\n"
            + "Contactez un administrateur ou réessayez plus tard.");
         }
         else
         {
            DefaultLib.closeAndUpdateDialog(function()
            {
               $('input[name="edit_emoticon_shortcut2"]').val('');
            });
            
            $('.emoticonBlock[data-id-emoticon="' + idEmoticon + '"] .usedShortcut').text(shortcut);
            EmoticonsLib.selected = 0;
         }
      }
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
   // Main events (visible buttons)
   $('#newEmoticonDialog').on('click', function () { DefaultLib.openDialog('#newEmoticon'); });
   $('.buttonMap').on('click', function()
   {
      EmoticonsLib.prepareMapping($(this));
   });
   $('.buttonUnmap').on('click', function()
   {
      var idEmoticon = $(this).attr('data-id-emoticon');
      EmoticonsLib.unmap(idEmoticon);
   });
   if($('.buttonDelete')[0])
   {
      $('.buttonDelete').on('click', function()
      {
         var idEmoticon = $(this).attr('data-id-emoticon');
         EmoticonsLib.deleteEmoticon(idEmoticon);
      });
   }
   if($('.buttonEdit')[0])
   {
      $('.buttonEdit').on('click', function() { EmoticonsLib.prepareEdition($(this)); });
   }
   if($('.buttonEditShortcut')[0])
   {
      $('.buttonEditShortcut').on('click', function() { EmoticonsLib.prepareShortcutEdition($(this)); });
   }
   
   // Events in dialogs
   $("#newEmoticon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   $("#mapEmoticon .closeDialog").on('click', function ()
   {
      EmoticonsLib.selected = 0;
      DefaultLib.closeDialog();
   });
   $("#editEmoticon .closeDialog").on('click', function ()
   {
      EmoticonsLib.selected = 0;
      DefaultLib.closeDialog();
   });
   $("#editEmoticonShortcut .closeDialog").on('click', function ()
   {
      EmoticonsLib.selected = 0;
      DefaultLib.closeDialog();
   });
   
   $('#newEmoticon .triggerDialog').on('click', EmoticonsLib.loadEmoticon);
   $('#mapEmoticon .triggerDialog').on('click', EmoticonsLib.map);
   $('#editEmoticon .triggerDialog').on('click', EmoticonsLib.edit);
   $('#editEmoticonShortcut .triggerDialog').on('click', EmoticonsLib.editShortcut);
});
