/**
* This script is made to handle the navigation, which includes:
* -unhiding hidden pages in the page selection, 
* -switching between navigation modes (static, dynamic, flow), 
* -handling each mode.
*
* The navigation modes are the following:
* -Static: this is the classical pagination, with only one page being displayed at once and page 
*  links leading to a reload of the topic with another page. This is the default navigation mode 
*  and how it would work without the pages.js script.
* -Dynamic: an improved pagination, it consists in replacing page links with events that load the 
*  selected pages and add it to the document. This avoids reloading the whole topic page by just 
*  loading the next page. If we want to view again a page that was previously displayed, the 
*  script just uses hide() and show() to switch without any additionnal AJAX stuff.
* -Flow: this last mode partially removes the notion of pages by removing the pages list, showing 
*  all loaded pages and replacing missing pages by placeholders. What is interesting with this 
*  mode is that it loads automatically the next page when one gets to the last placeholder. It is 
*  therefore possible to view a whole topic by starting on page 1, activating flow mode, and just 
*  scrolling down. It is worth noting the automatic loading only occurs when scrolling down, i.e., 
*  former pages are only loaded on demand (either one at once, either all at once).
*/

var PagesLib = {};

// Constants for each navigation mode
PagesLib.NAV_MODE_STATIC = 1;
PagesLib.NAV_MODE_DYNAMIC = 2;
PagesLib.NAV_MODE_FLOW = 3;
PagesLib.navMode = PagesLib.NAV_MODE_STATIC; // Mode currently used
PagesLib.nbMsgsPerPage = 20; // 20 by default
PagesLib.getter = ""; // Set at binding
PagesLib.scrollToBottom = false; // Set to true when one needs to scroll to bottom (after using bottom .pagesNav)
PagesLib.scrollLock = false; // Used to block multiple calls to load() while scrolling over last placeholder (flow mode)

// Returns the page that is currently visible at the screen.

PagesLib.getViewedPage = function()
{
   var viewedPage = "";
   var nbPages = $('.pagesNav:first .pageLink').length + 1;
   for(i = 1; i <= nbPages; i++)
      if($('.page[data-page=' + i + ']').is(':visible'))
         if($('.page[data-page=' + i + ']').visible(true))
            return $('.page[data-page=' + i + ']').attr('data-page');
   
   /*
    * N.B.: is(':visible') and .visible(true) are both needed, because .visible(true) on a hidden 
    * page which div is somewhere in the screen will still return true.
    */
   
   return "None"; // Unlikely
}

// Returns the first post that is currently visible at the screen.

PagesLib.getViewedPost = function()
{
   var onscreenPost = 0;
   var firstVisible = parseInt($('.postBlock:visible:first').attr('id'));
   var lastVisible = parseInt($('.postBlock:visible:last').attr('id'));
   for(i = firstVisible; i <= lastVisible; i++)
   {
      if($('.postBlock[id=' + i + ']').visible(true))
      {
         var headerBottom = 0;
         if($('#topicHeader').length)
            headerBottom = $('#topicHeader').offset().top + $('#topicHeader').height();
         else if($('#pingsContent').length)
            headerBottom = $('#pingsContent .centeredTitle').offset().top + $('#pingsContent .centeredTitle').height();
         var blockBottom = $('.postBlock[id=' + i + ']').offset().top + $('.postBlock[id=' + i + ']').height();
         
         // Ensures we align on the last post visible below the topic header
         if(headerBottom < blockBottom)
         {
            onscreenPost = i;
            break;
         }
      }
   }
   
   return onscreenPost;
}

PagesLib.showHiddenPages = function()
{
   // Unbinds events
   $('.unhidePages').unbind();

   // First operates on the above pagesNav
   var parent = $('.pagesNav').first();
   var hidden = parent.find('.hiddenPages').html();
   var nbHidden = $(hidden).filter('.pageLink').length;
   
   if(nbHidden > 10)
   {
      var firstFive = parent.find('.hiddenPages > .pageLink').slice(0, 5);
      var lastFive = parent.find('.hiddenPages > .pageLink').slice(-5);
      var unhideButton = parent.find('.unhidePages');
      
      firstFive.insertBefore(parent.find('.hiddenPages')).after(' ');
      lastFive.insertAfter(parent.find('.unhidePages')).after(' ');
      parent.find('.unhidePages').after(' ');
   }
   else
   {
      $(parent).find('.hiddenPages').replaceWith(hidden);
      $(parent).find('.unhidePages').remove();
   }
   
   // Then replicates on the bottom pagesNav
   $('.pagesNav').last().replaceWith($(parent).clone());
   
   // And rebinds events
   if($('.unhidePages').length)
      $('.unhidePages').on('click', function() { PagesLib.showHiddenPages(); });
}

