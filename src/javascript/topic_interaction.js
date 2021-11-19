/**
* This file defines multiple methods to handle interactivity on topics, which includes the headers 
* as well as some general features seen in the body of the current page (such as showing videos, 
* spoilers, etc.).
*/

var TopicInteractionLib = {};

/*
* favouriteTopic() receives a topic ID and either undoes the favoriting of this topic (if the 
* topic was favorited), either records it. The whole process relies on a single AJAX request which 
* will provide the image to display afterwards in place of the icon the user clicked.
*
* @param number topic  The ID of the topic which should be (un)favorited
*/

TopicInteractionLib.favouriteTopic = function(topic)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/FavoriteTopic.php', 
   data: 'id_topic='+topic,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.localeCompare($('#buttonFavourite').attr('class')) !== 0)
      {
         $('#buttonFavourite').attr('class', data);
         if(data.localeCompare('icon-general_star') === 0)
            $('#buttonFavourite').attr('title', 'Enlever des favoris');
         else
            $('#buttonFavourite').attr('title', 'Ajouter aux favoris');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Inserts the unparsed content of some post (retrieved via AJAX) which the ID is provided to 
* insert it into the quick reply form. Only usable if auto-refresh is activated.
*
* @param integer idPost  The ID of the post to quote
*/

TopicInteractionLib.quotePost = function(idPost)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/QuotePost.php', 
   data: 'id_post='+idPost,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var message = $('#replyForm textarea[name=message]');
      var content = message.val();
      var before = content.substring(0, message[0].selectionStart);
      var selection = content.substring(message[0].selectionStart, message[0].selectionEnd);
      var after = content.substring(message[0].selectionEnd, content.length);
      message.val(before + data + selection + after);
      
      if(!($('#slidingBlock').is(':visible')))
         $('#slidingBlock').slideToggle();
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* Shows all interactions of registered users with a given post. The summary of these interactions 
* is retrieved with an AJAX request.
*
* @param idPost  The ID of the post which the user wants to see the interactions
*/

TopicInteractionLib.showInteractions = function(idPost)
{
   if(!($('#postInteractions').length))
   {
      alert('Une erreur s\'est produite lors de la génération de cette page. Réessayez plus '+
      'tard ou prévenez un administrateur.');
      return;
   }

   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/GetPostInteractions.php', 
   data: 'id_post='+idPost,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         var dataBis = '';
         if(data === 'no interaction')
            dataBis = '<p>Aucun utilisateur n\'a encore interagi avec ce message.</p>';
         else
            dataBis = data;
         $('#postInteractions .interactionsPostID').text(idPost);
         $('#postInteractions #interactionsContainer').html(dataBis);
         DefaultLib.openDialog('#postInteractions');
      }
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* highlight() changes the opacity of posts. The policy is to change the opacity how posts with 
* score above the threshold when negative and with score below the same threshold when this 
* threshold is positive.
*
* @param integer threshold  The threshold (positive: score above, negative: score below)
*/

TopicInteractionLib.highlight = function(threshold)
{
   $(".postBlock" ).each(function()
   {
      var i = $(this).attr('data-id-post');
      var score = parseInt($('#score' + i).attr('data-score'));
      if(threshold < 0)
      {
         if(score > threshold)
            $('.postBlock[data-id-post=' + i.toString() + ']').animate({opacity: 0.5}, 300);
         else
            $('.postBlock[data-id-post=' + i.toString() + ']').animate({opacity: 1.0}, 300);
      }
      else if(threshold > 0)
      {
         if(score < threshold)
            $('.postBlock[data-id-post=' + i.toString() + ']').animate({opacity: 0.5}, 300);
         else
            $('.postBlock[data-id-post=' + i.toString() + ']').animate({opacity: 1.0}, 300);
      }
      else
         $('.postBlock[data-id-post=' + i.toString() + ']').animate({opacity: 1.0}, 300);
   });
}

// Binds events

$(document).ready(function()
{
   // Buttons in the title
   if($('#buttonFavourite').length)
   {
      $('#buttonFavourite').on('click', function() {
         var idTopic = $('#buttonFavourite').attr('data-id-topic');
         TopicInteractionLib.favouriteTopic(idTopic);
      });
   }
   
   if($('#buttonDelete').length)
   {
      $('#buttonDelete').on('click', function() { DefaultLib.openDialog('#delete'); });
      $('#delete .closeDialog').on('click', function() { DefaultLib.closeDialog(); });
   }
   
   if($('#buttonLock').length)
   {
      $('#buttonLock').on('click', function() { DefaultLib.openDialog('#lock'); });
      $('#lock .closeDialog').on('click', function() { DefaultLib.closeDialog(); });
   }
   
   // Show topic thumbnail on mouse over the title of the topic
   $('#topicHeader h1 a').on('mouseover', function(e)
   {
      var topicThumb = $('.topicThumbnail');
      var xInit = e.screenX, yInit = e.screenY;
      
      topicThumb.css('top', (yInit - 40) + 'px'); // -40 due to the top of the window of the browser
      topicThumb.css('left', xInit + 'px');
      topicThumb.show();

      window.onmousemove = function (e)
      {
         var x = e.screenX, y = e.screenY;
         topicThumb.css('top', (y - 40) + 'px');
         topicThumb.css('left', x + 'px');
      };
   });
   
   // Stop showing topic thumbnail
   $('#topicHeader h1 a').on('mouseout', function(e)
   {
      $('.topicThumbnail').hide();
   });
   
   // Hover effect on keywords: background-color change + game is shown if it exists
   $('.topicKeywords a').on('mouseover', function(e)
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

         window.onmousemove = function (e)
         {
            var x = e.screenX, y = e.screenY;
            gameThumb.css('top', (y - 40) + 'px');
            gameThumb.css('left', x + 'px');
         };
      }
   });
   
   // Stops effects of hovering a keyword
   $('.topicKeywords a').on('mouseout', function(e)
   {
      $(this).css("background-color", "rgb(" + $(this).attr('data-rgb') + ")");
   
      var game = $(this).html();
      if($('.mediaThumbnail[data-game="' + game + '"]').length)
         $('.mediaThumbnail[data-game="' + game + '"]').hide();
   });
   
   // Retrieving interactions of a single post in a small window
   $('.postInteractions').on('click', function() { TopicInteractionLib.showInteractions($(this).attr('data-id-post')); });
   
   // Closing the interactions window
   if($('#postInteractions').length)
   {
      $("#postInteractions .closeDialog").on('click', function() { DefaultLib.closeDialog(); });
   }
   
   // Special formatting in messages
   $('.spoiler a:first-child').on('click', function() { DefaultLib.showSpoiler($(this).attr('data-id-spoiler')); });
   
   // Miniatures
   $('.miniature').on('click', function() { DefaultLib.showUpload($(this)); });
   
   // Video thumbnails
   $('.videoThumbnail').on('click', function()
   {
      var index = $(this).attr('data-id-post');
      var videoId = $(this).attr('data-id-video');
      DefaultLib.showVideo(videoId, index);
   });
   
   // Uploads below posts or in Uploads.php
   $('.uploadDisplay .uploadDisplayAlign').on('click', function() { DefaultLib.showUpload($(this).parent()); });
   
   // Hidden messages and attachments
   $('.link_masked_post').on('click', function() { $('#masked' + $(this).attr('data-id-post')).toggle(300); });
   $('.link_masked_attachment').on('click', function() { $('#maskedAttachment' + $(this).attr('data-id-post')).toggle(300); });
   
   $('.quickForm').on('click', function()
   {
      if($('#slidingBlock').is(':visible'))
         $('#visibleWrapper').css('margin-bottom', 0);
      else
         $('#visibleWrapper').css('margin-bottom', $('#slidingBlock').height() - 100);
      $('#slidingBlock').slideToggle();
   });
   $('#highlightPosts option').on('click', function() { TopicInteractionLib.highlight($(this).val()); });
   
   // Direct quote for the quick reply form
   $('.quote').each(function()
   {
      var idPost = $(this).attr('data-id-post');
      $('.quote[data-id-post=' + idPost + ']').on('click', function()
      {
         TopicInteractionLib.quotePost(idPost);
      });
   });
});

// Handles key presses to show/hide the quick reply form

$(document).keydown(function(e)
{
   if(e.keyCode == 32 && e.ctrlKey) // Ctrl + Space
   {
      if($('#slidingBlock').is(':visible'))
         $('#visibleWrapper').css('margin-bottom', 0);
      else
         $('#visibleWrapper').css('margin-bottom', $('#slidingBlock').height() - 100);
      $('#slidingBlock').slideToggle();
   }
});
