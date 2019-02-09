/**
* Script to handle the automatic refresh of topics and private discussions. It relies on the 
* paging conventions to detect whether the automatic refresh can be done and to append the new 
* messages still following the same conventions.
*/

var RefreshLib = {};
RefreshLib.autoRefresh = null;
RefreshLib.refreshScript = "";
RefreshLib.allSeenScript = "";
RefreshLib.unseen = 0;
RefreshLib.lock = false; // Needed due to possible race conditions between polling and quick reply
RefreshLib.fromOneself = false; // Set to true after quick reply, to not have a notification if it's the only new message

// (de)Activates auto refresh and makes the appropriate changes on the page.
RefreshLib.changeAutoRefresh = function()
{
   if(RefreshLib.autoRefresh != null)
   {
      clearTimeout(RefreshLib.autoRefresh);
      RefreshLib.autoRefresh = null;
      $('#navMenu .navRefresh').animate({opacity: 0.5}, 300);
   }
   else
   {
      RefreshLib.autoRefresh = setTimeout(RefreshLib.refreshPosts, 10000);
      $('#navMenu .navRefresh').animate({opacity: 1.0}, 300);
   }
}

RefreshLib.refreshPosts = function()
{
   if(RefreshLib.refreshScript === "")
      return;
   
   if(RefreshLib.lock)
   {
      RefreshLib.autoRefresh = setTimeout(RefreshLib.refreshPosts, 10000);
      return;
   }
   RefreshLib.lock = true;
   
   // Nice explanation of why the lock works:
   // https://stackoverflow.com/questions/7266918/are-there-any-atomic-javascript-operations-to-deal-with-ajaxs-asynchronous-natu

   var selected = 1;
   var lastPage = 1;
   if($('.pageLink').length) // If there are pages (to begin with)
   {
      // Checks last page is present
      selected = parseInt($('.pagesNav:first .pageLinkSelected').attr('data-page'));
      lastPage = parseInt($('.pagesNav:first .pageLink:last').attr('data-page'));
      if(selected > lastPage)
         lastPage = selected;
   }
   
   if(selected == lastPage || $('.page[data-page=' + lastPage + ']').length)
   {
      var lastPost = parseInt($('.page[data-page=' + lastPage + '] .postBlock:last').attr('id'));
      
      RefreshLib.getNewMessages(lastPost, function(lastNewPage)
      {
         if(lastNewPage == 0)
            return;
      
         var staticLink = $('.pagesNav').attr('data-static-link').split('[]');
         
         // Adding selected page 1 (when there is no page at start)
         if(lastNewPage == 2)
         {
            var newLink = ' <span class="pageLinkSelected" data-page="1">1</span>';
            $('.pagesNav').append(newLink);
         }

         for(i = lastPage + 1; i <= lastNewPage; i++)
         {
            var newLink = ' <span class="pageLink" data-page="' + i + '">';
            // Recomputes link for static mode if needed
            if(PagesLib.navMode == PagesLib.NAV_MODE_STATIC)
            {
               var newURL = staticLink[0] + i.toString();
               if(staticLink[1] !== '')
                  newURL += staticLink[1];
               newLink += '<a href="' + newURL + '">' + i + '</a>';
            }
            else
               newLink += i.toString();
            newLink += '</span>';
         
            $('.pagesNav').append(newLink);
            if(PagesLib.navMode == PagesLib.NAV_MODE_DYNAMIC)
            {
               // Binds event for dynamic pagination if needed
               $('.pageLink[data-page=' + i + ']').on('click', function()
               {
                  if($(this).closest('.pagesNav').is('.pagesNav:last'))
                     PagesLib.scrollToBottom = true;
                  PagesLib.dynamicSwitchPage(parseInt($(this).attr('data-page')));
               });
            }
         }
      });
   }
   
   // Next refresh
   RefreshLib.autoRefresh = setTimeout(RefreshLib.refreshPosts, 10000);
}

// Refreshes the counter of new posts in the title of the page.