/*
* Main method to switch from a navigation mode to another. It calls subsequent methods to do 
* the "deep" changes, as this function only focuses on the button display.
*/

PagesLib.switchNavMode = function(mode)
{
   if(mode < PagesLib.NAV_MODE_STATIC || mode > PagesLib.NAV_MODE_FLOW || mode == PagesLib.navMode)
      return;
   
   var prevMode = PagesLib.navMode;
   PagesLib.navMode = mode;
   $('#navMenu .navMode[data-mode=' + PagesLib.navMode + ']').animate({opacity: 1.0}, 300);
   switch(PagesLib.navMode)
   {
      case PagesLib.NAV_MODE_STATIC:
         $('#navMenu .navMode[data-mode=2]').animate({opacity: 0.5}, 300);
         $('#navMenu .navMode[data-mode=3]').animate({opacity: 0.5}, 300);
         PagesLib.toStatic(prevMode);
         break;
      case PagesLib.NAV_MODE_DYNAMIC:
         $('#navMenu .navMode[data-mode=1]').animate({opacity: 0.5}, 300);
         $('#navMenu .navMode[data-mode=3]').animate({opacity: 0.5}, 300);
         PagesLib.toDynamic(prevMode);
         break;
      case PagesLib.NAV_MODE_FLOW:
         $('#navMenu .navMode[data-mode=1]').animate({opacity: 0.5}, 300);
         $('#navMenu .navMode[data-mode=2]').animate({opacity: 0.5}, 300);
         PagesLib.toFlow(prevMode);
         break;
      default:
         break;
   }
}

/*
* Updates the selected page.
*/

PagesLib.updateSelectedPage = function(displayedPage)
{
   var previousPage = $('.pageLinkSelected').attr('data-page');
   if(previousPage !== displayedPage)
   {
      $('.pageLinkSelected').removeClass('pageLinkSelected').addClass('pageLink');
      $('.pageLink[data-page=' + displayedPage + ']').removeClass('pageLink').addClass('pageLinkSelected');
   }
}

/*
* Transition to static paging. It resets .pageLink elements to "static" links. If we come from the 
* flow mode, then we only keep the visible page and hides the others (can still be useful if we go 
* to dynamic paging again).
*/

PagesLib.toStatic = function(previousMode)
{
   if(!($('.pagesNav:first span').length > 1))
      return; // Nothing to do

   if(previousMode == PagesLib.NAV_MODE_FLOW)
   {
      // Remove all placeholders
      $('.pagePlaceholder').remove();
   
      // Gets currently visible page and hide others
      var viewedPage = PagesLib.getViewedPage();
      $('.page').each(function() 
      {
         if($(this).attr('data-page') !== viewedPage)
            $(this).hide();
      });
      
      // Updates page lists and show them again
      PagesLib.updateSelectedPage(viewedPage);
      $('.pageLinkSelected').html($('.pageLinkSelected').attr('data-page'));
      $('.pagesNav').show(500);
   }

   var staticLink = $('.pagesNav:first').attr('data-static-link').split('[]');
   
   // Removes any event tied to .pageLink elements
   $('.pageLink').unbind();
   
   // Puts pack the URL to each page
   $('.pageLink').each(function()
   {
      var page = $(this).attr('data-page');
      var newURL = staticLink[0] + page.toString();
      if(staticLink[1] !== '')
         newURL += staticLink[1];
      $(this).html('<a href="' + newURL + '">' + page + '</a>');
   });
}

/*
* Transition to dynamic paging. It resets .pageLink elements to remove eventual links and tie the 
* dynamicSwitchPage() function to each of them. If we come from the flow mode, then we keep the 
* visible page on screen and hide the others.
*/

