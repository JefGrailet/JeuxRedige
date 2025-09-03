/**
* This file defines functions to input a game title in a convenient way. "Convenient" here means 
* that the interface will provide title autocomplete after a small delay after a keystroke and 
* save the result on click/press on Enter, and warn the user when no such game exists in the 
* database.
*/

var GamesLib = {};
GamesLib.hoverSugg = false; // Boolean set to true if the mouse is hovering the suggestions
GamesLib.selectedIndex = -1; // Suggestion being selected (-1 = no suggestion)
GamesLib.nbSugg = 0; // Amount of suggestions being shown

/*
* searchGames() takes the value which is currently in an input field named "selectGame" and 
* sends it with AJAX to a PHP script which will send back a list of suggestions of games matching 
* the input string (or via alias system) or a single line telling no such game exists. There is no
* argument needded and no return value.
*/

GamesLib.searchGames = function()
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
            GamesLib.selectGame($(this).attr('data-game'));
         });
      });
      
      GamesLib.nbSugg = parseInt($('#gamesSuggestions #suggestionsList').attr('data-sugg'));
      GamesLib.selectedIndex = 0; // First element always selected
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* selectGame() takes an input keyword and puts it in an hidden field which will be later used by a 
* PHP script to get the game title to be used. It also updates the display and provide a rollback 
* button to get back to the selection.
*
* @param string keyword  The game title
*/

GamesLib.selectGame = function(keyword)
{
   $('#gamesSuggestions').html('');
   if(keyword.length === 0)
   {
      keyword = $('input[type=text][name="selectGame"]').val();
   }
   $('input[type=text][name="selectGame"]').val('');
   
   keyword = keyword.split('"').join('');
   keyword = keyword.split('|').join('');
   if(keyword.length > 100) // Max length for any tag: 100 characters
      keyword = keyword.substring(0, 100);
   $('input[type=hidden][name="game"]').val(keyword);
   
   // Updates display
   var selectedGame = "<span id=\"gameSelected\"><strong>Jeu sélectionné:</strong> ";
   selectedGame += keyword + " (<a href=\"javascript:void(0)\" id=\"rollbackGame\">changer</a>)</span>";
   
   $('#gameToSelect').replaceWith(selectedGame);
   $('#rollbackGame').on('click', GamesLib.rollback);
}

/*
* Binds the events. It's a separate function from $(document).ready() due to the need of calling 
* the same code after putting back the selection form (after a call to rollback()).
*/

GamesLib.bindEvents = function()
{
   /*
   * Code which listens to keystrokes; it waits 0.5s after the last key press before calling the 
   * autocomplete function which will later displays suggestions. It is worth noting that this 
   * code ignores key presses on Esc/Up/Down to avoid repop of the suggestions (Esc clears the 
   * pop-up) or backtrack of the selector (due to the suggestions being refreshed). It also 
   * returns false for Up/Down for keypress and keyup event to avoid moving the cursor in the 
   * input field while navigating in the suggestions.
   */

   $('input[type=text][name="selectGame"]').keypress(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="selectGame"]').keyup(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="selectGame"]').keydown(function(e)
   {
      var code = e.keyCode;
      if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
      {
         clearTimeout($.data(this, 'timer'));
         var keystrokeEnd = setTimeout(GamesLib.searchGames, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });
   
   /* 
   * Code responsible for watching when the user gets in the suggestions zone and gets out of it; 
   * this behavior makes the suggestions list disappear (because the user, most likely, did not 
   * find a satisfying suggestion).
   */

   $('#gamesSuggestions').mouseenter(function()
   {
      GamesLib.hoverSugg = true;
   });
   
   $('#gamesSuggestions').mouseleave(function()
   {
      if(GamesLib.hoverSugg)
      {
         $('#gamesSuggestions').html('');
         GamesLib.hoverSugg = false;
      }
   });
}

/*
* rollback(), as the name suggests, undoes the game title selection and puts back the selection 
* input text field to select a new game title. No parameter is required.
*/

GamesLib.rollback = function()
{
   $('input[type=hidden][name="game"]').val('');
   
   var renewedForm = '<span id="gameToSelect">' + "\n";
   renewedForm += '<input type="text" name="selectGame" size="60" maxlength="100" ';
   renewedForm += 'placeholder="Entrez un jeu (répertorié dans la base de données)" ';
   renewedForm += 'autocomplete="off"/>' + "\n";
   renewedForm += '<span class="suggestions" id="gamesSuggestions">' + "\n";
   renewedForm += '</span>' + "\n";
   renewedForm += '</span>';
   
   $('#gameSelected').replaceWith(renewedForm);
   GamesLib.bindEvents();
}

// Binds the events.

$(document).ready(function()
{
   GamesLib.bindEvents();
});

/*
* Handles pressing Up/Down/Enter/Esc keys in order to navigate through the suggestions with the 
* keyboard only (for convenience).
*/

$(document).keypress(function(e)
{
   if(GamesLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Avoids submitting the whole form on pressing Enter
});

$(document).keyup(function(e)
{
   if(GamesLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Same (for cross browser compatibility)
});

$(document).keydown(function(e)
{
   if(GamesLib.nbSugg > 0)
   {
      if(e.keyCode === 13) // Enter
      {
         e.preventDefault();
         if(GamesLib.nbSugg === 1)
         {
            if(('#gamesSuggestions a[data-kindex="0"]').length)
            {
               var game = $('#gamesSuggestions a[data-kindex="0"]').html();
               GamesLib.selectGame(game);
            }
         }
         else
         {
            var game = $('#gamesSuggestions a[data-kindex="' + GamesLib.selectedIndex + '"]').html();
            GamesLib.selectGame(game);
         }
         $('#gamesSuggestions').html('');
         GamesLib.selectedIndex = -1;
         GamesLib.nbSugg = 0;
         return false;
      }
      else if(e.keyCode === 27) // Escape
      {
         $('#gamesSuggestions').html('');
         GamesLib.selectedIndex = -1;
         GamesLib.nbSugg = 0;
      }
      else if(e.keyCode === 38 && GamesLib.nbSugg > 1) // Up
      {
         if(GamesLib.selectedIndex > 0)
         {
            $('#gamesSuggestions a[data-kindex="' + GamesLib.selectedIndex + '"]').parent().attr('style', '');
            GamesLib.selectedIndex--;
            $('#gamesSuggestions a[data-kindex="' + GamesLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
      else if(e.keyCode === 40 && GamesLib.nbSugg > 1) // Down
      {
         if(GamesLib.selectedIndex < (GamesLib.nbSugg - 1))
         {
            $('#gamesSuggestions a[data-kindex="' + GamesLib.selectedIndex + '"]').parent().attr('style', '');
            GamesLib.selectedIndex++;
            $('#gamesSuggestions a[data-kindex="' + GamesLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
   }
});
