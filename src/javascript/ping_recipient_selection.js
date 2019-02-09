/**
* This file defines functions used to elegantly pick a recipient while creating a ping. It is 
* pretty much identical to user_input.js, therefore most comments have been removed. The main 
* difference is the behavior of the selectUser() function.
*/

var PingRecipientLib = {};
PingRecipientLib.hoverSugg = false;
PingRecipientLib.selected = -1;
PingRecipientLib.nbSugg = 0;

// Sends an AJAX request to look for users matching the "userInput" field (after a keystroke)

PingRecipientLib.searchUsers = function()
{
   var needle = $('input[type=text][name="userInput"]').val();
   if(!needle || needle.length === 0)
   {
      $('#usersSuggestions').html('');
      return;
   }
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FindUsers.php', 
   data: 'needle='+needle,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      $('#usersSuggestions').html(text);
      PingRecipientLib.nbSugg = parseInt($('#usersList').attr('data-sugg'));
      PingRecipientLib.selected = 0; // First element always selected
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* selectUser() replaces the content of <span class="recipientSelection"></span> in the ping page 
* by a single statement with the selected recipient. The hidden input field of the same name is, 
* of course, also edited in the process.
*
* @param string user  The user's pseudo
*/

PingRecipientLib.selectUser = function(user)
{
   $('#usersSuggestions').html('');
   
   if(user.substring(0, 17) !== "Aucun utilisateur")
   {
      $('input[type=hidden][name="recipient"]').attr('value', user);
      $('.recipientSelection').html('<strong>Destinataire:</strong> ' + user);
   }
}

// Binds the events

$(document).ready(function()
{
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
         var keystrokeEnd = setTimeout(PingRecipientLib.searchUsers, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });

   $('#usersSuggestions').mouseenter(function()
   {
      PingRecipientLib.hoverSugg = true;
   });
   
   $('#usersSuggestions').mouseleave(function()
   {
      if(PingRecipientLib.hoverSugg)
      {
         $('#usersSuggestions').html('');
         PingRecipientLib.hoverSugg = false;
      }
   });
});

// Handles Esc/Enter/Up/Down key presses

$(document).keypress(function(e)
{
   if(PingRecipientLib.nbSugg > 0 && e.keyCode === 13)
      return false;
});

$(document).keyup(function(e)
{
   if(PingRecipientLib.nbSugg > 0 && e.keyCode === 13)
      return false;
});

$(document).keydown(function(e){
   if(PingRecipientLib.nbSugg > 0)
   {
      if(e.keyCode === 13) // Enter
      {
         if(PingRecipientLib.nbSugg === 1)
         {
            var selectedUser = $('a[data-kindex="0"]').html();
            PingRecipientLib.selectUser(selectedUser);
         }
         else
         {
            var selectedUser = $('a[data-kindex="' + PingRecipientLib.selected + '"]').html();
            PingRecipientLib.selectUser(selectedUser);
         }
         $('#usersSuggestions').html('');
         PingRecipientLib.selected = -1;
         PingRecipientLib.nbSugg = 0;
         return false;
      }
      else if(e.keyCode === 27) // Escape
      {
         $('#usersSuggestions').html('');
         PingRecipientLib.selected = -1;
         PingRecipientLib.nbSugg = 0;
      }
      else if(e.keyCode === 38 && PingRecipientLib.nbSugg > 1) // Up
      {
         if(PingRecipientLib.selected > 0)
         {
            $('a[data-kindex="' + PingRecipientLib.selected + '"]').parent().attr('style', '');
            PingRecipientLib.selected--;
            $('a[data-kindex="' + PingRecipientLib.selected + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
      else if(e.keyCode === 40 && PingRecipientLib.nbSugg > 1) // Down
      {
         if(PingRecipientLib.selected < (PingRecipientLib.nbSugg - 1))
         {
            $('a[data-kindex="' + PingRecipientLib.selected + '"]').parent().attr('style', '');
            PingRecipientLib.selected++;
            $('a[data-kindex="' + PingRecipientLib.selected + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
   }
});
