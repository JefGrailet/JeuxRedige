/**
* This file contains several functions to handle interactivity on posts with features such as a 
* "Like"/"Dislike" system, a report button and a "highlight" feature that highlight posts with a 
* certain score (positive sum of like/dislike, above some value, etc.) while posts that do not fit 
* the criterion are grayed out.
*/

var PostInteractionLib = {};

/*
* updateScore() updates the HTML code of the item with class "votes" in order to update the overall
* score of the the related post.
*
* @param number post  The ID of the post which the score should be updated
* @param number vote  +1 if we are adding an upvote/undoing a downvote, -1 for the contrary
*/

PostInteractionLib.updateScore = function(post, vote)
{
   var curScore = $('.votes[data-id-post='+post+']').html();
   if(curScore.charAt(0) == '+')
     curScore = curScore.substr(1);
   
   var newScore = parseInt(curScore) + vote;
   $('.votes[data-id-post='+post+']').attr('data-score', newScore);
   if(newScore > 0)
   {
     $('.votes[data-id-post='+post+']').attr('style', 'color: green;');
     newScore = '+' + newScore;
   }
   else if(newScore == 0)
   {
      var url = window.location.href;
      if(url.indexOf("PopularPosts.php") !== -1) // If in popular posts page, we remove the whole block
      {
         var nbPosts = $('.postBlock').length;
         if(nbPosts > 1)
         {
            $('.postBlock[data-id-post=' + post + ']').animate({opacity: 0}, 500, function()
            {
               $(this).remove();
            });
            
            // Attachment needs to be removed too
            if($('#Attachment' + post).length)
            {
               $('#Attachment' + post).animate({opacity: 0}, 500, function()
               {
                  $(this).remove();
               });
            }
         }
         else
         {
            $('.postBlock[data-id-post=' + post + ']').animate({opacity: 0}, 500, function()
            {
               $(this).replaceWith("<p class=\"centeredAlert\">Plus de message!</p>");
            });
            
            // Attachment needs to be removed
            if($('#Attachment' + post).length)
            {
               $('#Attachment' + post).animate({opacity: 0}, 500, function()
               {
                  $(this).remove();
               });
            }
         }
      }
      else
         $('.votes[data-id-post='+post+']').attr('style', 'color: green;');
   }
   else
     $('.votes[data-id-post='+post+']').attr('style', 'color: red;');

   $('.votes[data-id-post='+post+']').html(newScore);
}

/*
* updateToolTip() updates the tooltip giving the amount of upvotes/downvotes for a given post.
*
* @param number post                The ID of the post which the score should be updated
* @param number vote                The vote (+1 for upvote, -1 for downvote)
* @param bool undoneVoteOrNewVote   True if we are undoing a vote, false if we are counting
*                                   a new one
*/

PostInteractionLib.updateTooltip = function(post, vote, undoneVoteOrNewVote)
{
   var currentLikes = parseInt($('.postInteractions[data-id-post='+post+']').attr('data-likes'));
   var currentDislikes = parseInt($('.postInteractions[data-id-post='+post+']').attr('data-dislikes'));
   
   if(vote == 1)
   {
      if(undoneVoteOrNewVote)
         currentLikes -= 1;
      else
         currentLikes += 1;
   }
   else
   {
      if(undoneVoteOrNewVote)
         currentDislikes -= 1;
      else
         currentDislikes += 1;
   }
   
   $('.postInteractions[data-id-post='+post+']').attr('data-likes', currentLikes);
   $('.postInteractions[data-id-post='+post+']').attr('data-dislikes', currentDislikes);
   
   var newTitle = currentLikes + " J'aime, " + currentDislikes + " Je n'aime pas";
   $('.postInteractions[data-id-post='+post+']').attr('title', newTitle);
}

