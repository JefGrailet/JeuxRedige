/**
* This file defines functions to input keywords in a convenient way. "Convenient" here means that
* the interface will provide keywords autocomplete after a small delay after a keystroke, and
* warn the user when he or she is about to input a completely new keyword. In addition to the 
* autocomplete, the code also provides everything to manage the list of keywords provided by the 
* user.
*/

var KeywordsLib = {};
KeywordsLib.hoverSugg = false; // Boolean set to true if the mouse is hovering the suggestions
KeywordsLib.selectedIndex = -1; // Suggestion being selected (-1 = no suggestion)
KeywordsLib.nbSugg = 0; // Amount of suggestions being shown

/*
* searchKeywords() takes the value which is currently in an input field named "keyword" and sends 
* it with AJAX to a PHP script which will send back a list of suggestions of keywords 
* containing what was in the field, or a single suggestion to create a new keyword.
*/

KeywordsLib.searchKeywords = function()
{
   var needle = $('input[type=text][name="keyword"]').val();
   if(!needle || needle.length === 0)
   {
      $('#keywordsSuggestions').html('');
      return;
   }
   
   var canCreate = $('input[type=text][name="keyword"]').attr('data-creation');
   if(typeof canCreate !== typeof undefined && canCreate !== false)
	   needle += '&creation=yes';
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FindKeywords.php', 
   data: 'keyword='+needle,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      $('#keywordsSuggestions').html(text);
      KeywordsLib.nbSugg = parseInt($('#keywordsSuggestions #suggestionsList').attr('data-sugg'));
      KeywordsLib.selectedIndex = 0; // First element always selected
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* closeSuggestions() empties the #keywordsSuggestions container. It is used for field listening 
* for keywords with which one cannot create a keyword (e.g. Search.php).
*/

KeywordsLib.closeSuggestions = function()
{
	$('#keywordsSuggestions').html('');
	$('input[type=text][name="keyword"]').val('');
}

/*
* addKeyword() takes an input keyword and adds it in an hidden field which will be later used by a 
* PHP script to register 1 to 10 keywords. It also displays it in a list with deletion buttons. 
* 2 characters must be escaped (" and |) and the length of the keyword is limited to 100 
* characters, so the keyword is reduced if it is too long. Also, if the keyword in argument is 
* null, we simply take the value of the field "keyword" (i.e. this means the keyword being written 
* is completely new).
*
* @param string keyword  The new keyword
*/

KeywordsLib.addKeyword = function(keyword)
{
   $('#keywordsSuggestions').html('');
   if(keyword.length === 0)
   {
      keyword = $('input[type=text][name="keyword"]').val();
   }
   $('input[type=text][name="keyword"]').val('');
   
   keyword = keyword.split('"').join('');
   keyword = keyword.split('|').join('');
   if(keyword.length > 100) // Max length for a keyword: 100 characters
      keyword = keyword.substring(0, 100);

   var keywords = $('input[type=hidden][name="keywords"]').val();
   var keywordsArr = keywords.split('|');
   var keywordsList = $('.keywordsList').html();
   
   // Removes the "<br/><br/>"
   if(keywordsList.length > 0)
   {
      var lastSpace = keywordsList.lastIndexOf(" ");
      keywordsList = keywordsList.substr(0, lastSpace);
   }
   
   var keywordNotPresent = true;
   for(i = 0; i < keywordsArr.length; i++)
   {
      if(keywordsArr[i] === keyword)
      {
         keywordNotPresent = false;
         break;
      }
   }
   
   if(keywords.length === 0 || keywordNotPresent)
   {
      var deleteButton = ' <a onclick="javascript:KeywordsLib.removeKeyword(\'';
      deleteButton += DefaultLib.addslashes(keyword) + '\')" class="deleteKeyword">';
      deleteButton += '<img src="' + DefaultLib.httpPath + 'res_icons/delete.png" alt="Delete" ';
      deleteButton += 'title="Supprimer ce mot-clef"/></a>';
      if(keywords.length === 0)
      {
         $('input[type=hidden][name="keywords"]').val(keyword);
         
         var addition = keyword + deleteButton + " <br/>\n<br/>\n";
         $('.keywordsList').html(addition);
      }
      else if(keywordsArr.length < 10)
      {
         $('input[type=hidden][name="keywords"]').val(keywords + '|' + keyword);
         
         var addition = keywordsList + ' ' + keyword + deleteButton + " <br/>\n<br/>\n";
         $('.keywordsList').html(addition);
      }
   }
}

/*
* removeKeyword(), as the name suggests, removes a keyword from the list previously built by the 
* user. It operates on both the hidden list and the displayed list. The method is simple: the 
* hidden list is split as an array, and the lists are recomputed ignoring the selected keyword. 
* The only exceptional case is where the list contains only one keyword; in that case, we just 
* compare it to the given keyword to decide whether or not we wipe away both lists.
*
* @param string keyword  The keyword the user wants to remove
*/

KeywordsLib.removeKeyword = function(keyword)
{
   var keywords = $('input[type=hidden][name="keywords"]').val();
   var keywordsArr = keywords.split('|');
   
   if(keywordsArr.length === 1)
   {
      if(keywords === keyword)
      {
         $('input[type=hidden][name="keywords"]').val('');
         $('.keywordsList').html('');
      }
      return;
   }
   
   var guardian = true;
   var newKeywords = '';
   var newKeywordsList = '';
   for(i = 0; i < keywordsArr.length; i++)
   {
      if(keywordsArr[i] === keyword)
         continue;
   
      if(!guardian)
      {
         newKeywords += '|';
         newKeywordsList += ' ';
      }
      else
         guardian = false;
   
      var deleteButton = ' <a onclick="javascript:KeywordsLib.removeKeyword(\'';
      deleteButton += keywordsArr[i] + '\')" class="deleteKeyword">';
      deleteButton += '<img src="' + DefaultLib.httpPath + 'res_icons/delete.png" alt="Delete" ';
      deleteButton += 'title="Supprimer ce mot-clef"/></a>';
      
      newKeywords += keywordsArr[i];
      newKeywordsList += keywordsArr[i] + deleteButton;
   }
   $('input[type=hidden][name="keywords"]').val(newKeywords);
   $('.keywordsList').html(newKeywordsList + " <br/>\n<br/>\n");
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

   $('input[type=text][name="keyword"]').keypress(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="keyword"]').keyup(function(e)
   {
      var code = e.keyCode;
      if(code === 38 || code === 40)
         return false;
   });
   
   $('input[type=text][name="keyword"]').keydown(function(e)
   {
      var code = e.keyCode;
      if(code !== 13 && code !== 27 && code !== 38 && code !== 40)
      {
         clearTimeout($.data(this, 'timer'));
         var keystrokeEnd = setTimeout(KeywordsLib.searchKeywords, 500);
         $(this).data('timer', keystrokeEnd);
      }
   });
   
   /* 
   * Code responsible for watching when the user gets in the suggestions zone and gets out of it; 
   * this behavior makes the suggestions list disappear (because the user, most likely, did not 
   * find a satisfying suggestion).
   */

   $('#keywordsSuggestions').mouseenter(function()
   {
      KeywordsLib.hoverSugg = true;
   });
   
   $('#keywordsSuggestions').mouseleave(function()
   {
      if(KeywordsLib.hoverSugg)
      {
         $('#keywordsSuggestions').html('');
         KeywordsLib.hoverSugg = false;
      }
   });
});


