/**
* This file handles interaction with lists.
*/

var ListInteractionLib = {};

// Moves an item, given its current rank and a "direction" (>= 0 means up, down otherwise).

ListInteractionLib.moveItem = function(rankToMove, direction)
{
   var rank1 = rankToMove;
   var itemID1 = parseInt($('.itemContainer[data-item-rank=' + rankToMove + ']').attr('data-item-id'));
   if(itemID1 == null)
      return;
   
   var rank2 = -1;
   var itemID2 = - 1;
   if(direction >= 0)
      rank2 = rankToMove - 1;
   else
      rank2 = rankToMove + 1;
   itemID2 = parseInt($('.itemContainer[data-item-rank=' + rank2 + ']').attr('data-item-id'));
   
   if(itemID2 == null || rank2 <= 0)
      return;
   
   var listID = parseInt($('#listTitle').attr('data-list-id'));
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/SwitchListItems.php', 
   data: 'id_list=' + listID + '&id_item1=' + itemID1 + '&id_item2=' + itemID2,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      
      if(text === "OK")
      {
         // Switches the buttons to move up/down and delete
         var buttons1 = $('.itemContainer[data-item-rank=' + rank1 + '] .itemInteractivity');
         var buttons2 = $('.itemContainer[data-item-rank=' + rank2 + '] .itemInteractivity');
         
         var clone1 = buttons1.clone();
         var clone2 = buttons2.clone();
         
         buttons1.replaceWith(clone2);
         buttons2.replaceWith(clone1);
         
         // Switches the content of the items
         var item1Content = $('.itemContainer[data-item-rank=' + rank1 + ']').html();
         var item2Content = $('.itemContainer[data-item-rank=' + rank2 + ']').html();
         
         $('.itemContainer[data-item-rank=' + rank1 + ']').html(item2Content);
         $('.itemContainer[data-item-rank=' + rank2 + ']').html(item1Content);
         
         $('.itemContainer[data-item-rank=' + rank1 + ']').attr('data-item-id', itemID2);
         $('.itemContainer[data-item-rank=' + rank2 + ']').attr('data-item-id', itemID1);
         
         // If displayed, updates the <h2> displaying the rank of each item
         if($('.itemContainer[data-item-rank=' + rank1 + '] .listedGame h2').length)
         {
            $('.itemContainer[data-item-rank=' + rank1 + '] .listedGame h2').html(rank1);
            $('.itemContainer[data-item-rank=' + rank2 + '] .listedGame h2').html(rank2);
         }
         
         // Re-binds events
         $('.itemContainer[data-item-rank=' + rank1 + '] .moveItemUp').on('click', function() { ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), 1); });
         $('.itemContainer[data-item-rank=' + rank1 + '] .moveItemDown').on('click', function() { ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), -1); });
         $('.itemContainer[data-item-rank=' + rank1 + '] .deleteItem').on('click', function() { ListInteractionLib.deleteItem(parseInt($(this).closest('.itemContainer').attr('data-item-id'))); });
         
         $('.itemContainer[data-item-rank=' + rank2 + '] .moveItemUp').on('click', function() { ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), 1); });
         $('.itemContainer[data-item-rank=' + rank2 + '] .moveItemDown').on('click', function() { ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), -1); });
         $('.itemContainer[data-item-rank=' + rank2 + '] .deleteItem').on('click', function() { ListInteractionLib.deleteItem(parseInt($(this).closest('.itemContainer').attr('data-item-id'))); });
      }
      else
      {
         alert('Une erreur est survenue lors de la mise à jour. Réessayez plus tard ou prévenez un administrateur.');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Deletes an item based on its ID. A confirmation is requested beforehand.

ListInteractionLib.deleteItem = function(itemID)
{
   if(!confirm('Êtes-vous sûr de vouloir supprimer cet élément de la liste ?'))
     return;

   var posToRemove = parseInt($('.itemContainer[data-item-id=' + itemID + ']').attr('data-item-rank'));
   
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeleteListItem.php', 
   data: 'id_item=' + itemID,
   timeout: 5000,
   success: function(text)
   {
      if(text === 'OK')
      {
         $('.itemContainer[data-item-id=' + itemID + ']').hide('slow', function()
         {
            $('.itemContainer[data-item-id=' + itemID + ']').remove();
            if($('#listContent .itemContainer').length > 0) // If there remain items
            {
               $('.itemContainer').each(function()
               {
                  var curPos = parseInt($(this).attr('data-item-rank'));
                  if(curPos > posToRemove)
                  {
                     var newPosStr = (curPos - 1).toString();
                     if($(this).find('h2').length)
                        $(this).find('h2').html(newPosStr);
                     $(this).attr('data-item-rank', newPosStr);
                  }
               });
               
               //if($('tr:last .moveDown').length)
               //   $('tr:last .moveDown').remove();
            }
            else
            {
               $('#listContent').html('<p style="text-align: center;">Cette liste est actuellement vide.</p>');
            }
         });
      }
      else
         alert('Une erreur est survenue lors de la mise à jour. Réessayez plus tard ou prévenez un administrateur.');
      
      DefaultLib.doneWithAJAX();
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
   // Show list thumbnail on mouse over the title
   $('#listTitle').on('mouseover', function(e)
   {
      var thumb = $('#listThumbnail');
      var xInit = e.screenX, yInit = e.screenY;
      
      thumb.css('top', (yInit - 40) + 'px'); // -40 due to the top of the window of the browser
      thumb.css('left', xInit + 'px');
      thumb.show();

      window.onmousemove = function(e)
      {
         var x = e.screenX, y = e.screenY;
         thumb.css('top', (y - 40) + 'px');
         thumb.css('left', x + 'px');
      };
   });
   
   // Stops showing thumbnail
   $('#listTitle').on('mouseout', function(e)
   {
      $('#listThumbnail').hide();
   });
   
   // Ensures the "dynamic" parts of the formatting are working
   $('.itemBlock .spoiler a:first-child').on('click', function() { DefaultLib.showSpoiler($(this).attr('data-id-spoiler')); });
   $('.itemBlock .miniature').on('click', function() { DefaultLib.showUpload($(this)); });
   $('.itemBlock .videoThumbnail').on('click', function()
   {
      var index = $(this).attr('data-post-id');
      var videoId = $(this).attr('data-video-id');
      DefaultLib.showVideo(videoId, index);
   });
   
   // Moves an item up or down
   $('.moveItemUp').on('click', function()
   {
      ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), 1);
   });
   
   $('.moveItemDown').on('click', function()
   {
      ListInteractionLib.moveItem(parseInt($(this).closest('.itemContainer').attr('data-item-rank')), -1);
   });
   
   // Delete an item (author of the list only)
   $('.deleteItem').on('click', function()
   {
      ListInteractionLib.deleteItem(parseInt($(this).closest('.itemContainer').attr('data-item-id')));
   });
   
   // Rating process
   $('.ratings').each(function()
   {
      if($(this).attr('data-voting-allowed') === 'yes')
      {
         var blockID = $(this).parent().attr('id');
         $(this).find(' .relevantRatings .ratingsLeft p').on('click', function()
         {
            CommentablesLib.rate(blockID, 'relevant');
         });
         $(this).find(' .relevantRatings .ratingsLeft p').hover(function()
         {
            $(this).css('cursor','pointer');
         });
         
         $(this).find(' .irrelevantRatings .ratingsLeft p').on('click', function()
         {
            CommentablesLib.rate(blockID, 'irrelevant');
         });
         $(this).find(' .irrelevantRatings .ratingsLeft p').hover(function()
         {
            $(this).css('cursor','pointer');
         });
      }
   });
});