PagesLib.toDynamic = function(previousMode)
{
   if(!($('.pagesNav:first span').length > 1))
      return; // Nothing to do

   if(previousMode == PagesLib.NAV_MODE_FLOW)
   {
      // Remove all placeholders
      $('.pagePlaceholder').remove();
   
      var viewedPage = PagesLib.getViewedPage();
      $('.page').each(function() 
      {
         if($(this).attr('data-page') !== viewedPage)
            $(this).hide();
      });
      
      // Updates page lists and show them again
      PagesLib.updateSelectedPage(viewedPage);
      $('.pageLinkSelected').html($('.pageLinkSelected').attr('data-page'));
      $('.pagesNav').show(500);
       
       // Resets events just in case (dynamic -> flow -> dynamic => double events)
      $('.pageLink').unbind(); 
   }
   
   $('.pageLink').each(function()
   {
      $(this).html($(this).attr('data-page'));
   });
   
   $('.pageLink').on('click', function()
   {
      if($(this).closest('.pagesNav').is('.pagesNav:last'))
         PagesLib.scrollToBottom = true;
      PagesLib.dynamicSwitchPage(parseInt($(this).attr('data-page')));
   });
}

/*
* Additionnal methods to handle the dynamic paging system (i.e., a page is loaded without 
* re-loading the entire page, and already loaded pages can be re-viewed without any other 
* request). The first is to load or show the selected page, the second is to finish operation by 
* updating the page lists and scrolling to bottom if necessary. The main purpose of the second 
* function is to avoid writing a same piece of code twice. Note the "delayedScroll" parameter in 
* the same function; it is used to drop the delay for scrolling if we loaded a new page rather 
* than switching to an already loaded page.
*/

PagesLib.completeDynamicSwitch = function(pageToLoad, delayedScroll)
{
   // Unbinds the click event on the clicked .pageLink element (soon a .pageLinkSelected)
   $('.pageLink[data-page=' + pageToLoad + ']').unbind();

   // Changes selected page
   var previousPage = $('.pageLinkSelected').attr('data-page');
   PagesLib.updateSelectedPage(pageToLoad);
   
   // Binds click event to the previous page, now a .pageLink element
   $('.pageLink[data-page=' + previousPage + ']').on('click', function()
   {
      if($(this).closest('.pagesNav').is('.pagesNav:last'))
         PagesLib.scrollToBottom = true;
      PagesLib.dynamicSwitchPage(parseInt($(this).attr('data-page')));
   });
   
   if(PagesLib.scrollToBottom)
   {
      PagesLib.scrollToBottom = false;
      if(delayedScroll)
         setTimeout(function() { $(document).scrollTop($(document).height()); }, 300);
      else
         $(document).scrollTop($(document).height());
   }
}

PagesLib.dynamicSwitchPage = function(pageToLoad)
{
   // Loads page or re-displays it
   if($('.page[data-page=' + pageToLoad + ']').length)
   {
      $('.page[data-page=' + pageToLoad + ']').show();
      $('.page').each(function()
      {
         if(parseInt($(this).attr('data-page')) != pageToLoad)
            $(this).hide();
      });
      
      PagesLib.completeDynamicSwitch(pageToLoad, false);
   }
   else
   {
      PagesLib.getMessages((pageToLoad - 1) * PagesLib.nbMsgsPerPage, function()
      {
         $('.page').each(function()
         {
            if(parseInt($(this).attr('data-page')) != pageToLoad)
               $(this).hide();
         });
         
         PagesLib.completeDynamicSwitch(pageToLoad, true);
      });
   }
}

/*
* Transition to flow mode. It hides the .pagesNav blocks and make all available .page block 
* displayed to move to the "flow" display mode. It also appends additionnal blocks for missing 
* pages, with the bottom block being used for automatic loading of the last pages on scroll.
*/

