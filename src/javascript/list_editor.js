/**
* This file defines functions to handle list edition.
*/

ListEditorLib = {};

/*
* Updates the counter of characters for the textarea zone.
*/

ListEditorLib.updateCharCounter = function()
{
   var nbCharacters = $("textarea[name=description]").val().length;
   $('input[name=nb_characters]').val(nbCharacters);
   
   // Changes color if the amount of characters is above the limit
   if(nbCharacters > 1000)
      $('input[name=nb_characters]').attr('style', 'color: red;');
   else
      $('input[name=nb_characters]').attr('style', 'color: black;');
}

// Binds events
$(document).ready(function()
{
   // Counting the amount of characters in the textarea, and displaying the total
   $("textarea[name=description]").keyup(ListEditorLib.updateCharCounter);
   
   ListEditorLib.updateCharCounter(); // First count
});
