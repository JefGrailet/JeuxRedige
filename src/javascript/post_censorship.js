/**
* This file contains code fragments to handle the censorship button in the history of a post. The 
* code is very similar to the report feature in post_scoring.js, so there is not much to say here.
*/

var PostCensoringLib = {};

/*
* censorPost() censors a given archived post (identified by its ID and version). It is done 
* through a single AJAX request, which the callback provides a integer giving
*
* @param integer post     The ID of the post which a version is being censored
* @param integer version  The version number
*/

PostCensoringLib.censorPost = function(post, version)
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/Censor.php', 
   data: 'id_post='+post+'&version_num='+version,
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
   
      var dataInt = parseInt(data);
      if(dataInt < 1) // Stops if 0, -1 or -2 returned (no censorship occurred)
         return;
      
      $('.censorship[data-id-post='+post+'][data-version='+version+']').unbind();
      $('.censorship[data-id-post='+post+'][data-version='+version+']').attr('title', 'Censuré');
      $('.censorship[data-id-post='+post+'][data-version='+version+']').animate({opacity: 1.0}, 300, function()
      {
         $('.censorship[data-id-post='+post+'][data-version='+version+']').css({cursor: 'default'});
      });
      
      // Updates the display
      var content = $('#wrapper'+version).html();
      var newContent = '<p><span style="color: grey;">Cette version du message a été censurée ' + 
      'par un modérateur. Par conséquent, son contenu a été masqué à titre préventif.<br/>\r\n' +
      '<br/>\r\n' +
      '<a href="javascript:void(0)" class="link_masked_post" data-id-post="'+version+'">Cliquez ici</a>\r\n' +
      'pour afficher/masquer ce contenu.</span>\r\n' +
      '</p>\r\n'+
      '<div id="masked'+version+'" style="display: none;">\r\n' + content + '\r\n</div>';
   
      $('#wrapper'+version).fadeOut(500, function()
      {
         $('#wrapper'+version).html(newContent);
         
         // Adds event
         $('.link_masked_post[data-id-post="'+version+'"]').on('click', function()
         {
            var toMask = $(this).attr('data-id-post');
            $('#masked'+toMask).toggle(300);
         });
         
         $('#wrapper'+version).fadeIn(500);
      });
      
      // Does the same with the attachments, if any
      if($('#Attachment' + version + ' .postAttachmentAlign').length)
      {
         // Don't touch attachment if already hidden
         if($('#maskedAttachment' + version).length)
            return;
      
         var attachment = $('#Attachment' + version + ' .postAttachmentAlign').html();
         var newAttachment = '<p><a href="javascript:void(0)" class="link_masked_attachment" ' +
         'data-id-post="' + version + '">Cliquez ici</a> pour afficher/masquer ' +
         'les uploads liés à ce message (<strong>censuré</strong>).</span></p>' + "\n" +
         '<div id="maskedAttachment' + version + '" style="display: none;">' + "\n" +
         attachment + "\n" +
         '</div>' + "\n";
         
         $('#Attachment' + version + ' .postAttachmentAlign').fadeOut(500, function()
         {
            $('#Attachment' + version + ' .postAttachmentAlign').html(newAttachment);
            
            // Adds event
            $('.link_masked_attachment[data-id-post="'+version+'"]').on('click', function()
            {
               var toMask = $(this).attr('data-id-post');
               $('#maskedAttachment'+toMask).toggle(300);
            });
            
            $('#Attachment' + version + ' .postAttachmentAlign').fadeIn(500);
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

// Binds the event

$(document).ready(function()
{
   $('.censorship').on('click', function()
   {
      post = parseInt($(this).attr("data-id-post"));
      version = parseInt($(this).attr("data-version"));
      PostCensoringLib.censorPost(post, version);
   });
});
