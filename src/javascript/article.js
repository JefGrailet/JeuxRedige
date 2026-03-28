/**
* JavaScript to navigate in an article.
*/

var ArticleLib = {};

/*
* Shows a spoiler, and edits the button to show/hide it depending on the state.
*
* @param idSpoiler  The ID of the spoiler to show/hide
*/

ArticleLib.showSpoiler = function(idSpoiler)
{
   var visibleBlock = $('#' + idSpoiler + ':visible');
   if(visibleBlock.length == 0)
   {
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour masquer");
   }
   else
   {
      $('a[data-id-spoiler="' + idSpoiler + '"]').html("Cliquez pour afficher");
   }
   $('#' + idSpoiler).toggle(100);
}

/*
* Replaces a video block by the embedded video upon click.
*/

ArticleLib.showVideo = function(idVideo, idPost)
{
   var type = $('.videoThumbnail[data-id-post="' + idPost + '"][data-id-video="' + idVideo + '"]').attr('data-video-type');
   var trueID = $('.videoThumbnail[data-id-post="' + idPost + '"][data-id-video="' + idVideo + '"]').attr('data-video-true-id');

   if(type === 'youtube')
   {
      var embedded = "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/";
      embedded += trueID + "\" frameborder=\"0\" allowfullscreen></iframe>";

      $('.videoWrapper' + idPost + '-' + idVideo).animate({opacity: 0.0}, 500);
      $('.videoWrapper' + idPost + '-' + idVideo).html(embedded);
      $('.videoWrapper' + idPost + '-' + idVideo).animate({opacity: 1.0}, 500);
   }
}

/*
* Loads the comments to display in the slider.
*/

ArticleLib.loadComments = function()
{
   if($('#commentsListed').html().length < 10)
   {
      var topicID = $('#commentsSlider').attr('data-id-topic');

      if(DefaultLib.isHandlingAJAX())
         return false;

      $.ajax({
      type: 'GET',
      url: DefaultLib.httpPath + 'ajax/GetComments.php',
      data: 'id_topic=' + topicID,
      timeout: 5000, // Because all comments are retrieved (N.B.: may be limited to the X lattest comments later)
      success: function(data)
      {
         DefaultLib.doneWithAJAX();

         // No particular error message handling, nor AJAX lock, because this is passive
         if(data.length !== 0 && data !== 'No message' && data !== 'DB error' && data !== 'Template error')
         {
            // Completes the HTML with the data obtained through AJAX
            $('#commentsListed').html(data);

            // Binds events (few of them, due to simplified display)
            $('.comment .spoiler a:first-child').on('click', function() { ArticleLib.showSpoiler($(this).attr('data-id-spoiler')); });
            // $('.comment .miniature').on('click', function() { DefaultLib.showUpload($(this)); });
            $('.comment .videoThumbnail').on('click', function() { ArticleLib.showVideo($(this).attr('data-id-video'), $(this).attr('data-id-post')); });
            $('.comment .link_masked_post').on('click', function() { $('#masked' + $(this).attr('data-id-post')).toggle(300); });
         }
         else
         {
            alert('Une erreur est survenue lors de la récupération des commentaires.');
         }
      },
      error: function(xmlhttprequest, textstatus, message)
      {
         DefaultLib.doneWithAJAX();
         DefaultLib.diagnose(textstatus, message);
      }
      });
   }
}

// Binds events

$(document).ready(function()
{
   $('.spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      ArticleLib.showSpoiler(spoilerId);
   });

   // $('.miniature').on('click', function() { DefaultLib.showUpload($(this)); });

   // Hover effect on keywords: background-color change + game is shown if it exists
   $('#articleKeywords a').on('mouseover', function(e)
   {
      // Color change
      var rgb = $(this).attr('data-rgb').split(',');
      for(i = 0; i < 3; i++)
         rgb[i] = (parseInt(rgb[i]) + 30).toString();
      var newRgb = rgb[0] + ',' + rgb[1] + ',' + rgb[2];
      $(this).css("background-color", "rgb(" + newRgb + ")");

      // Game display
      var game = $(this).html();

      // Game exists on the page
      if($('.mediaThumbnail[data-game="' + game + '"]').length)
      {
         var gameThumb = $('.mediaThumbnail[data-game="' + game + '"]');
         var xInit = e.screenX, yInit = e.screenY;
         gameThumb.css('position', 'fixed'); // Initially, not fixed

         gameThumb.css('top', (yInit - 40) + 'px'); // -40 due to the top of the window of the browser
         gameThumb.css('left', xInit + 'px');
         gameThumb.show();

         window.onmousemove = function(e)
         {
            var x = e.screenX, y = e.screenY;
            gameThumb.css('top', (y - 40) + 'px');
            gameThumb.css('left', x + 'px');
         };
      }
   });

   // Sets effects of hovering a keyword
   $('#articleKeywords a').on('mouseout', function(e)
   {
      $(this).css("background-color", "rgb(" + $(this).attr('data-rgb') + ")");

      var game = $(this).html();

      if($('.mediaThumbnail[data-game="' + game + '"]').length)
         $('.mediaThumbnail[data-game="' + game + '"]').hide();
   });

   if($('#segmentList select').length)
   {
      $('#segmentList select').on('change', function()
      {
         ArticleLib.switchSegment($(this).val());
      });
   }

   if($('.editSegment').length)
   {
      $('.editSegment').on('click', function()
      {
         var currentlyVisible = parseInt($('.articleSegment:visible').attr('data-segment-id'));
         window.location.replace(DefaultLib.httpPath + 'EditSegment.php?id_segment=' + currentlyVisible);
      });
   }

   // Comments slider, if present (just loads comments; toggling the slider is now done in HTML/CSS)
   if($('#commentsSlider').length)
   {
      $('.toggleLabel').on('click', ArticleLib.loadComments);
   }
});

