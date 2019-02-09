/**
* This file defines various functions to handle trope edition.
*/

TropeEditorLib = {};


/*****************
* Thumbnail part *
*****************/

/*
* Updates an upload progress bar, given an event evt.
*
* @param mixed evt  The event giving the amount of bytes loaded (plus total to upload)
*/

TropeEditorLib.update_progress = function(evt)
{
   if (evt.lengthComputable)
   {
      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
      if (percentLoaded <= 100)
      {
          $('#customIcon .windowContent').html("<div class=\"progressBar\">" +
          "<span style=\"width:" + percentLoaded +"%\"></span>" +
          "</div>" +
          "<p style=\"text-align: center;\">" + percentLoaded + '%</p>');
      }
   }
}

/*
* Loads an image sent by the user and send it to a PHP script responsible for (temporarly) storing
* it and generating the final XxY (max 45x45) PNG icon with it. Afterwards, the display at the 
* user is updated with the new thumbnail or an error message.
*/

TropeEditorLib.loadIcon = function()
{
   var file = $('#uploadIcon');
   
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
   var titleWindow = $('#customIcon .windowTop').html();
   var form = $('#customIcon .windowContent').html();
   
   // "Now loading..."
   $('#customIcon .windowTop').html("<span class=\"windowTitle\"><strong>Chargement en cours...</strong></span>");
   $('#customIcon .windowContent').html("<p style=\"text-align: center;\">0%</p>");
   
   // Handles an upload progress bar (thanks Danielo)
   var xhr = jQuery.ajaxSettings.xhr(); // Tells jQuery that we expand xhr object
   if(xhr.upload)
   {
      xhr.upload.addEventListener('progress', function(e)
      {
         TropeEditorLib.update_progress(e);
      }, false);
   }
   provider = function() { return xhr; }
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   // AJAX request sent to the PHP script that will generate the thumbnail
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CreateTropeIcon.php', 
   data: formData,
   xhr: provider,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text == "file too big")
      {
         alert("Le fichier soumis excède 1 Mo.");
         $('#customIcon .windowTop').html(titleWindow);
         $('#customIcon .windowContent').html(form);
      }
      else if(text == "no more space")
      {
         alert("Le serveur n'a plus la capacité suffisante pour charger ce fichier.\n"
         + "Contactez un administrateur ou réessayez plus tard.");
         $('#customIcon .windowTop').html(titleWindow);
         $('#customIcon .windowContent').html(form);
      }
      else if(text == "not a PNG")
      {
         alert("Le fichier soumis n'est pas un PNG.");
         $('#customIcon .windowTop').html(titleWindow);
         $('#customIcon .windowContent').html(form);
      }
      else if(text == "fail")
      {
         alert("Une erreur inconnue est survenue. Veuillez réessayer plus tard.");
         $('#customIcon .windowTop').html(titleWindow);
         $('#customIcon .windowContent').html(form);
      }
      // Success
      else
      {
         // Dialog is closed and form is put back in order to create a new icon (if necessary)
         $('#blackScreen').fadeOut(100);
         $('#customIcon').fadeOut(100);
         $('#customIcon .windowTop').html(titleWindow);
         $('#customIcon .windowContent').html(form);
      
         // The old image fades away and the new image appears in the reverse way
         $('#previewIcon').animate({opacity: 0.0}, 300);
         $('#previewIcon').attr('src', text);
         $('#previewIcon').animate({opacity: 1.0}, 300);
         
         $('input[name=icon]').attr('value', text);
      }
      
      // Rebinds events
      $('#customIcon .triggerDialog').on('click', TropeEditorLib.loadIcon);
      $("#customIcon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
      
      $('#customIcon .windowTop').html(titleWindow);
      $('#customIcon .windowContent').html(form);
      
      // Rebinds events
      $('#customIcon .triggerDialog').on('click', TropeEditorLib.loadIcon);
      $("#customIcon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   },
   processData: false,
   contentType: false
   });
}

