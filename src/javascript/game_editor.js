/**
* This file defines functions specially designed for game edition pages. Most of them are similar
* to other scripts (like uploads.js) but are slightly adapted to the interface of game edition 
* pages.
*/

var GameEditorLib = {};
GameEditorLib.isFieldEmpty = true; // Tells if the alias field is empty or not
GameEditorLib.isValidAlias = false; // True when the current input can be a valid alias

/*****************
* Thumbnail part *
*****************/

/*
* Updates an upload progress bar, given an event evt.
*
* @param mixed evt  The event giving the amount of bytes loaded (plus total to upload)
*/

GameEditorLib.update_progress = function(evt)
{
   if (evt.lengthComputable)
   {
      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
      if (percentLoaded <= 100)
      {
          $('#customThumbnail .windowContent').html("<div class=\"progressBar\">" +
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

GameEditorLib.loadThumbnail = function()
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
   
   // Handles an upload progress bar (thanks Danielo)
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         GameEditorLib.update_progress(e);
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // AJAX request sent to the PHP script that will generate the thumbnail
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateThumbnail.php', 
   data: formData,
   xhr: provider,
   timeout: 5000,
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
         $('#previewThumbnail').animate({opacity: 0.0}, 300);
         $('#previewThumbnail').attr('src', text);
         $('#previewThumbnail').animate({opacity: 1.0}, 300);
         
         $('input[name=thumbnail]').attr('value', text);
      }
      
      // Rebinds events
      $('#customThumbnail .triggerDialog').on('click', GameEditorLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#customThumbnail .windowTop').html(titleWindow);
      $('#customThumbnail .windowContent').html(form);
      
      // Rebinds events
      $('#customThumbnail .triggerDialog').on('click', GameEditorLib.loadThumbnail);
      $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/*************
* Alias part *
*************/

/*
* checkAlias() takes the value which is currently in an input field named "alias" and send it with
* AJAX to a PHP script which will send back a message telling if this alias can be used or not.
*/

GameEditorLib.checkAlias = function()
{
   var needle = $('input[type=text][name="alias"]').val();
   if(!needle || needle.length === 0)
   {
      GameEditorLib.isFieldEmpty = true;
      $('#aliasTest').html('');
   }
   
   GameEditorLib.isFieldEmpty = false;
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CheckAlias.php', 
   data: 'alias='+needle,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text === 'OK')
      {
         GameEditorLib.isValidAlias = true;
         $('#aliasTest').html('<span style="color: green;">Alias utilisable (appuyez sur Enter pour valider)</span>');
      }
      else
      {
         GameEditorLib.isValidAlias = false;
         $('#aliasTest').html('<span style="color: red;">Alias inutilisable (titre d\'un jeu dans la BDD)</span>');
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
* AddAlias() takes an input string sitting in an input "alias" and adds it in an hidden field 
* which will be later used by a PHP script to register 1 to 10 aliases with a single field. It 
* also displays it in a kind of  list where a delete button is made available for each alias. 
* Finally, 2 characters must be escaped (" and |) and the length of the alias is limited to 
* 50 characters, so the alias is reduced if it is too long. Finally, the code takes care of
* duplicata.
*/

GameEditorLib.addAlias = function()
{
   $('#aliasTest').html('');
   var alias = $('input[type=text][name="alias"]').val();
   $('input[type=text][name="alias"]').val('');
   
   alias = alias.split('"').join('');
   alias = alias.split('|').join('');
   if(alias.length > 50) // Max length for an alias: 50 characters
      alias = alias.substring(0, 50);

   var aliases = $('input[type=hidden][name="aliases"]').val();
   var aliasesArr = aliases.split('|');
   var aliasesList = $('.aliasesList').html();
   
   // Removes the "<br/><br/>"
   if(aliasesList.length > 0)
   {
      var lastSpace = aliasesList.lastIndexOf(" ");
      aliasesList = aliasesList.substr(0, lastSpace);
   }
   
   var aliasNotPresent = true;
   for(i = 0; i < aliasesArr.length; i++)
   {
      if(aliasesArr[i] === alias)
      {
         aliasNotPresent = false;
         break;
      }
   }
   
   if(aliases.length === 0 || aliasNotPresent)
   {
      var deleteButton = ' <a onclick="javascript:GameEditorLib.removeAlias(\'';
      deleteButton += alias + '\')" class="deleteAlias">';
      deleteButton += '<img src="' + DefaultLib.httpPath + 'res_icons/delete.png" alt="Delete" ';
      deleteButton += 'title="Supprimer cet alias"/></a>';
      if(aliases.length === 0)
      {
         $('input[type=hidden][name="aliases"]').val(alias);
         
         var addition = alias + deleteButton + " <br/>\n<br/>\n";
         $('.aliasesList').html(addition);
      }
      else if(aliasesArr.length < 10)
      {
         $('input[type=hidden][name="aliases"]').val(aliases + '|' + alias);
         
         var addition = aliasesList + ' ' + alias + deleteButton + " <br/>\n<br/>\n";
         $('.aliasesList').html(addition);
      }
   }
}

/*
* removeAlias(), as the name suggests, remove an alias from the list previously built by the
* end user. It operates on both the hidden list and the displayed list. The method is simple:
* the hidden list is split as an array, and the lists are recomputed ignoring the selected
* alias. The only exceptional case is where the list contains only one alias; in that case,
* we just compare it to the given alias to decide whether or not we wipe away both lists.
*
* @param string alias  The alias the user wants to remove
*/

GameEditorLib.removeAlias = function(alias)
{
   var aliases = $('input[type=hidden][name="aliases"]').val();
   var aliasesArr = aliases.split('|');
   
   if(aliasesArr.length === 1)
   {
      if(aliases === alias)
      {
         $('input[type=hidden][name="aliases"]').val('');
         $('.aliasesList').html('');
      }
      return;
   }
   
   var guardian = true;
   var newAliases = '';
   var newAliasesList = '';
   for(i = 0; i < aliasesArr.length; i++)
   {
      if(aliasesArr[i] === alias)
         continue;
   
      if(!guardian)
      {
         newAliases += '|';
         newAliasesList += ' ';
      }
      else
         guardian = false;
   
      var deleteButton = ' <a onclick="javascript:GameEditorLib.removeAlias(\'';
      deleteButton += aliasesArr[i] + '\')" class="deleteAlias">';
      deleteButton += '<img src="' + DefaultLib.httpPath + 'res_icons/delete.png" alt="Delete" ';
      deleteButton += 'title="Supprimer cet alias"/></a>';
      
      newAliases += aliasesArr[i];
      newAliasesList += aliasesArr[i] + deleteButton;
   }
   $('input[type=hidden][name="aliases"]').val(newAliases);
   $('.aliasesList').html(newAliasesList + " <br/>\n<br/>\n");
}

// Binds events

$(document).ready(function()
{
   // Activates multiselection on hardware field
   $("#hardware").multipleSelect();

   $('#customThumbnail .triggerDialog').on('click', GameEditorLib.loadThumbnail);
   $("#customThumbnail .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   $('input[type=text][name="alias"]').keypress(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   $('input[type=text][name="alias"]').keyup(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   $('input[type=text][name="alias"]').keydown(function(e)
   {
      var code = e.keyCode;
      if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
      {
         clearTimeout($.data(this, 'timer'));
         var keystrokeEnd = setTimeout(GameEditorLib.checkAlias, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });
});

// Handles the Enter key press

$(document).keypress(function(e)
{
   if(!GameEditorLib.isFieldEmpty && e.keyCode === 13)
      return false; // Avoids submitting the whole form on pressing Enter
});

$(document).keyup(function(e)
{
   if(!GameEditorLib.isFieldEmpty && e.keyCode === 13)
      return false; // Same (for cross browser compatibility)
});

$(document).keydown(function(e)
{
   if(GameEditorLib.isValidAlias && e.keyCode === 13) // Enter
   {
      GameEditorLib.addAlias();
      $('#aliasTest').html('');
      GameEditorLib.isValidAlias = false;
      return false;
   }
});
