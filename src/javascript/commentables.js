/**
* This file contains everything that is needed to handle the rating process of "commentables", 
* i.e., short pieces of content which are not fit to be full articles but which can still be 
* evaluated by users through a simple rating system which works kind of like the like system of 
* posts.
*/

var CommentablesLib = {};

/*
* rate() receives the ID of a commentable and either undoes the rating recorded for that user (if 
* any), either records the new rating provided as a second argument. The fact of undoing a rating 
* or recording a new one at database level is performed via AJAX requests.
*
* @param number commentable  The ID of the post which the ratings should be updated
* @param number rating       The rating itself (+1 for "relevant", -1 for "irrelevant")
*/

CommentablesLib.rate = function(commentable, rating)
{
   var comBlock = '#' + commentable + ' .ratings ';
   
   // Can the user interact at all ?
   if($(comBlock).attr('data-voting-allowed') !== 'yes')
      return;
   
   // Has the user already voted for that post ?
   var currentRating = $(comBlock + '.ratingsLeft p').attr('class');
   
   // Undoes a rating
   if(currentRating != 'noRating')
   {
      if(DefaultLib.isHandlingAJAX())
         return;
   
      $.ajax({
      type: 'POST',
      url: DefaultLib.httpPath + 'ajax/RateCommentable.php', 
      data: 'rating=none&id_commentable='+commentable,
      timeout: 5000,
      success: function(text)
      {
         if(text !== 'DB error' && text !== "Missing content" && text !== "Forbidden rating")
         {
            var reply = $(text);
            $(comBlock + '.ratingsLeft p').attr('class', 'noRating');
            
            $('#' + commentable + ' .commentableRatings').html(reply.find('#newScore').html());
            $(comBlock + '.relevantRatings .ratingsRight').html(reply.find('#newRelevantRatings').html());
            $(comBlock + '.irrelevantRatings .ratingsRight').html(reply.find('#newIrrelevantRatings').html());
         }
         else
            alert('Une erreur est survenue lors du vote. Réessayez plus tard.');
         DefaultLib.doneWithAJAX();
      },
      error: function(xmlhttprequest, textstatus, message)
      {
         DefaultLib.doneWithAJAX();
         DefaultLib.diagnose(textstatus, message);
      }
      });

      return;
   }

   // Records a new rating
   if(DefaultLib.isHandlingAJAX())
      return;
   
   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/RateCommentable.php', 
   data: 'rating='+rating+'&id_commentable='+commentable,
   timeout: 5000,
   success: function(text)
   {
      if(text !== 'DB error' && text !== "Missing content" && text !== "Forbidden rating")
      {
         var reply = $(text);
         if(rating === 'relevant')
            $(comBlock + '.ratingsLeft p').attr('class', 'ratedRelevant');
         else if(rating === 'irrelevant')
            $(comBlock + '.ratingsLeft p').attr('class', 'ratedIrrelevant');
         $('#' + commentable + ' .commentableRatings').html(reply.find('#newScore').html());
         $(comBlock + '.relevantRatings .ratingsRight').html(reply.find('#newRelevantRatings').html());
         $(comBlock + '.irrelevantRatings .ratingsRight').html(reply.find('#newIrrelevantRatings').html());
      }
      else
         alert('Une erreur est survenue lors du vote. Réessayez plus tard.');
      DefaultLib.doneWithAJAX();
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.doneWithAJAX();
      DefaultLib.diagnose(textstatus, message);
   }
   });
}