/******************
* Color pick part *
******************/

/*
* Simple functions to handle RGB to hexadecimal conversion of a color. Based on this thread:
* https://stackoverflow.com/questions/5623838/rgb-to-hex-and-hex-to-rgb
*/

TropeEditorLib.componentToHex = function(c)
{
    var hex = c.toString(16);
    return hex.length == 1 ? "0" + hex : hex;
}

TropeEditorLib.rgbToHex = function(r, g, b)
{
    return "#" + TropeEditorLib.componentToHex(r) + TropeEditorLib.componentToHex(g) + TropeEditorLib.componentToHex(b);
}

TropeEditorLib.hexToRgb = function(hex)
{
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
}

/*
* Updates the display of the color selection, given the component (red, green or blue) being 
* edited.
*/

TropeEditorLib.updateColor = function(comp)
{
   var redComp = $('input[type="range"][name="red_comp"]').val();
   var greenComp = $('input[type="range"][name="green_comp"]').val();
   var blueComp = $('input[type="range"][name="blue_comp"]').val();
   
   var r = parseInt(redComp);
   var g = parseInt(greenComp);
   var b = parseInt(blueComp);
   
   if(comp === 'red')
      $('.colorShow[data-color-comp="red"]').css('background-color', 'rgb(' + redComp + ',0,0)');
   else if(comp === 'green')
      $('.colorShow[data-color-comp="green"]').css('background-color', 'rgb(0,' + greenComp + ',0)');
   else if(comp === 'blue')
      $('.colorShow[data-color-comp="blue"]').css('background-color', 'rgb(0,0,' + blueComp + ')');
   
   $('.colorShow[data-color-comp="mix"]').css('background-color', 'rgb(' + redComp + ',' + greenComp + ',' + blueComp + ')');
   $('input[name=color]').attr('value', TropeEditorLib.rgbToHex(r, g, b));
}

/*
* Updates the counter of characters for the textarea zone.
*/

TropeEditorLib.updateCharCounter = function()
{
   var nbCharacters = $("textarea[name=description]").val().length;
   $('input[name=nb_characters]').val(nbCharacters);
   
   // Changes color if the amount of characters is above the limit
   if(nbCharacters > 250)
      $('input[name=nb_characters]').attr('style', 'color: red;');
   else
      $('input[name=nb_characters]').attr('style', 'color: black;');
}

// Binds events
$(document).ready(function()
{
   // Icon generation
   $('#customIcon .triggerDialog').on('click', TropeEditorLib.loadIcon);
   $("#customIcon .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   // Counting the amount of characters in the textarea, and displaying the total
   $("textarea[name=description]").keyup(TropeEditorLib.updateCharCounter);

   // Color pick
   $('input[type="range"][name="red_comp"]').on('change', function () { TropeEditorLib.updateColor('red'); });
   $('input[type="range"][name="green_comp"]').on('change', function () { TropeEditorLib.updateColor('green'); });
   $('input[type="range"][name="blue_comp"]').on('change', function () { TropeEditorLib.updateColor('blue'); });
   
   // Sets the counter of characters for the description
   TropeEditorLib.updateCharCounter();
   
   // Sets the ranges for color pick
   var hexColor = $('input[name=color]').attr('value');
   var rgb = TropeEditorLib.hexToRgb(hexColor);
   
   $('.colorShow[data-color-comp="red"]').css('background-color', 'rgb(' + rgb.r + ',0,0)');
   $('.colorShow[data-color-comp="green"]').css('background-color', 'rgb(0,' + rgb.g + ',0)');
   $('.colorShow[data-color-comp="blue"]').css('background-color', 'rgb(0,0,' + rgb.b + ')');
   $('.colorShow[data-color-comp="mix"]').css('background-color', 'rgb(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ')');
   $('input[type="range"][name="red_comp"]').val(rgb.r);
   $('input[type="range"][name="green_comp"]').val(rgb.g);
   $('input[type="range"][name="blue_comp"]').val(rgb.b);
});
