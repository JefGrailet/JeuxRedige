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
   var type = $('.videoThumbnail[data-post-id="' + idPost + '"][data-video-id="' + idVideo + '"]').attr('data-video-type');
   var trueID = $('.videoThumbnail[data-post-id="' + idPost + '"][data-video-id="' + idVideo + '"]').attr('data-video-true-id');
   
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
* Slides in/out the comments slider.
*/

ArticleLib.toggleComments = function()
{
   if($('#commentsSlider').is(':visible'))
   {
      $('#commentsSlider').css('min-width', '0px');
      $('#commentsSlider').animate({width: 'toggle'}, 500);
      $('#coverScreen').fadeOut(200);
      $('body').css('overflow', 'visible');
   }
   else
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
         timeout: 5000, // Because all comments are retrieved
         success: function(data)
         {
            DefaultLib.doneWithAJAX();
            // No particular error message handling, nor AJAX lock, because this is passive
            if(data.length !== 0 && data !== 'No message' && data !== 'DB error' && data !== 'Template error')
            {
               // Shows the slider
               $('#coverScreen').fadeIn(200);
               $('#coverScreen').on('click', function(e) { toggleComments(); });
               $('#commentsSlider').animate({width: 'toggle'}, 300, function()
               {
                  $('#commentsSlider').css('min-width', '400px');
                  $('#commentsListed').html(data);
                  
                  // Binds events (few of them, due to simplified display)
                  $('.comment .spoiler a:first-child').on('click', function() { ArticleLib.showSpoiler($(this).attr('data-id-spoiler')); });
                  $('.comment .miniature').on('click', function() { DefaultLib.showUpload($(this)); });
                  $('.comment .videoThumbnail').on('click', function() { ArticleLib.showVideo($(this).attr('data-video-id'), $(this).attr('data-post-id')); });
                  $('.comment .link_masked_post').on('click', function() { $('#masked' + $(this).attr('data-id-post')).toggle(300); });
                  
                  $('body').css('overflow', 'hidden');
               });
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
      else
      {
         $('#coverScreen').fadeIn(200);
         $('#commentsSlider').animate({width: 'toggle'}, 500, function()
         {
            $('#commentsSlider').css('min-width', '400px');
         });
      }
   }
}

/*
* Changes the display to print another segment (if multiple segments exist).
*
* @param index  The index of the segment within the article
*/

ArticleLib.switchSegment = function(index)
{
   $('.articleSegment:visible').hide();
   $('.articleSegment[data-segment-pos=' + index + ']').show();
   $('#segmentList select option:selected').removeAttr('selected');
   $('#segmentList select option[value=' + index + ']').prop('selected', 'selected');
   $(window).scrollTop(0);
   
   var lastSegment = parseInt($('.articleSegment:last').attr('data-segment-pos'));
   if(index == 1)
   {
      $('#previousSegment p i').unbind();
      $('#previousSegment p').remove();
      
      if(!($('#nextSegment p').length))
      {
         $('#nextSegment').html('<p><i class="icon-general_next" title="Section suivante"/></p>');
         $('#nextSegment p i').on('click', function() { ArticleLib.nextSegment(); });
      }
   }
   else if(index == lastSegment)
   {
      $('#nextSegment p i').unbind();
      $('#nextSegment p').remove();
      
      if(!($('#previousSegment p').length))
      {
         $('#previousSegment').html('<p><i class="icon-general_previous" title="Section précédente"></i></p>');
         $('#previousSegment p i').on('click', function() { ArticleLib.previousSegment(); });
      }
   }
   else
   {
      if(!($('#nextSegment p').length))
      {
         $('#nextSegment').html('<p><i class="icon-general_next" title="Section suivante"></i></p>');
         $('#nextSegment p i').on('click', function() { ArticleLib.nextSegment(); });
      }
      
      if(!($('#previousSegment p').length))
      {
         $('#previousSegment').html('<p><i class="icon-general_previous" title="Section précédente"></i></p>');
         $('#previousSegment p i').on('click', function() { ArticleLib.previousSegment(); });
      }
   }
}

// Simple methods to move to next/previous segment, using switchSegment().

ArticleLib.previousSegment = function()
{
   var currentlyVisible = parseInt($('.articleSegment:visible').attr('data-segment-pos'));
   if(currentlyVisible == 1)
      return;
   
   ArticleLib.switchSegment(currentlyVisible - 1);
}

ArticleLib.nextSegment = function()
{
   var lastSegment = parseInt($('.articleSegment:last').attr('data-segment-pos'));
   var currentlyVisible = parseInt($('.articleSegment:visible').attr('data-segment-pos'));
   if(currentlyVisible == lastSegment)
      return;
   
   ArticleLib.switchSegment(currentlyVisible + 1);
}

// Binds events

$(document).ready(function()
{
   $('.spoiler a:first-child').on('click', function()
   {
      var spoilerId = $(this).attr('data-id-spoiler');
      ArticleLib.showSpoiler(spoilerId);
   });
   
   $('.miniature').on('click', function() { DefaultLib.showUpload($(this)); });
   
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
      $('#segmentList select option').on('click', function()
      {
         ArticleLib.switchSegment($(this).val());
      });
   }
   
   if($('#previousSegment p').length)
   {
      var icon = $('#previousSegment p i').parent().html();
      $('#previousSegment p').html(icon);
      $('#previousSegment p i').on('click', function() { ArticleLib.previousSegment(); });
   }
   
   if($('#nextSegment p').length)
   {
      var icon = $('#nextSegment p i').parent().html();
      $('#nextSegment p').html(icon);
      $('#nextSegment p i').on('click', function() { ArticleLib.nextSegment(); });
   }
   
   if($('.editSegment').length)
   {
      $('.editSegment').on('click', function()
      {
         var currentlyVisible = parseInt($('.articleSegment:visible').attr('data-segment-id'));
         window.location.replace(DefaultLib.httpPath + 'EditSegment.php?id_segment=' + currentlyVisible);
      });
   }
   
   // Comments slider, if present
   if($('#commentsSlider').length)
   {
      $('#closeClickable').on('click', ArticleLib.toggleComments);
      var commentsLink = $('#showComments').html();
      $('#showComments').replaceWith('<span id="showComments">' + commentsLink + '</span>');
      $('#showComments').on('click', ArticleLib.toggleComments);
   }
});