PagesLib.toFlow = function(previousMode)
{
   if(!($('.pagesNav:first span').length > 1))
      return; // Nothing to do
   
   // Finds current page, first on-screen post and total amount of pages
   var onscreenPost = PagesLib.getViewedPost();
   var currentlyShown = parseInt($('.pageLinkSelected:first').attr('data-page'));
   var nbPages = $('.pagesNav:first .pageLink').length + 1;
   $('.pagesNav').hide(500);
   
   var consecutive = 0;
   for(i = 1; i <= nbPages; i++)
   {
      if($('.page[data-page=' + i + ']').length)
      {
         if(currentlyShown != i)
            $('.page[data-page=' + i + ']').show();
         
         // Inserts a placeholder
         if(consecutive > 0)
         {
            PagesLib.createPlaceholder(i - consecutive, i - 1);
            consecutive = 0;
         }
      }
      else
         consecutive++;
   }
   
   // Inserts the last placeholder
   if(consecutive > 0)
      PagesLib.createPlaceholder(nbPages - consecutive + 1, nbPages);
   
   // Scrolls to last post being seen if not on top of the page
   if(onscreenPost > 0)
   {
      if(!($('.topicKeywords').visible()) && !($('.centeredTitle').visible()))
         $(document).scrollTop($('.postBlock[id=' + onscreenPost + ']').offset().top - 95);
   }
}

/*
* Additionnal methods to handle the flow mode. The main "challenge" is to handle the page 
* placeholders that replace missing pages after transitioning to flow mode or having loaded a new 
* page into the flow.
*/

// Creates a placeholder, given the first and last page it should represent

PagesLib.createPlaceholder = function(firstPage, lastPage)
{
   var newBlock = '<div class="pagePlaceholder" data-first-page="' + firstPage + '" ';
   newBlock += 'data-last-page="' + lastPage + '"><p>';
   if(firstPage == lastPage)
   {
      newBlock += '<span class="singleMissingPage" data-page="' + firstPage + '">';
      newBlock += 'Charger la page ' + firstPage + '</span>';
   }
   else
   {
      for(i = firstPage; i <= lastPage; i++)
         newBlock += '<span class="missingPage" data-page="' + i + '">' + i + '</span> ';
      newBlock += '<span class="missingPages" data-page="' + firstPage + ' - ' + lastPage + '">';
      newBlock += 'Tout charger</span>';
   }
   newBlock += '</p></div>';
   
   if($('.page[data-page=' + (lastPage + 1) + ']').length)
      $(newBlock).insertBefore($('.page[data-page=' + (lastPage + 1) + ']'));
   else
      $('#postsWrapper').append(newBlock);
   
   // Binds events
   var parent = '.pagePlaceholder[data-first-page=' + firstPage + '][data-last-page=' + lastPage + ']';
   if($(parent + ' .missingPage').length)
   {
      $(parent + ' .missingPage').on('click', function()
      {
         load($(this).attr('data-page'));
      });
      $(parent + ' .missingPages').on('click', function()
      {
         var pages = $(this).attr('data-page').split(' - ');
         var firstPage = parseInt(pages[0]);
         var lastPage = parseInt(pages[1]);
         $('.pagePlaceholder[data-last-page=' + lastPage + ']').animate({opacity: 0.5}, 300);
         PagesLib.loadAll(firstPage, lastPage);
      });
   }
   else
   {
      $(parent + ' .singleMissingPage').on('click', function()
      {
         PagesLib.load($(this).attr('data-page'));
      });
   }
}

/*
* Loads a page. This method checks beforehand if the page is a single missing page (e.g., if 
* pages 1 and 3 have been loaded, 2 is a "single missing") or in a sequence of missing pages (e.g. 
* pages 1 and 4, 2 and 3 are missing) in order to create new placeholders after a page has been 
* loaded, if necessary.
*/

PagesLib.load = function(page)
{
   // Determines if the placeholder models a single page or not and hides it
   var isSingle = false;
   if($('.singleMissingPage[data-page=' + page + ']').length)
   {
      isSingle = true;
      $('.singleMissingPage[data-page=' + page + ']').closest('.pagePlaceholder').animate({opacity: 0.5}, 300);
   }
   else
   {
      $('.missingPage[data-page=' + page + ']').closest('.pagePlaceholder').animate({opacity: 0.5}, 300);
   }
   
   var pageInt = parseInt(page);
   PagesLib.getMessages((pageInt - 1) * PagesLib.nbMsgsPerPage, function()
   {
      if(isSingle)
      {
         $('.pagePlaceholder[data-first-page=' + page + ']').remove();
      }
      else
      {
         var parent = $('.missingPage[data-page=' + page + ']').closest('.pagePlaceholder');
         var parentLowerBound = parseInt(parent.attr('data-first-page'));
         var parentUpperBound = parseInt(parent.attr('data-last-page'));
         
         parent.remove();
         if(parentLowerBound < pageInt)
            PagesLib.createPlaceholder(parentLowerBound, pageInt - 1);
         if(pageInt < parentUpperBound)
            PagesLib.createPlaceholder(pageInt + 1, parentUpperBound);
      }
      
      PagesLib.scrollLock = false;
   });
}