/*
* ratePost() receives a post ID and either undoes the vote recorded for that user (if any), either
* records the vote provided as a second argument. The fact of undoing a vote or recording a new
* one at database level is performed via AJAX requests.
*
* @param number post  The ID of the post which the score should be updated
* @param number vote  The vote (+1 for upvote, -1 for downvote)
*/

PostInteractionLib.ratePost = function(post, vote)
{
   // Has the user already voted for that post ?
   var hasVoted = parseInt($('.votes[data-id-post='+post+']').attr('data-has-voted'));

   // Undoes a vote
   if(hasVoted != 0)
   {
      if(DefaultLib.isHandlingAJAX())
         return;
   
      $.ajax({
      type: 'POST',
      url: DefaultLib.httpPath + 'ajax/Vote.php', 
      data: 'vote=0&id_post='+post,
      timeout: 5000,
      success: function(text)
      {
         DefaultLib.doneWithAJAX();
         if(text === 'OK')
         {
            $('.votes[data-id-post='+post+']').attr('data-has-voted', 0);
            $('.vote[data-id-post='+post+']').animate({opacity: 1.0}, 300);

            PostInteractionLib.updateScore(post, -hasVoted);
            PostInteractionLib.updateTooltip(post, hasVoted, true);
         }
         else
            alert('Une erreur est survenue lors du vote. Réessayez plus tard.');
      },
      error: function(xmlhttprequest, textstatus, message)
      {
         DefaultLib.doneWithAJAX();
         DefaultLib.diagnose(textstatus, message);
      }
      });

      return;
   }

   // Records a new vote
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/Vote.php', 
   data: 'vote='+vote+'&id_post='+post,
   timeout: 5000,
   success: function(text)
   {
      DefaultLib.doneWithAJAX();
      if(text === 'OK')
      {
         if(vote < 0)
            $('.vote[data-id-post='+post+'][data-vote=1]').animate({opacity: 0.2}, 300);
         else
            $('.vote[data-id-post='+post+'][data-vote=-1]').animate({opacity: 0.2}, 300);

         $('.votes[data-id-post='+post+']').attr('data-has-voted', vote);
         PostInteractionLib.updateScore(post, vote);
         PostInteractionLib.updateTooltip(post, vote, false);
      }
      else
         alert('Une erreur est survenue lors du vote. Réessayez plus tard.');
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* getAlertMotivations() retrieves the motivations of the (potential) previous alerts in order to 
* help the user to select a motivation, upon sending an alert, rather than forcing him/her to 
* always input a whole new motivation.
*
* @param number post   The ID of the post for which the user wants to send a report
*/

PostInteractionLib.getAlertMotivations = function(post)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/AlertPostMotivations.php', 
   data: 'id_post='+post,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var defaultOption = '<option value="New" selected>Nouveau motif</option>';
      if(data === "None")
      {
         $("#sendAnAlert .motivationsList").html(defaultOption);
         $("#sendAnAlert .preloadedMotivations").hide();
         $("#sendAnAlert .newMotivLabel").text('Motif :');
      }
      else
      {
         var options = data + "\n" + defaultOption;
         $("#sendAnAlert .motivationsList").html(options);
         $("#sendAnAlert .preloadedMotivations").show();
         $("#sendAnAlert .newMotivLabel").text('Nouveau :');
      }
      
      $('#sendAnAlert .alertPostID').text(post);
      
      DefaultLib.openDialog("#sendAnAlert");
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}

/*
* sendAlert() records a new alert for a given post sent by a user. It is done through a single 
* AJAX request, which the callback provides the new "bad" score for that post, which can result
* in instantly hiding the content of the post.
*
* @param number post   The ID of the post for which a report is being sent
* @param string motiv  The motivation for the alert (max. 100 characters)
*/

PostInteractionLib.sendAlert = function(post, motiv)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/AlertPost.php', 
   data: 'id_post='+post+'&motiv='+motiv,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      // Resets and closes alert dialog window
      $('input[name=new_motiv]').val('');
      DefaultLib.closeDialog();
      
      var dataInt = parseInt(data);
      if(dataInt == -1) // Stops if -1 returned
         return;
      
      var newTooltip = 'Vous avez émis une alerte (' + motiv + ')';
      $('.report[data-id-post='+post+']').unbind();
      $('.report[data-id-post='+post+']').attr('title', newTooltip);
      $('.report[data-id-post='+post+']').attr('alt', newTooltip);
      $('.report[data-id-post='+post+']').animate({opacity: 1.0}, 300, function() { $('.report[data-id-post='+post+']').attr('class', 'reported icon-general_alert'); });
      
      // Changes the display if the code says so
      if(dataInt == 1)
      {
         var content = $('#wrapper'+post).html();
         var newContent = '<p><span style="color: grey;">Ce message a été signalé par plusieurs inscrits et/ou\r\n' +
         'un modérateur comme inapproprié/offensant. Par conséquent, son contenu a été masqué à titre\r\n' +
         ' préventif.<br/>\r\n' +
         '<br/>\r\n' +
         '<a href="javascript:void(0)" class="link_masked_post" data-id-post="'+post+'">Cliquez ici</a>\r\n' +
         'pour afficher/masquer ce contenu.</span>\r\n' +
         '</p>\r\n'+
         '<div id="masked'+post+'" style="display: none;">\r\n' + content + '\r\n</div>';
      
         $('#wrapper'+post).fadeOut(500, function()
         {
            $('#wrapper'+post).html(newContent);
            
            // Adds event
            $('.link_masked_post[data-id-post="'+post+'"]').on('click', function()
            {
               var toMask = $(this).attr('data-id-post');
               $('#masked'+toMask).toggle(300);
            });
            
            $('#wrapper'+post).fadeIn(500);
         });
         
         // Does the same with the attachments, if any
         if($('#Attachment' + post + ' .postAttachmentAlign').length)
         {
            // Don't touch attachment if already hidden
            if($('#maskedAttachment' + post).length)
               return;
         
            var attachment = $('#Attachment' + post + ' .postAttachmentAlign').html();
            var newAttachment = '<p><a href="javascript:void(0)" class="link_masked_attachment" ' +
            'data-id-post="' + post + '">Cliquez ici</a> pour afficher/masquer ' +
            'les uploads liés à ce message (<strong>censuré</strong>).</span></p>' + "\n" +
            '<div id="maskedAttachment' + post + '" style="display: none;">' + "\n" +
            attachment + "\n" +
            '</div>' + "\n";
            
            $('#Attachment' + post + ' .postAttachmentAlign').fadeOut(500, function()
            {
               $('#Attachment' + post + ' .postAttachmentAlign').html(newAttachment);
               
               // Adds event
               $('.link_masked_attachment[data-id-post="'+post+'"]').on('click', function()
               {
                  var toMask = $(this).attr('data-id-post');
                  $('#maskedAttachment'+toMask).toggle(300);
               });
               
               $('#Attachment' + post + ' .postAttachmentAlign').fadeIn(500);
            });
         }
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
* pinPost() sends a simple AJAX request to pin the selected post for the current user.
*
* @param number post     The ID of the post which the user wants to pin
* @param string comment  Comment written by the user for this post
*/

PostInteractionLib.pinPost = function(post, comment)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/PinPost.php', 
   data: 'id_post='+post+'&comment='+comment,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var pinObject = '.pin[data-id-post=' + post + ']';
      if(data === 'Pinned')
      {
         $(pinObject).removeClass('icon-general_pin');
         $(pinObject).addClass('icon-general_unpin');
         $(pinObject).attr('title', comment);
         $(pinObject).attr('alt', 'Enlever ce message de mes favoris');
         
         DefaultLib.closeDialog();
         $('input[name=comment]').val('');
      }
      else
      {
         alert('Une erreur est survenue lors de l\'épinglage. Réessayez plus tard.');
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
* unpinPost() sends a simple AJAX request to unpin the selected post for the current user.
*
* @param number post     The ID of the post which the user wants to pin
*/

PostInteractionLib.unpinPost = function(post)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/PinPost.php', 
   data: 'id_post='+post,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var pinObject = '.pin[data-id-post=' + post + ']';
      if(data === 'Unpinned')
      {
         var url = window.location.href;
         if(url.indexOf("PopularPosts.php") !== -1) // If in pinned posts page, we remove the whole block
         {
            var nbPosts = $('.postBlock').length;
            if(nbPosts > 1)
            {
               $('.postBlock[data-id-post=' + post + ']').animate({opacity: 0}, 500, function()
               {
                  $(this).remove();
               });
               
               // Attachment needs to be removed too
               if($('#Attachment' + post).length)
               {
                  $('#Attachment' + post).animate({opacity: 0}, 500, function()
                  {
                     $(this).remove();
                  });
               }
            }
            else
            {
               $('.postBlock[data-id-post=' + post + ']').animate({opacity: 0}, 500, function()
               {
                  $(this).replaceWith("<p class=\"centeredAlert\">Plus de message!</p>");
               });
               
               // Attachment needs to be removed
               if($('#Attachment' + post).length)
               {
                  $('#Attachment' + post).animate({opacity: 0}, 500, function()
                  {
                     $(this).remove();
                  });
               }
            }
         }
         else
         {
            $(pinObject).removeClass('icon-general_unpin');
            $(pinObject).addClass('icon-general_pin');
            $(pinObject).attr('title', 'Ajouter ce message à mes favoris');
            $(pinObject).attr('alt', 'Ajouter ce message à mes favoris');
         }
      }
      else if(data === "Not pinned")
      {
         alert('Ce message n\'était pas dans vos favoris.');
      
         $(pinObject).removeClass('icon-general_unpin');
         $(pinObject).addClass('icon-general_pin');
         $(pinObject).attr('title', 'Ajouter ce message à mes favoris');
         $(pinObject).attr('alt', 'Ajouter ce message à mes favoris');
      }
      else
      {
         alert('Une erreur est survenue lors de la suppression du favori. Réessayez plus tard.');
      }
      
      DefaultLib.closeDialog();
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
   $('.vote').on('click', function()
   {
      vote = parseInt($(this).attr("data-vote"));
     
      if(vote > 1)
         vote = 1;
      else if(vote < -1)
         vote = -1;
      
      if(vote == 0)
         return;
     
      post = parseInt($(this).attr("data-id-post"));
      PostInteractionLib.ratePost(post, vote);
   });
   
   $('.report').on('click', function()
   {
      post = parseInt($(this).attr("data-id-post"));
      PostInteractionLib.getAlertMotivations(post);
   });
   
   $('.pin').on('click', function()
   {
      postID = parseInt($(this).attr("data-id-post"));
      if($(this).hasClass("icon-general_pin"))
      {
         $('#pinPost .pinPostID').text(postID);
         DefaultLib.openDialog('#pinPost');
      }
      else
         PostInteractionLib.unpinPost(postID);
   });
   
   // Alert send dialog window
   $("#sendAnAlert button").on('click', function()
   {
      var postID = $('#sendAnAlert .alertPostID').text();
      var motiv = $('select[name=existing_motiv]').val();
      if(motiv === "New")
         motiv = $('input[name=new_motiv]').val();
      
      if(motiv === "")
         alert("Vous devez indiquer un motif.");
      else
         PostInteractionLib.sendAlert(postID, motiv);
   });
   
   $("#sendAnAlert .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   // Pin comment dialog window
   $("#pinPost button").on('click', function()
   {
      var postID = $('#pinPost .pinPostID').text();
      var comment = $('input[name=comment]').val();
      PostInteractionLib.pinPost(postID, comment);
   });
   
   $("#pinPost .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
});
