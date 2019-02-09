/**
* This file defines functions to get to one user's profile in a convenient way. "Convenient" 
* here means that the interface will provide pseudonym autocomplete after a small delay after a 
* keystroke. Upon selecting a suggestion, the current user is redirected to the selected user's 
* profile.
*/

var UsersLookUpLib = {};
UsersLookUpLib.hoverSugg = false; // Boolean which is true if the mouse is hovering the suggestions
UsersLookUpLib.selected = -1; // Suggestion being selected (-1 = no suggestion)
UsersLookUpLib.nbSugg = 0; // Amount of suggestions being shown

/*
* searchUsers() takes the value which is currently in an input field named "userInput" and send 
* it with AJAX to a PHP script which will send back a list of suggestions of users containing 
* the input string. There is no argument needed and no return value.
*/

UsersLookUpLib.searchUsers = function()
{
   var needle = $('input[type=text][name="userInput"]').val();
   if(!needle || needle.length === 0)
   {
      $('#usersSuggestions').html('');
      return;
   }

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FindUsers.php', 
   data: 'needle='+needle,
   timeout: 5000,
   success: function(text)
   {
      $('#usersSuggestions').html(text);
      UsersLookUpLib.nbSugg = parseInt($('#usersList').attr('data-sugg'));
      UsersLookUpLib.selected = 0; // First element always selected
   },
   error: function() {}
   });
}

/*
* selectUser() redirects to the edition page of some user, given a string corresponding to the 
* pseudonym of that user.
*
* @param string user  The user's pseudo
*/

UsersLookUpLib.selectUser = function(user)
{
   $('#usersSuggestions').html('');
   
   if(user.substring(0, 17) !== "Aucun utilisateur")
      window.location.href = DefaultLib.httpPath + 'EditUser.php?user=' + user;
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

   $('input[type=text][name="userInput"]').keypress(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="userInput"]').keyup(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="userInput"]').keydown(function(e)
   {
      var code = e.keyCode;
      if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
      {
         clearTimeout($.data(this, 'timer'));
         var keystrokeEnd = setTimeout(searchUsers, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });
   
   /*
   * Code responsible for watching when the user gets in the suggestions zone and gets out of it; 
   * this behavior makes the suggestions list disappear (because the user, most likely, did not 
   * find a satisfying suggestion).
   */
   
   $('#usersSuggestions').mouseenter(function()
   {
      UsersLookUpLib.hoverSugg = true;
   });
   
   $('#usersSuggestions').mouseleave(function()
   {
      if(UsersLookUpLib.hoverSugg)
      {
         $('#usersSuggestions').html('');
         UsersLookUpLib.hoverSugg = false;
      }
   });
});

// Handles Esc/Enter/Up/Down key presses

$(document).keypress(function(e)
{
   if(UsersLookUpLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Avoids any form on pressing Enter
});

$(document).keyup(function(e)
{
   if(UsersLookUpLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Same (for cross browser compatibility)
});

$(document).keydown(function(e)
{
   if(UsersLookUpLib.nbSugg > 0)
   {
      if(e.keyCode === 13) // Enter
      {
         if(UsersLookUpLib.nbSugg === 1)
         {
            var selectedUser = $('a[data-kindex="0"]').html();
            selectUser(selectedUser);
         }
         else
         {
            var selectedUser = $('a[data-kindex="' + UsersLookUpLib.selected + '"]').html();
            selectUser(selectedUser);
         }
         $('#usersSuggestions').html('');
         UsersLookUpLib.selected = -1;
         UsersLookUpLib.nbSugg = 0;
         return false;
      }
      else if(e.keyCode === 27) // Escape
      {
         $('#usersSuggestions').html('');
         UsersLookUpLib.selected = -1;
         UsersLookUpLib.nbSugg = 0;
      }
      else if(e.keyCode === 38 && UsersLookUpLib.nbSugg > 1) // Up
      {
         if(UsersLookUpLib.selected > 0)
         {
            $('a[data-kindex="' + UsersLookUpLib.selected + '"]').parent().attr('style', '');
            UsersLookUpLib.selected--;
            $('a[data-kindex="' + UsersLookUpLib.selected + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
      else if(e.keyCode === 40 && UsersLookUpLib.nbSugg > 1) // Down
      {
         if(UsersLookUpLib.selected < (UsersLookUpLib.nbSugg - 1))
         {
            $('a[data-kindex="' + UsersLookUpLib.selected + '"]').parent().attr('style', '');
            UsersLookUpLib.selected++;
            $('a[data-kindex="' + UsersLookUpLib.selected + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
   }
});
