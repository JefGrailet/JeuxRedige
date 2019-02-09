/**
* This file defines various functions to handle tropes (pool display) in a convenient fashion, 
* using additionnal dialogs when relevant.
*/

var TropesPoolLib = {};
TropesPoolLib.selected = ""; // Maintains the name of a selected trope when trying to delete it

/*
* Sends an AJAX request to delete a selected trope.
*/

TropesPoolLib.deleteTrope = function()
{
   var tropeName = TropesPoolLib.selected;
   if(!tropeName)
   {
      alert('Erreur: aucun code n\'est sélectionné!');
      return;
   }

   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'POST',
   url: DefaultLib.httpPath + 'ajax/DeleteTrope.php', 
   data: 'trope='+encodeURI(tropeName),
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === 'OK')
         {
            var eBlock = $('.mediaThumbnail[data-trope="' + tropeName + '"]');
            eBlock.animate({opacity: 0.0}, 600, function()
            {
               eBlock.remove();
               
               if(!($('.mediaThumbnail').length))
               {
                  $('#thumbnailsPool').html('<p class="poolError">Il n\'y a actuellement aucun '
                  + 'code enregistré dans la base de données.<br/><br/><br/></p>');
               }
            });
         }
         else
         {
            alert('Une erreur est survenue avec la base de données. Réessayez plus tard.');
         }
      }
      else
      {
         alert('Une erreur inconnue est survenue. Veuillez contacter un administrateur.');
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

// Binds events
$(document).ready(function()
{
   if($('.buttonDelete')[0])
   {
      $('.buttonDelete').on('click', function()
      {
         TropesPoolLib.selected = $(this).attr('data-trope');
         DefaultLib.openDialog('#deleteTrope');
      });
   }
   
   $("#deleteTrope .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   $('#deleteTrope .triggerDialog').on('click', TropesPoolLib.deleteTrope);
});
