/**
* This file defines functions and behaviors for the ping page, such as deleting archived pings 
* (such as notifications) or replying to friendship requests (coming soon).
*/

var PingInteractionLib = {};

PingInteractionLib.deletePing = function(pingID)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeletePing.php', 
   data: 'id_ping='+pingID,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
   
      /*
       * DeletePing returns a message in 3 parts, separated with "\n\n":
       * 1) Must be "OK" to continue.
       * 2) Actualized pages (if necessary).
       * 3) Ping(s) to append to the body of the page to refresh the list.
       */
      
      splitted = text.split("\n\n");
      if(splitted.length > 3)
      {
         for(i = 3; i < splitted.length; i++)
            splitted[2] += splitted[i];
      }
      
      if(splitted[0].localeCompare("OK") === 0)
      {
         $('.pingBlock[id="' + pingID + '"]').animate({opacity: 0}, 500, function()
         {
            $(this).remove();
            
            if(splitted[1].localeCompare("No change") !== 0 && splitted[1].localeCompare("Error") !== 0)
            {
               if(splitted[1].localeCompare("Emptied") === 0)
               {
                  $('.pingsNav .pages').html('');
               }
               else
               {
                  $('.pingsNav .pages').html(splitted[1]);
               }
            }
            
            if(splitted[2].localeCompare("None") !== 0 && splitted[2].localeCompare("Error") !== 0)
            {
               $('#listPings').append(splitted[2]);
               
               // Updates "on click" events
               $('.delete').on('click', function()
               {
                  ping = parseInt($(this).attr("data-ping"));
                  deletePing(ping);
               });
            }
            
            // Code that checks there are still ping blocks, otherwise it changes display
            if(!($('.pingBlock').length > 0))
            {
               $('#listPings').html("<p class=\"centeredText\">Vous n'avez actuellement aucun ping.</p>");
            }
         });
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

PingInteractionLib.checkPing = function(pingID)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/CheckPing.php', 
   data: 'id_ping='+pingID,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text === 'OK')
      {
         $('.check[data-ping="' + pingID + '"]').css('cursor', 'default');
         $('.check[data-ping="' + pingID + '"]').off();
         $('.check[data-ping="' + pingID + '"]').animate({opacity: 0}, 500, function()
         {
            $('.check[data-ping="' + pingID + '"]').remove();
         });
         $('.pingBlock[id="' + pingID + '"] .pingBlockText .pingBlockTextTop').attr('style', 'background-color: #0B6FC6;');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

// Binds the events

$(document).ready(function()
{
   $('.delete').on('click', function()
   {
      ping = parseInt($(this).attr("data-ping"));
      PingInteractionLib.deletePing(ping);
   });
   
   $('.check').on('click', function()
   {
      ping = parseInt($(this).attr("data-ping"));
      PingInteractionLib.checkPing(ping);
   });
   
   $('.spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      DefaultLib.showSpoiler(spoilerId);
   });
   
   $('.miniature').on('click', function()
   {
      DefaultLib.showUpload($(this));
   });
   
   $('.videoThumbnail').on('click', function()
   {
      var index = $(this).attr('data-post-id');
      var videoId = $(this).attr('data-video-id');
      DefaultLib.showVideo(videoId, index);
   });
   
   $('.quickForm').on('click', function()
   {
      if($('#slidingBlock').is(':visible'))
         $('#visibleWrapper').css('margin-bottom', 0);
      else
         $('#visibleWrapper').css('margin-bottom', $('#slidingBlock').height() - 100);
      $('#slidingBlock').slideToggle();
   });
});

// Handles press on CTRL to show/hide the quick reply form

$(document).keydown(function(e)
{
   if (e.keyCode == 32 && e.ctrlKey)
   {
      if($('#slidingBlock').is(':visible'))
         $('#visibleWrapper').css('margin-bottom', 0);
      else
         $('#visibleWrapper').css('margin-bottom', $('#slidingBlock').height() - 100);
      $('#slidingBlock').slideToggle();
   }
});