RefreshLib.refreshTitleAndState = function()
{
   if(document.title.substr(0, 1) !== '(')
      return; // No need to do anything (can happen after a message from oneself)

   // Updates page title
   var curString = document.title.split(") ");
   var oldString = curString.slice(1).join(") ");
   if(RefreshLib.unseen == 0)
      document.title = oldString;
   else
      document.title = "(" + RefreshLib.unseen + ") " + oldString;
   
   // Notifies the discussion is fully read (currently only for pings)
   if(RefreshLib.unseen == 0 && RefreshLib.allSeenScript !== "")
   {
      var splittedAllSeen = RefreshLib.allSeenScript.split('?');

      $.ajax({
      type: 'POST',
      url: DefaultLib.httpPath + 'ajax/' + splittedAllSeen[0], 
      data: splittedAllSeen[1],
      timeout: 5000,
      success: function(text)
      {
         DefaultLib.doneWithAJAX();
         if(text === 'OK')
         {
            // Nothing, because this is passive
         }
      },
      error: function(xmlhttprequest, textstatus, message)
      {
         // DefaultLib.diagnose(textstatus, message);
      }
      });
   }
}

RefreshLib.getNewMessages = function(offset, callback)
{
   if(offset < 0 || RefreshLib.refreshScript === "")
      return;
   
   var splittedRefresh = RefreshLib.refreshScript.split('?');
   
   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/' + splittedRefresh[0], 
   data: splittedRefresh[1] + '&offset=' + offset + '&per_page=' + PagesLib.nbMsgsPerPage,
   timeout: 5000,
   success: function(data)
   {
      // No particular error message handling, nor AJAX lock, because this is passive
      if(data.length !== 0 && data !== 'Bad arguments' && data !== 'No message' && data !== 'DB error' && data !== 'Template error')
      {
         var multiplePages = false;
         if(data.startsWith("New pages"))
         {
            data = data.substring(10);
            multiplePages = true;
         }
         var nbNewPosts = (data.match(/class="postBlock"/g) || []).length;
         if(nbNewPosts == 0)
            return;
         
         var lastNewPage = 0;
         if(!multiplePages)
         {
            $(data).hide().appendTo('.page:last').show(500);
         }
         else
         {
            var newContent = $(data);
            var pageIndex = parseInt(newContent.filter('.page:first').attr('data-page'));
            lastNewPage = parseInt(newContent.filter('.page:last').attr('data-page'));
            if(pageIndex == parseInt($('.page:last').attr('data-page')))
            {
               $('.page:last').append(newContent.filter('.page:first').html());
               pageIndex++;
            }
            
            for(i = pageIndex; i <= lastNewPage; i++)
            {
               if(PagesLib.navMode == PagesLib.NAV_MODE_FLOW)
                  newContent.filter('.page[data-page=' + i + ']').hide().appendTo($('#postsWrapper')).show(500);
               else
               {
                  newContent.filter('.page[data-page=' + i + ']').hide().appendTo($('#postsWrapper'));
                  if(!($('.pagesNav').is(':visible')))
                     $('.pagesNav').show(500);
               }
            }
         }
         
         // Binds events (same as seen in topic_interaction.js and post_interaction.js), one post at a time
         for(i = 0; i < nbNewPosts; i++)
         {
            var postID = offset + i + 1;
            $('.postBlock[id=' + postID + '] .spoiler a:first-child').on('click', function() { DefaultLib.showSpoiler($(this).attr('data-id-spoiler')); });
            $('.postBlock[id=' + postID + '] .miniature').on('click', function() { DefaultLib.showUpload($(this)); });
            $('.postBlock[id=' + postID + '] .videoThumbnail').on('click', function() { DefaultLib.showVideo($(this).attr('data-video-id'), $(this).attr('data-post-id')); });
            
            // Specific for forum posts
            if($('.postBlock[id=' + postID + '] .postInteractions').length)
            {
               $('.postBlock[id=' + postID + '] .postInteractions').on('click', function() { TopicInteractionLib.showInteractions($(this).attr('data-post')); });
               $('.postAttachment[id=Attachment' + postID + '] .uploadDisplay .uploadDisplayAlign img').on('click', function() { DefaultLib.showUpload($(this).parent().parent()); });
               $('.postAttachment[id=Attachment' + postID + '] .uploadDisplay .uploadDisplayAlign video').on('click', function() { DefaultLib.showUpload($(this).parent().parent()); });
               $('.postAttachment[id=Attachment' + postID + '] .link_masked_attachment').on('click', function() { $('#maskedAttachment' + $(this).attr('data-id-post')).toggle(300); });
               $('.postBlock[id=' + postID + '] .link_masked_post').on('click', function() { $('#masked' + $(this).attr('data-id-post')).toggle(300); });
               $('.postBlock[id=' + postID + '] .report').on('click', function() { PostInteractionLib.getAlertMotivations($(this).attr("data-post")); });
               $('.postBlock[id=' + postID + '] .vote').on('click', function()
               {
                  vote = parseInt($(this).attr("data-vote"));
                 
                  if(vote > 1)
                     vote = 1;
                  else if(vote < -1)
                     vote = -1;
                  
                  if(vote == 0)
                     return;
                 
                  post = parseInt($(this).attr("data-post"));
                  PostInteractionLib.ratePost(post, vote);
               });
               
               $('.postBlock[id=' + postID + '] .pin').on('click', function()
               {
                  postID = parseInt($(this).attr("data-post"));
                  var curIcon = $(this).attr('src');
                  if(curIcon.indexOf("post_pin") !== -1)
                  {
                     $('#pinPost .pinPostID').text(postID);
                     DefaultLib.openDialog('#pinPost');
                  }
                  else
                     PostInteractionLib.unpinPost(postID);
               });
               
               $('.postBlock[id=' + postID + '] .quote').on('click', function() { PostInteractionLib.quotePost($(this).attr('data-post')); });
            }
            
            /*
            * We also add an attribute "data-viewed" to mark the message as unviewed. This 
            * attribute will be removed on scroll over the message.
            */
            
            $('.postBlock[id=' + postID + ']').attr('data-viewed', 'no');
            
            /*
            * And a mouseover event to consider the post as viewed, if the page is not large 
            * enough to scroll over it.
            */
            
            $('.postBlock[id=' + postID + ']').on('mouseover', function()
            {
               if($(this).visible(true)) // From jQuery plugin jquery.visible.js
               {
                  $(this).removeAttr('data-viewed');
                  RefreshLib.unseen--;
                  if(RefreshLib.unseen < 0) // Just in case
                     RefreshLib.unseen = 0;
               }
               
               $(this).unbind('mouseover');
               RefreshLib.refreshTitleAndState();
            });
         }
         
         // Updates page title and unseen counter
         if(!RefreshLib.fromOneself || nbNewPosts > 1)
         {
            var previouslyUnseen = RefreshLib.unseen;
            RefreshLib.unseen += nbNewPosts;
            if(previouslyUnseen == 0)
            {
               document.title = "(" + RefreshLib.unseen + ") " + document.title;
            }
            else
            {
               var curString = document.title.split(") ");
               var oldString = curString.slice(1).join(") ");
               document.title = "(" + RefreshLib.unseen + ") " + oldString;
            }
         }
         RefreshLib.fromOneself = false;
         
         callback(lastNewPage); // To set page lists
      }
      RefreshLib.lock = false;
   },
   error: function(xmlhttprequest, textstatus, message)
   {
      DefaultLib.diagnose(textstatus, message);
      RefreshLib.lock = false;
   }
   });
}

