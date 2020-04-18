/**
* This file defines functions to input tropes in a convenient way. "Convenient" here means that
* the interface will provide tropes autocomplete after a small delay after a keystroke, and
* warn the user when (s)he provided an input that does not match any trope. In addition to the 
* autocomplete, the code also provides everything to manage the list of tropes provided by the 
* user.
*/

var TropesLib = {};
TropesLib.hoverSugg = false; // Boolean set to true if the mouse is hovering the suggestions
TropesLib.selectedIndex = -1; // Suggestion being selected (-1 = no suggestion)
TropesLib.nbSugg = 0; // Amount of suggestions being shown

/*
* searchTropes() takes the value which is currently in an input field named "trope" and sends 
* it with AJAX to a PHP script which will send back a list of suggestions of tropes which names 
* contain what was in the field, or a single suggestion to advertise there is no such trope.
*/

TropesLib.searchTropes = function()
{
   var needle = $('input[type=text][name="trope"]').val();
   if(!needle || needle.length === 0)
   {
      $('#tropesSuggestions').html('');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FindTropes.php', 
   data: 'keyword='+needle,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      $('#tropesSuggestions').html(text);
      TropesLib.nbSugg = parseInt($('#tropesSuggestions #suggestionsList').attr('data-sugg'));
      TropesLib.selectedIndex = 0; // First element always selected
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* addTrope() takes an input keyword and adds it in an hidden field which will be later used by a 
* PHP script to taken account of 1 to 10 tropes. It also displays it in a list with deletion 
* buttons. Since users cannot create new tropes like one can create new keywords, the function is 
* slightly simpler than addKeyword() in keywords.js.
*
* @param string keyword  The new trope
*/

TropesLib.addTrope = function(keyword)
{
   $('#tropesSuggestions').html('');
   $('input[type=text][name="trope"]').val('');
   if(keyword.length === 0)
   {
      return;
   }
   
   var tropes = $('input[type=hidden][name="tropes"]').val();
   var tropesArr = tropes.split('|');
   var tropesList = $('.tropesList').html();
   
   // Removes the "<br/><br/>"
   if(tropesList.length > 0)
   {
      var lastSpace = tropesList.lastIndexOf(" ");
      tropesList = tropesList.substr(0, lastSpace);
   }
   
   var tropeNotPresent = true;
   for(i = 0; i < tropesArr.length; i++)
   {
      if(tropesArr[i] === keyword)
      {
         tropeNotPresent = false;
         break;
      }
   }
   
   if(tropes.length === 0 || tropeNotPresent)
   {
      var deleteButton = ' <a onclick="javascript:TropesLib.removeTrope(\'';
      deleteButton += keyword + '\')" class="deleteKeyword">'; // Same CSS class as for keywords
      deleteButton += '<i class="icon-general_trash" title="Supprimer ce code"></i></a>';
      if(tropes.length === 0)
      {
         $('input[type=hidden][name="tropes"]').val(keyword);
         
         var addition = keyword + deleteButton + " <br/>\n<br/>\n";
         $('.tropesList').html(addition);
      }
      else if(tropesArr.length < 10)
      {
         $('input[type=hidden][name="tropes"]').val(tropes + '|' + keyword);
         
         var addition = tropesList + ' ' + keyword + deleteButton + " <br/>\n<br/>\n";
         $('.tropesList').html(addition);
      }
   }
}

/*
* removeTrope(), as the name suggests, removes a keyword from the list previously built by the
* user. The method basically works the same way as removeKeyword() in keywords.js.
*
* @param string keyword  The trope the user wants to remove
*/

TropesLib.removeTrope = function(keyword)
{
   var tropes = $('input[type=hidden][name="tropes"]').val();
   var tropesArr = tropes.split('|');
   
   if(tropesArr.length === 1)
   {
      if(tropes === keyword)
      {
         $('input[type=hidden][name="tropes"]').val('');
         $('.tropesList').html('');
      }
      return;
   }
   
   var guardian = true;
   var newTropes = '';
   var newTropesList = '';
   for(i = 0; i < tropesArr.length; i++)
   {
      if(tropesArr[i] === keyword)
         continue;
   
      if(!guardian)
      {
         newTropes += '|';
         newTropesList += ' ';
      }
      else
         guardian = false;
   
      var deleteButton = ' <a onclick="javascript:TropesLib.removeTrope(\'';
      deleteButton += tropesArr[i] + '\')" class="deleteKeyword">';
      deleteButton += '<i class="icon-general_trash" title="Supprimer ce code"></i></a>';
      
      newTropes += tropesArr[i];
      newTropesList += tropesArr[i] + deleteButton;
   }
   $('input[type=hidden][name="tropes"]').val(newTropes);
   $('.tropesList').html(newTropesList + " <br/>\n<br/>\n");
}

/*
* searchGames() takes the value which is currently in an input field named "selectGame" and 
* sends it with AJAX to a PHP script which will send back a list of suggestions of games matching 
* the input string (or via alias system) or a single line telling no such game exists. There is no
* argument needded and no return value. The code is very similar to searchGames in games.js, and 
* only differs by the click event that is bound to the suggestions.
*/

TropesLib.searchGames = function()
{
   var needle = $('input[type=text][name="selectGame"]').val();
   if(!needle || needle.length === 0)
   {
      $('#gamesSuggestions').html('');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FindGames.php', 
   data: 'keyword='+needle,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      $('#gamesSuggestions').html(text);
      
      // Binds click events
      $('#gamesSuggestions #suggestionsList li a').each(function()
      {
         $(this).on('click', function()
         {
            TropesLib.addGameTropes($(this).attr('data-game'));
         });
      });
      
      TropesLib.nbSugg = parseInt($('#gamesSuggestions #suggestionsList').attr('data-sugg'));
      TropesLib.selectedIndex = 0; // First element always selected
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* addGameTropes() takes an input game (as a string) then retrives via AJAX the tropes associated 
* to it (5 most popular) and adds it in an hidden field which will be later used by a PHP script 
* to take account of 1 to 10 tropes. It also displays it in a list with deletion buttons.
*
* @param string game  The game which tropes must be added
*/

TropesLib.addGameTropes = function(game)
{
   $('#gamesSuggestions').html('');
   $('input[type=text][name="selectGame"]').val('');
   if(game.length === 0)
   {
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/GetGameTropes.php', 
   data: 'game='+game,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      
      if(text.length === 0)
         return;
      
      // Get current tropes
      var tropes = $('input[type=hidden][name="tropes"]').val();
      var tropesArr = tropes.split('|');
      var tropesList = $('.tropesList').html();
      
      // Does nothing if the list already contains 10 tropes
      if(tropesArr.length >= 10)
         return;
      
      // Removes the "<br/><br/>"
      if(tropesList.length > 0)
      {
         var lastSpace = tropesList.lastIndexOf(" ");
         tropesList = tropesList.substr(0, lastSpace);
      }
      
      // Start handling new tropes
      var newTropes = text.split('|');
      var initNbTropes = tropesArr.length;
      var nbTropes = tropesArr.length;
      var finalList = tropes;
      var finalListRender = tropesList;
      for(i = 0; i < newTropes.length; i++)
      {
         var tropeNotPresent = true;
         for(j = 0; j < tropesArr.length; j++)
         {
            if(tropesArr[j] === newTropes[i])
            {
               tropeNotPresent = false;
               break;
            }
         }
         
         if(tropeNotPresent)
         {
            var deleteButton = ' <a onclick="javascript:TropesLib.removeTrope(\'';
            deleteButton += newTropes[i] + '\')" class="deleteKeyword">'; // Same CSS class as for keywords
            deleteButton += '<i class="icon-general_trash" title="Supprimer ce code"></i></a>';
            
            nbTropes++;
            if(finalListRender.length > 0)
            {
               finalList += '|';
               finalListRender += ' ';
            }
            finalList += newTropes[i];
            finalListRender += newTropes[i] + deleteButton;
            if(nbTropes >= 10)
               break;
         }
      }
      
      // Updates display if and only if at least one new trope has been added
      if(nbTropes > initNbTropes)
      {
         finalListRender += " <br/>\n<br/>\n";
         $('input[type=hidden][name="tropes"]').val(finalList);
         $('.tropesList').html(finalListRender);
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Binds the events.

$(document).ready(function()
{
   /* 
   * Code which listens to keystrokes; it waits 0.5s after the last key press before calling the 
   * autocomplete function which will later displays suggestions. It is worth noting that this 
   * code ignores key presses on Esc/Up/Down to avoid repop of the suggestions (Esc clears the 
   * pop-up) or backtrack of the selector (due to the suggestions being refreshed). It also 
   * returns false for Up/Down for keypress and keyup event to avoid moving the cursor in the 
   * input field while navigating in the suggestions.
   */

   $('input[type=text][name="trope"]').keypress(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="trope"]').keyup(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="trope"]').keydown(function(e)
   {
      var code = e.keyCode;
      if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
      {
         clearTimeout($.data(this, 'timer'));
         var keystrokeEnd = setTimeout(TropesLib.searchTropes, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });
   
   /* 
   * Code responsible for watching when the user gets in the suggestions zone and gets out of it; 
   * this behavior makes the suggestions list disappear (because the user, most likely, did not 
   * find a satisfying suggestion).
   */

   $('#tropesSuggestions').mouseenter(function()
   {
      TropesLib.hoverSugg = true;
   });
   
   $('#tropesSuggestions').mouseleave(function()
   {
      if(TropesLib.hoverSugg)
      {
         $('#tropesSuggestions').html('');
         TropesLib.hoverSugg = false;
      }
   });
   
   /*
   * Code responsible for checking whether there is the option of inputting tropes by selecting a 
   * (similar) game, and binding the related events.
   */
   
   if($('input[type=radio][name="form_type"]').length)
   {
      // Switching between each type of trope selection
      $('input[type=radio][name="form_type"][value="by_trope"]').on('click', function()
      {
         $('#by_similar_game').hide();
         $('#by_trope').show();
      });
      
      $('input[type=radio][name="form_type"][value="by_similar_game"]').on('click', function()
      {
         $('#by_trope').hide();
         $('#by_similar_game').show();
      });
      
      // Searching for games
      $('input[type=text][name="selectGame"]').keydown(function(e)
      {
         var code = e.keyCode;
         if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
         {
            clearTimeout($.data(this, 'timer'));
            var keystrokeEnd = setTimeout(TropesLib.searchGames, 500);
            $(this).data('timer', keystrokeEnd);
         }
      });
   }
});


/*
* Handles pressing Up/Down/Enter/Esc keys in order to navigate through the suggestions with the 
* keyboard only (for convenience).
*/

$(document).keypress(function(e)
{
   if(TropesLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Avoids submitting the whole form on pressing Enter
});

$(document).keyup(function(e)
{
   if(TropesLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Same (for cross browser compatibility)
});

$(document).keydown(function(e)
{
   if(TropesLib.nbSugg > 0)
   {
      var listBeingShown = '#tropesSuggestions';
      if($('#gamesSuggestions').length && !$('#gamesSuggestions').is(':empty'))
         listBeingShown = '#gamesSuggestions';
      if(e.keyCode === 13) // Enter
      {
         e.preventDefault();
         if(TropesLib.nbSugg === 1)
         {
            if((listBeingShown + ' a[data-kindex="0"]').length) // Same behavior as in games.js
            {
               var selectedItem = $(listBeingShown + ' a[data-kindex="0"]').html();
               if(listBeingShown === '#tropesSuggestions')
                  TropesLib.addTrope(selectedItem);
               else
                  TropesLib.addGameTropes(selectedItem);
            }
         }
         else
         {
            var selectedItem = $(listBeingShown + ' a[data-kindex="' + TropesLib.selectedIndex + '"]').html();
            if(listBeingShown === '#tropesSuggestions')
               TropesLib.addTrope(selectedItem);
            else
               TropesLib.addGameTropes(selectedItem);
         }
         $(listBeingShown).html('');
         TropesLib.selectedIndex = -1;
         TropesLib.nbSugg = 0;
         return false;
      }
      else if(e.keyCode === 27) // Escape
      {
         $(listBeingShown).html('');
         TropesLib.selectedIndex = -1;
         TropesLib.nbSugg = 0;
      }
      else if(e.keyCode === 38 && TropesLib.nbSugg > 1) // Up
      {
         if(TropesLib.selectedIndex > 0)
         {
            $(listBeingShown + ' a[data-kindex="' + TropesLib.selectedIndex + '"]').parent().attr('style', '');
            TropesLib.selectedIndex--;
            $(listBeingShown + ' a[data-kindex="' + TropesLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
      else if(e.keyCode === 40 && TropesLib.nbSugg > 1) // Down
      {
         if(TropesLib.selectedIndex < (TropesLib.nbSugg - 1))
         {
            $(listBeingShown + ' a[data-kindex="' + TropesLib.selectedIndex + '"]').parent().attr('style', '');
            TropesLib.selectedIndex++;
            $(listBeingShown + ' a[data-kindex="' + TropesLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
   }
});
