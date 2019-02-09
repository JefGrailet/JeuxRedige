/**
* This file handles the possibility to see the trope descriptions while reading reviews (for now).
*/

var ReviewInteractionLib = {};

// Hover effect on trope icons: background-color changes and the full trope is shown

ReviewInteractionLib.showFullTrope = function(miniTrope, event)
{
   // Color change
   var rgb = miniTrope.attr('data-rgb').split(',');
   for(i = 0; i < 3; i++)
      rgb[i] = (parseInt(rgb[i]) + 30).toString();
   var newRgb = rgb[0] + ',' + rgb[1] + ',' + rgb[2];
   miniTrope.css("background-color", "rgb(" + newRgb + ")");

   // Look-up of trope
   var trope = miniTrope.attr('alt');
   
   // Trope exists on the page
   if($('#tropesContainer .mediaThumbnail[data-trope="' + trope + '"]').length)
   {
      var tropeThumb = $('#tropesContainer .mediaThumbnail[data-trope="' + trope + '"]');
      var xInit = event.screenX, yInit = event.screenY;
      tropeThumb.css('position', 'fixed'); // Initially, not fixed
      
      tropeThumb.css('top', (yInit - 40) + 'px'); // -40 due to the top of the window of the browser
      tropeThumb.css('left', xInit + 'px');
      tropeThumb.show();

      window.onmousemove = function(e)
      {
         var x = e.screenX, y = e.screenY;
         tropeThumb.css('top', (y - 40) + 'px');
         tropeThumb.css('left', x + 'px');
      };
   }
}

// Stops effects of hovering a trope icon

ReviewInteractionLib.stopShowingFullTrope = function(miniTrope)
{
   miniTrope.css("background-color", "rgb(" + miniTrope.attr('data-rgb') + ")");
   
   var trope = miniTrope.attr('alt');
   if($('#tropesContainer .mediaThumbnail[data-trope="' + trope + '"]').length)
      $('#tropesContainer .mediaThumbnail[data-trope="' + trope + '"]').hide();
}

// Gets the details on a review which the ID is provided

ReviewInteractionLib.getDetails = function(reviewID)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/GetReviewDetails.php', 
   data: 'id_review='+reviewID,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text === 'Bad arguments' || text === 'Bad templating' || text === 'DB error')
      {
         alert('Une erreur est survenue lors de la requête. Réessayez plus tard.');
      }
      else if(text === 'Missing review')
      {
         alert('Cette évaluation a été supprimée.');
      }
      else
      {
         // Adds the details
         var reply = $(text);
         $('.reviewBlock[id=' + reviewID + '] .contentDates').html(reply.find('#detailsOnDates').html());
         $('.reviewBlock[id=' + reviewID + '] .readMore').html(reply.find('#detailsOnLinks').html());
         $('.reviewBlock[id=' + reviewID + '] .commentableRatings').html(reply.find('#upToDateRatings').html());
         
         // Adds the detailed tropes (if not present yet in the .tropesContainer div)
         reply.find('#detailsOnTropes .mediaThumbnail').each(function()
         {
            var trope = $(this).attr('data-trope');
            if(!($('#tropesContainer .mediaThumbnail[data-trope="' + trope + '"]').length))
               $('#tropesContainer').append($('<div>').append($(this).clone()).html());
            
            $('.miniTrope[alt="' + trope + '"]').removeAttr('title');
         });
         
         // Adds all details for the rating process
         var ratingsBlock = '.reviewBlock[id=' + reviewID + '] .ratings';
         $(ratingsBlock).attr('data-voting-allowed', reply.find('#userCanRate').html());
         $(ratingsBlock + ' .ratingsLeft p').attr('class', reply.find('#ratingButtonStyle').html());
         $(ratingsBlock + ' .relevantRatings .ratingsRight').html(reply.find('#relevantRatings').html());
         $(ratingsBlock + ' .irrelevantRatings .ratingsRight').html(reply.find('#irrelevantRatings').html());
         
         // Adds the events to trigger the rating process if voting is allowed
         if(reply.find('#userCanRate').html() === 'yes')
         {
            $(ratingsBlock + ' .relevantRatings .ratingsLeft p').on('click', function()
            {
               CommentablesLib.rate($(this).closest('.reviewBlock').attr('id'), 'relevant');
            });
            $(ratingsBlock + ' .relevantRatings .ratingsLeft p').hover(function()
            {
               $(this).css('cursor','pointer');
            });
            
            $(ratingsBlock + ' .irrelevantRatings .ratingsLeft p').on('click', function()
            {
               CommentablesLib.rate($(this).closest('.reviewBlock').attr('id'), 'irrelevant');
            });
            $(ratingsBlock + ' .irrelevantRatings .ratingsLeft p').hover(function()
            {
               $(this).css('cursor','pointer');
            });
         }
         
         // Modifies a bit the reviewBlock element
         $('.reviewBlock[id=' + reviewID + '] .readMore').attr('style', 'display: block');
         $('.reviewBlock[id=' + reviewID + '] .showReviewDetails').remove();
         $('.reviewBlock[id=' + reviewID + '] .ratings').attr('style', 'display: block');
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
   $('.reviewBlock .showReviewDetails').on('click', function(e)
   {
      var reviewID = $(this).closest('.reviewBlock').attr('id');
      ReviewInteractionLib.getDetails(reviewID);
   });
   
   $('.reviewBlock .showDetailsLink').on('click', function(e)
   {
      var reviewID = $(this).closest('.reviewBlock').attr('id');
      ReviewInteractionLib.getDetails(reviewID);
   });
   
   $('.miniTrope').on('mouseover', function(e)
   {
      ReviewInteractionLib.showFullTrope($(this), e);
   });
   
   $('.miniTrope').on('mouseout', function(e)
   {
      ReviewInteractionLib.stopShowingFullTrope($(this));
   });
   
   // Ensures the "dynamic" parts of the formatting are working
   $('.reviewBlock .spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      DefaultLib.showSpoiler(spoilerId);
   });
   
   $('.reviewBlock .miniature').on('click', function()
   {
      DefaultLib.showUpload($(this));
   });
   
   $('.reviewBlock .videoThumbnail').on('click', function()
   {
      var index = $(this).attr('data-post-id');
      var videoId = $(this).attr('data-video-id');
      DefaultLib.showVideo(videoId, index);
   });
   
   // If a .ratings block is already visible, binds event to it
   $('.reviewBlock .ratings:visible').each(function()
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