/*
* Code to handle F5 press to redirect to the right segment at page refresh, and also left/right 
* buttons to navigate with the directional arrows.
*/

$(document).keydown(function(e)
{
   if(e.keyCode == 116 && $('#segmentList').length)
   {
      e.preventDefault(); // Prevents browser behaviour regarding F5
      
      var currentlyVisible = parseInt($('.articleSegment:visible').attr('data-segment-pos'));
      var currentURL = window.location.toString();
      
      var newURL = "";
      var lastChar = currentURL.substr(currentURL.length - 1);
      if(lastChar == '/')
      {
         var splittedPrevURL = currentURL.split('/');
         if(splittedPrevURL[splittedPrevURL.length - 2].match(/^[0-9]+$/))
         {
            splittedPrevURL[splittedPrevURL.length - 2] = currentlyVisible;
            newURL = splittedPrevURL.join('/');
         }
         else if(currentlyVisible > 1)
            newURL = currentURL + currentlyVisible + '/';
         else
            newURL = currentURL;
      }
      else
      {
         if(currentURL.indexOf('&') != -1)
         {
            var splittedPrevURL = currentURL.split('&section=');
            newURL = splittedPrevURL[0] + '&section=' + currentlyVisible;
         }
         else if(currentlyVisible > 1)
            newURL = currentURL + '&section=' + currentlyVisible;
         else
            newURL = currentURL;
      }
      
      window.location.replace(newURL);
   }
   else if(e.keyCode == 37 && !$('#lightbox').is(':visible'))
   {
      ArticleLib.previousSegment();
   }
   else if(e.keyCode == 39 && !$('#lightbox').is(':visible'))
   {
      ArticleLib.nextSegment();
   }
});