/*
* Handles pressing Up/Down/Enter/Esc keys in order to navigate through the suggestions with the 
* keyboard only (for convenience).
*/

$(document).keypress(function(e)
{
   if(KeywordsLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Avoids submitting the whole form on pressing Enter
});

$(document).keyup(function(e)
{
   if(KeywordsLib.nbSugg > 0 && e.keyCode === 13)
      return false; // Same (for cross browser compatibility)
});

$(document).keydown(function(e)
{
   if(KeywordsLib.nbSugg > 0)
   {
      if(e.keyCode === 13) // Enter
      {
         e.preventDefault();
         if(KeywordsLib.nbSugg === 1)
         {
            var isNew = $('#keywordsSuggestions a[data-kindex="0"]').attr('data-new');
            // The data-new field exists: keyword does not exist in the DB
            if(typeof isNew !== typeof undefined && isNew !== false)
            {
                if(isNew === 'yes')
                   KeywordsLib.addKeyword(''); // New keyword
                else
                   KeywordsLib.closeSuggestions(); // "Keyword doesn't exist"
            }
            // Otherwise, existing keyword
            else
            {
               var keywordToAdd = $('#keywordsSuggestions a[data-kindex="0"]').html();
               KeywordsLib.addKeyword(keywordToAdd);
            }
         }
         else
         {
            var keywordToAdd = $('#keywordsSuggestions a[data-kindex="' + KeywordsLib.selectedIndex + '"]').html();
            KeywordsLib.addKeyword(keywordToAdd);
         }
         KeywordsLib.selectedIndex = -1;
         KeywordsLib.nbSugg = 0;
         return false;
      }
      else if(e.keyCode === 27) // Escape
      {
         $('#keywordsSuggestions').html('');
         KeywordsLib.selectedIndex = -1;
         KeywordsLib.nbSugg = 0;
      }
      else if(e.keyCode === 38 && KeywordsLib.nbSugg > 1) // Up
      {
         if(KeywordsLib.selectedIndex > 0)
         {
            $('#keywordsSuggestions a[data-kindex="' + KeywordsLib.selectedIndex + '"]').parent().attr('style', '');
            KeywordsLib.selectedIndex--;
            $('#keywordsSuggestions a[data-kindex="' + KeywordsLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
      else if(e.keyCode === 40 && KeywordsLib.nbSugg > 1) // Down
      {
         if(KeywordsLib.selectedIndex < (KeywordsLib.nbSugg - 1))
         {
            $('#keywordsSuggestions a[data-kindex="' + KeywordsLib.selectedIndex + '"]').parent().attr('style', '');
            KeywordsLib.selectedIndex++;
            $('#keywordsSuggestions a[data-kindex="' + KeywordsLib.selectedIndex + '"]').parent().attr('style', 'background-color: rgb(230,230,230);');
         }
      }
   }
});