// Binds events and implements quick reply

$(document).ready(function()
{
   RefreshLib.refreshScript = $('.pagesNav:first').attr('data-refresh');
   if(typeof RefreshLib.refreshScript === typeof undefined || RefreshLib.refreshScript === false)
      RefreshLib.refreshScript = "";
   RefreshLib.allSeenScript = $('.pagesNav:first').attr('data-all-seen');
   if(typeof RefreshLib.allSeenScript === typeof undefined || RefreshLib.allSeenScript === false)
      RefreshLib.allSeenScript = "";

   // Activates/deactivates auto-refresh
   $('#navMenu .navRefresh').on('click', function()
   {
      RefreshLib.changeAutoRefresh();
   });
   
   // Quick reply
   if($('#replyForm').length)
   {
      // From: https://stackoverflow.com/questions/5721724/jquery-how-to-get-which-button-was-clicked-upon-form-submission
      $('#replyForm').submit(function()
      {
         var val = $('input[type=submit][clicked=true]').val();
         $('input[type=submit][clicked=true]').removeAttr('clicked');
         if(RefreshLib.autoRefresh != null && val !== 'Mode avancé')
         {
            var textToProcess = encodeURIComponent($('#replyForm textarea').val());
            if(textToProcess === '')
            {
               alert('Entrez un message.');
               return false;
            }
            var dest = $('#replyForm').attr('data-ajax').split('?');
            
            var additionnalPart = "";
            var replaceForm = false;
            // For forms allowing anonymous users (only forum posts, so far)
            if($('#replyForm input[name=pseudo]').length && $('#replyForm input[name=captcha]').length)
            {
               additionnalPart += "&pseudo=" + $('#replyForm input[name=pseudo]').val();
               additionnalPart += "&captcha=" + $('#replyForm input[name=captcha]').val();
            }
            // For pings
            else if($('#replyForm input[name=archive]').length)
            {
               if($('#replyForm input[name=archive]').is(':checked'))
               {
                  additionnalPart += "&archive=" + $('#replyForm input[name=archive]').val();
                  replaceForm = true;
               }
            }

            // 1) Sends the new message
            if(DefaultLib.isHandlingAJAX())
               return false;
            
            $.ajax({
            type: 'POST',
            url: DefaultLib.httpPath + 'ajax/' + dest[0], 
            data: dest[1] + '&message=' + textToProcess + additionnalPart,
            timeout: 5000,
            success: function(text)
            {
               DefaultLib.doneWithAJAX();
               if(text === 'Locked')
                  alert('Le sujet a été verrouillé.');
               else if(text === 'Archived')
                  alert('Cette discussion a été archivée.');
               else if(text === 'Too many posts')
                  alert('Vous ne pouvez pas écrire plus de deux messages en moins de 15 secondes.');
               else if(text === 'No anon')
                  alert('Ce sujet n\'est pas/plus ouvert aux anonymes.');
               else if(text === 'Anon pseudo too long')
                  alert('Le pseudo que vous avez choisi est trop long.');
               else if(text === 'Anon pseudo unavailable')
                  alert('Le pseudo que vous souhaitez utiliser n\'est pas disponible.');
               else if(text === 'Anon wrong captcha')
                  alert('Le captcha n\'est pas correct.');
               else if(text === 'Anon too many posts')
                  alert('Vous ne pouvez pas écrire plus de deux messages en 2 minutes en tant qu\'anonyme.');
               else if(text === 'DB error')
                  alert('Une erreur de base de données est survenue. Réessayez plus tard ou prévenez un administrateur.');
               else if(text === 'OK')
               {
                  if($('#slidingBlock').is(':visible'))
                     $('#visibleWrapper').css('margin-bottom', 0);
                  else
                     $('#visibleWrapper').css('margin-bottom', $('#slidingBlock').height() - 100);
                  $('#slidingBlock').slideToggle();
                  
                  if(replaceForm)
                  {
                     $('#replyForm').remove();
                     $('#slidingBlock').html("<h1>Discussion archivée</h1>\n<p>Vous avez archivé cette discussion.</p>");
                  }
                  // Resets form
                  else
                  {
                     $('#replyForm textarea').val('');
                     if($('#replyForm input[name=captcha]').length)
                     {
                        var id = Math.random();
                        $('#Captcha').attr('src', DefaultLib.httpPath + 'Captcha.php?id='+id);
                        $('#replyForm input[name=captcha]').val('');
                     }
                     
                     // If quick preview is activated, we empty the zone as well
                     if($('#previewZone').length)
                        $('#previewZone p').html('');
                  }
                  RefreshLib.fromOneself = true;
                  RefreshLib.refreshPosts(); // 2) Calls the code to load all new messages
               }
               else
                  alert('Une erreur inconnue est survenue. Réessayez plus tard ou prévenez un administrateur.');
            },
            error: function(xmlhttprequest, textstatus, message)
            {
               DefaultLib.doneWithAJAX();
               DefaultLib.diagnose(textstatus, message);
            }
            });
            
            return false;
         }
      });
      
      $('#replyForm input[type=submit]').on('click', function()
      {
         $('input[type=submit]', $(this).parents('#replyForm')).removeAttr('clicked');
         $(this).attr('clicked', 'true');
      });
      
      $('#replyForm input[type=submit]').on('keyup', function(event)
      {
         if(event.which == 32) // Space bar
            event.preventDefault();
      });
   }
});

/*
* On scroll, if there are unviewed messages brought by the refresh, we check if they are visible 
* by the user, decrements the RefreshLib.unseen counter as a consequence and updates the window 
* title.
*/

$(window).on('scroll', function()
{
   if(RefreshLib.unseen == 0)
      return;

   $('.postBlock[data-viewed=no]:visible').each(function()
   {
      if($(this).visible(true)) // From jQuery plugin jquery.visible.js
      {
         $(this).removeAttr('data-viewed');
         RefreshLib.unseen--;
         if(RefreshLib.unseen < 0) // Just in case
            RefreshLib.unseen = 0;
      }
      
      $(this).unbind('mouseover');
   });
   
   RefreshLib.refreshTitleAndState();
});