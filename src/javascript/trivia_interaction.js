/**
* This file handles basic interaction with trivia (whether there are several blocks or only one).
*/

var TriviaInteractionLib = {};

// Gets the details on a piece which the ID is provided

TriviaInteractionLib.getDetails = function(triviaID)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/GetTriviaDetails.php', 
   data: 'id_trivia='+triviaID,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text === 'Bad arguments' || text === 'DB error')
      {
         alert('Une erreur est survenue lors de la requête. Réessayez plus tard.');
      }
      else if(text === 'Missing piece')
      {
         alert('Cette anecdote a été supprimée.');
      }
      else
      {
         // Adds the details
         var reply = $(text);
         $('.triviaBlock[id=' + triviaID + '] .contentDates').html(reply.find('#detailsOnDates').html());
         $('.triviaBlock[id=' + triviaID + '] .commentableRatings').html(reply.find('#upToDateRatings').html());
         
         // Adds all details for the rating process
         var ratingsBlock = '.triviaBlock[id=' + triviaID + '] .ratings';
         $(ratingsBlock).attr('data-voting-allowed', reply.find('#userCanRate').html());
         $(ratingsBlock + ' .ratingsLeft p').attr('class', reply.find('#ratingButtonStyle').html());
         $(ratingsBlock + ' .relevantRatings .ratingsRight').html(reply.find('#relevantRatings').html());
         $(ratingsBlock + ' .irrelevantRatings .ratingsRight').html(reply.find('#irrelevantRatings').html());
         
         // Adds the events to trigger the rating process if voting is allowed
         if(reply.find('#userCanRate').html() === 'yes')
         {
            $(ratingsBlock + ' .relevantRatings .ratingsLeft p').on('click', function()
            {
               CommentablesLib.rate($(this).closest('.triviaBlock').attr('id'), 'relevant');
            });
            $(ratingsBlock + ' .relevantRatings .ratingsLeft p').hover(function()
            {
               $(this).css('cursor','pointer');
            });
            
            $(ratingsBlock + ' .irrelevantRatings .ratingsLeft p').on('click', function()
            {
               CommentablesLib.rate($(this).closest('.triviaBlock').attr('id'), 'irrelevant');
            });
            $(ratingsBlock + ' .irrelevantRatings .ratingsLeft p').hover(function()
            {
               $(this).css('cursor','pointer');
            });
         }
         
         // Modifies a bit the triviaBlock element
         $('.triviaBlock[id=' + triviaID + '] .fullContent').show(500);
         $('.triviaBlock[id=' + triviaID + '] .readMore').attr('style', 'display: block');
         $('.triviaBlock[id=' + triviaID + '] .ratings').attr('style', 'display: block');
      }
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
   $('.triviaBlock .hideShowTrivia').on('click', function()
   {
      var triviaID = $(this).closest('.triviaBlock').attr('id');
      var visibleBlock = $('.triviaBlock[id=' + triviaID + '] .fullContent:visible');
      if(visibleBlock.length === 0)
      {
         $('.triviaBlock[id=' + triviaID + '] .fullContent').show(500);
         if($('.triviaBlock[id=' + triviaID + '] .ratingsRight').html() != 0)
            $('.triviaBlock[id=' + triviaID + '] .ratings').show(500);
      }
      else
      {
         $('.triviaBlock[id=' + triviaID + '] .fullContent').hide(500);
         $('.triviaBlock[id=' + triviaID + '] .ratings').hide(500);
      }
   });
   
   $('.triviaBlock .showDetailsLink').on('click', function(e)
   {
      var triviaID = $(this).closest('.triviaBlock').attr('id');
      TriviaInteractionLib.getDetails(triviaID);
   });
   
   // Ensures the "dynamic" parts of the formatting are working
   $('.triviaBlock .spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      DefaultLib.showSpoiler(spoilerId);
   });
   
   $('.triviaBlock .miniature').on('click', function()
   {
      DefaultLib.showUpload($(this));
   });
   
   $('.triviaBlock .videoThumbnail').on('click', function()
   {
      var index = $(this).attr('data-id-post');
      var videoId = $(this).attr('data-id-video');
      DefaultLib.showVideo(videoId, index);
   });
   
   // If a .ratings block is already visible, binds event to it
   $('.triviaBlock .ratings:visible').each(function()
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