/*
* Loads all pages matching a given placeholder. Works in a recursive manner: each time a page is 
* loaded, the method calls itself with an incremented curPage to load the next page, and that 
* until the last page has been loaded (curPage == endPage). No other placeholder is created in 
* this case.
*/

PagesLib.loadAll = function(curPage, endPage)
{
   if(curPage > endPage)
   {
      $('.pagePlaceholder[data-last-page=' + endPage + ']').remove();
      return;
   }
   
   PagesLib.getMessages((curPage - 1) * PagesLib.nbMsgsPerPage, function()
   {
      PagesLib.loadAll(curPage + 1, endPage);
   });
}

/*
* Methods that executes the AJAX request that will fetch new messages from the topic. It is used 
* for both the dynamic paging and the flow navigation modes.
*/

PagesLib.getMessages = function(offset, callback)
{
   if(offset < 0 || PagesLib.getter === "")
      return;
   
   if(DefaultLib.isHandlingAJAX())
      return;
   
   var splittedGetter = PagesLib.getter.split('?');

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/' + splittedGetter[0], 
   data: splittedGetter[1] + '&offset=' + offset + '&amount=' + PagesLib.nbMsgsPerPage,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      
      var errorMsgEnd = ' Réessayez plus tard ou prévenez un administrateur.';
      if(data.length !== 0)
      {
         if(data === 'Bad arguments')
         {
            alert('Une erreur de requête s\'est produite.' + errorMsgEnd);
         }
         else if(data === 'DB error')
         {
            alert('Une erreur de base de données s\'est produite.' + errorMsgEnd);
         }
         else if(data === 'No message')
         {
            alert('Aucun message n\'a pu être trouvé avec votre requête.');
         }
         else if(data === 'Template error')
         {
            alert('Une erreur de formattage s\'est produite.' + errorMsgEnd);
         }
         else
         {
            // Everything OK
            var pageNumber = ((offset / PagesLib.nbMsgsPerPage) + 1).toString();
            var newPage = '<div class="page" data-page="' + pageNumber + '">\n' + data + '\n</div>';
            var inserted = false;
            for(i = pageNumber - 1; i >= 0; i--)
            {
               if($('.page[data-page=' + i + ']').length)
               {
                  $(newPage).insertAfter($('.page[data-page=' + i + ']'));
                  inserted = true;
                  break;
               }
            }
            
            if(!inserted)
               $('#postsWrapper').prepend(newPage);
            
            // Binds events (same as seen in topic_interaction.js and post_interaction.js)
            $('.page[data-page=' + pageNumber + '] .spoiler a:first-child').on('click', function() { DefaultLib.showSpoiler($(this).attr('data-id-spoiler')); });
            $('.page[data-page=' + pageNumber + '] .miniature').on('click', function() { DefaultLib.showUpload($(this)); });
            $('.page[data-page=' + pageNumber + '] .videoThumbnail').on('click', function() { DefaultLib.showVideo($(this).attr('data-video-id'), $(this).attr('data-post-id')); });
            
            // Specific for forum posts
            if($('.page[data-page=' + pageNumber + '] .postInteractions').length)
            {
               $('.page[data-page=' + pageNumber + '] .postInteractions').on('click', function() { TopicInteractionLib.showInteractions($(this).attr('data-post')); });
               $('.page[data-page=' + pageNumber + '] .uploadDisplay .uploadDisplayAlign').on('click', function() { DefaultLib.showUpload($(this).parent()); });
               $('.page[data-page=' + pageNumber + '] .link_masked_post').on('click', function() { $('#masked' + $(this).attr('data-id-post')).toggle(300); });
               $('.page[data-page=' + pageNumber + '] .link_masked_attachment').on('click', function() { $('#maskedAttachment' + $(this).attr('data-id-post')).toggle(300); });
               $('.page[data-page=' + pageNumber + '] .report').on('click', function() { PostInteractionLib.getAlertMotivations($(this).attr("data-post")); });
               $('.page[data-page=' + pageNumber + '] .vote').on('click', function()
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
               
               $('.page[data-page=' + pageNumber + '] .pin').on('click', function()
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
               
               $('.page[data-page=' + pageNumber + '] .quote').each(function()
               {
                  var idPost = $(this).attr('data-post');
                  $('.quote[data-post=' + idPost + ']').on('click', function()
                  {
                     PostInteractionLib.quotePost(idPost);
                  });
               });
            }
            
            callback();
         }
      }
      else
      {
         alert('Une erreur inconnue s\'est produite.' +errorMsgEnd);
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
   // Gets amount of messages per page
   PagesLib.nbMsgsPerPage = parseInt($('.pagesNav:first').attr('data-per-page'));
   PagesLib.getter = $('.pagesNav:first').attr('data-getter');
   
   // Annotates current page
   var firstVisibleID = parseInt($('.page .postBlock:first').attr('id'));
   var page = Math.ceil(firstVisibleID / PagesLib.nbMsgsPerPage);
   $('.page').attr('data-page', page.toString());

   // Change navigation mode
   $('#navMenu .navMode').on('click', function()
   {
      PagesLib.switchNavMode(parseInt($(this).attr('data-mode')));
   });

   // Button to show more pages
   if($('.unhidePages').length)
      $('.unhidePages').on('click', function() { PagesLib.showHiddenPages(); });
});

// Handles F5 press to redirect to the right page at page refresh.
$(document).keydown(function(e)
{
   if(e.keyCode == 116 && $('.pagesNav').length)
   {
      e.preventDefault(); // Prevents browser behaviour regarding F5
   
      var viewedPage = PagesLib.getViewedPage();
      var staticLink = $('.pagesNav').attr('data-static-link').split('[]');
      var redirectURL = staticLink[0] + viewedPage;
      if(staticLink[1] !== '')
         redirectURL += staticLink[1];
      
      if($('.topicKeywords').visible() || $('.centeredTitle').visible())
      {
         window.location.replace(redirectURL);
      }
      else
      {
         // Finds the first on-screen .postBlock that is below the topic header
         var onscreenPost = PagesLib.getViewedPost();
         if(onscreenPost > 0)
         {
            // Just in case
            viewedPage = $('.postBlock[id=' + onscreenPost + ']').parent().attr('data-page');
            redirectURL = staticLink[0] + viewedPage;
            if(staticLink[1] !== '')
               redirectURL += staticLink[1];
         
            // If on the same page as initially, we need to force the refresh (because of anchor)
            if(window.location.search.indexOf("page=" + viewedPage) >= 0)
            {
               window.location.replace(redirectURL + '#' + onscreenPost);
               $(document).scrollTop($(window.location.hash).offset().top - 95);
               window.location.reload(true);
            }
            // Otherwise, business as usual
            else
            {
               window.location.replace(redirectURL + '#' + onscreenPost);
            }
         }
      }
   }
});

/*
* Last but not least: in flow mode, the last pages must be loaded automatically on scroll. To do 
* so, we check at scroll that we are in flow mode, that we have a final placeholder, and if yes, 
* we check if we crossed the corresponding placeholder and loads the next page.
*/

$(window).on('scroll', function()
{
   if(PagesLib.scrollLock)
      return;

   if(PagesLib.navMode != PagesLib.NAV_MODE_FLOW)
      return;
   
   if(!($('.pagePlaceholder').length && $('.pagePlaceholder:last').is(':last-child')))
      return;
   
   var placeholderOffset = $('.pagePlaceholder:last').offset().top;
   var scrollPosition = $(this).scrollTop();
   var windowHeight = $(window).height();
   
   if(scrollPosition + windowHeight > placeholderOffset)
   {
      PagesLib.scrollLock = true;
      PagesLib.load($('.pagePlaceholder:last').attr('data-first-page'));
   }
});
