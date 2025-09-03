/**
* This file defines various functions to handle pins in a covenient fashion (with two operations: 
* pin deletion and pin comment edition).
*/

var PinsLib = {};

// Unpins the corresponding message.

PinsLib.deletePin = function(pinID)
{
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/PinPost.php', // We re-use the same script as in post_interaction.js for convenience
   data: 'id_post='+pinID,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var pinObject = '.pinBlock[data-id-pin=' + pinID + ']';
      var nbPins = $('.pinBlock').length;
      if(data === 'Unpinned')
      {
         if(nbPins > 1)
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).remove();
            });
         }
         else
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith("<p class=\"poolError\">Plus de favori !</p>");
            });
         }
      }
      else if(data === "Not pinned")
      {
         alert('Ce message n\'était pas dans vos favoris.');
         
         if(nbPins > 1)
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith();
            });
         }
         else
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith("<p class=\"poolError\">Plus de favori !</p>");
            });
         }
      }
      else
      {
         alert('Une erreur est survenue lors de la suppression du favori. Réessayez plus tard.');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Replaces a comment by a quick form to edit it.

PinsLib.loadCommentEdition = function(pinID)
{
   var commentStr = $('.pinBlock[data-id-pin=' + pinID + '] .comment').html();
   var form = "<input type=\"text\" name=\"newComment\" value=\"" + commentStr + "\"/> <button>Modifier</button>";
   
   $('.pinBlock[data-id-pin=' + pinID + '] .comment').replaceWith(form);
   
   // Two events to take into account: press on Enter key and button click
   $('.pinBlock[data-id-pin=' + pinID + '] input[type="text"]').bind("enterKey", function(e)
   {
      var ID = $(this).closest('.pinBlock').attr('data-id-pin');
      PinsLib.editComment(ID);
   });
   
   $('.pinBlock[data-id-pin=' + pinID + '] input[type="text"]').keyup(function(e)
   {
       if(e.keyCode == 13)
       {
           $(this).trigger("enterKey");
       }
   });
   
   // Button click
   $('.pinBlock[data-id-pin=' + pinID + '] button').on('click', function()
   {
      var ID = $(this).closest('.pinBlock').attr('data-id-pin');
      PinsLib.editComment(ID);
   });
}

// Actually carries out the comment edition with an AJAX request.

PinsLib.editComment = function(pinID)
{
   var pinComment = $('.pinBlock[data-id-pin=' + pinID + '] input[type="text"]').val();

   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/EditPinComment.php', // We re-use the same script as in post_interaction.js for convenience
   data: 'id_post='+pinID+'&comment='+pinComment,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      if(data === 'OK')
      {
         // Updates the display
         var newComment = "<span class=\"comment\">" + pinComment + "</span>";
         $('.pinBlock[data-id-pin=' + pinID + '] input[type="text"]').replaceWith(newComment);
         $('.pinBlock[data-id-pin=' + pinID + '] button').remove();
         
         // Re-binds the edition event
         $('.pinBlock[data-id-pin=' + pinID + '] .comment').on('click', function()
         {
            PinsLib.loadCommentEdition($(this).closest('.pinBlock').attr('data-id-pin'));
         });
      }
      // Removes the pin if it did not exist
      else if(data === 'Not pinned')
      {
         alert('Ce message n\'était pas dans vos favoris.');
         
         var pinObject = '.pinBlock[data-id-pin=' + pinID + ']';
         var nbPins = $('.pinBlock').length;
         if(nbPins > 1)
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith();
            });
         }
         else
         {
            $(pinObject).animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith("<p class=\"poolError\">Plus de favori !</p>");
            });
         }
      }
      else
      {
         alert('Une erreur est survenue lors de l\'édition du commentaire. Réessayez plus tard.');
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
   $('.pinDelete').on('click', function()
   {
      PinsLib.deletePin($(this).closest('.pinBlock').attr('data-id-pin'));
   });
   
   $('.comment').on('click', function()
   {
      PinsLib.loadCommentEdition($(this).closest('.pinBlock').attr('data-id-pin'));
   });
});
