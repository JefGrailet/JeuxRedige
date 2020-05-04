/**
* This file defines various functions to handle format code in a convenient fashion. This 
* sometimes requires additionnal dialogs, handled with custom windows.
*/

FormattingLib = {};

/*
* Method called at the end of each subsequent method to refresh the preview, if present.
*/

FormattingLib.refreshPreview = function()
{
   if(typeof PreviewLib !== 'undefined')
   {
      if(PreviewLib.auto)
         PreviewLib.preview();
   }
   else if(typeof QuickPreviewLib !== 'undefined')
   {
      if(QuickPreviewLib.enabled)
         QuickPreviewLib.preview();
   }
   else if(typeof SegmentEditorLib !== 'undefined')
   {
      if(SegmentEditorLib.previewEnabled)
         SegmentEditorLib.preview();
   }
}

/*
* Given two tags (respectively opening and closing tags), the next method inserts them in the 
* textarea, with the currently selected zone being put between both tags. The preview is also 
* updated if the function preview() is available and if autoPreview is set to true.
*
* @param string openingTag  The opening tag
* @param string closingTag  The closing tag
*/

FormattingLib.insertTags = function(openingTag, closingTag)
{
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var selection = content.substring(message[0].selectionStart, message[0].selectionEnd);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + openingTag + selection + closingTag + after);
   
   FormattingLib.refreshPreview();
}

/*
* Given an emoticon shortcut, the next function inserts it in the textarea, with the currently 
* selected zone being replaced by the shortcut. The preview is also updated if the function 
* preview() is available and if autoPreview is set to true.
*
* @param string shortcut  The emoticon shortcut
*/

FormattingLib.insertShortcut = function(shortcut)
{
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + shortcut + after);
   
   FormattingLib.refreshPreview();
}


/*
* Deals with the input provided in addHyperlink div (dialog window) and inserts the proper format 
* code into the textarea. The preview is also updated if the function preview() is available and 
* if autoPreview is set to true.
*/

FormattingLib.insertHyperlink = function()
{
   var URL = $('input[type="text"][name="hyperlink"]').val();
   var title = $('input[type="text"][name="hyperlink_title"]').val();
   
   if(URL.length < 10)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   var formatCode = "";
   if(title.length > 0)
      formatCode = '[url=' + URL + ']' + title + '[/url]';
   else
      formatCode = '[url]' + URL + '[/url]';
   
   // Updating textarea
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + formatCode + after);
   
   FormattingLib.refreshPreview();
   DefaultLib.closeDialog();
   
   $('input[type="text"][name="hyperlink"]').val('');
   $('input[type="text"][name="hyperlink_title"]').val('');
}

/*
* Deals with the input provided in integrateImg div (which is also a dialog window) and inserts 
* the format code required to integrate the selected image into the textarea. The preview is also 
* updated if the function preview() is available and if autoPreview is set to true.
*/

FormattingLib.insertImg = function()
{
   var URLImg = $('input[type="text"][name="url_img"]').val();
   var ratioImg = $('select[name="format_img"]').val();
   var floatingImg = $('select[name="floating_img"]').val();
   var commentImg = $('input[type="text"][name="comment_img"]').val();
   
   if(URLImg.length < 10)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   // Checks extension for .webm/.mp4 (because the tag is "!clip" rather than "!img" for full size
   var ext = URLImg.substr((URLImg.lastIndexOf('.') + 1)).toLowerCase();
   
   var formatCode = "";
   var withComment = false;
   if(ratioImg === "mini")
   {
      formatCode = "!mini[";
      if(commentImg !== "")
         withComment = true;
   }
   else
   {
      if(ext == "webm" || ext == "mp4")
         formatCode = "!clip[";
      else
         formatCode = "!img[";
   }
   formatCode += URLImg;
   if(ratioImg != 'mini' && ratioImg != '1.0')
      formatCode += ";" + ratioImg;
   if(floatingImg === 'left' || floatingImg === 'right')
      formatCode += ";" + floatingImg;
   formatCode += "]";
   if(withComment)
      formatCode += '[' + commentImg + ']';
   
   // Updating textarea
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + formatCode + after);
   
   FormattingLib.refreshPreview();
   DefaultLib.closeDialog();
   
   $('input[type="text"][name="url_img"]').val('');
   $('input[type="text"][name="comment_img"]').val('');
}

/*
* Deals with the input provided in integrateVideo div (which is also a dialog window) and inserts 
* the format code required to integrate the given video into the textarea. Just like before, 
* preview is refreshed if necessary.
*/

FormattingLib.insertVideo = function()
{
   var URL = $('input[type="text"][name="url_video"]').val();
   
   if(URL.length < 10)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   var formatCode = '!video[' + URL + ']';
   
   // Updating textarea
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + formatCode + after);
   
   FormattingLib.refreshPreview();
   DefaultLib.closeDialog();
   
   $('input[type="text"][name="url_video"]').val('');
}

/*
* Deals with the input provided in putEmphasis div (which is also a dialog window) and inserts 
* the format code required to integrate the emphasis into the textarea. Just like before, 
* preview is refreshed if necessary.
*
* This feature is exclusive to articles formatting.
*/

FormattingLib.insertEmphasis = function()
{
   var background = $('input[type="text"][name="emphasis_background"]').val();
   var quote = $('input[type="text"][name="emphasis_quote"]').val();
   
   if(background.length < 10 || quote.length == 0)
   {
      DefaultLib.closeDialog();
      return;
   }
   
   var formatCode = '!emphase[' + background + '][' + quote + ']';
   
   // Updating textarea
   var message = $('textarea[name=message]');
   var content = message.val();
   var before = content.substring(0, message[0].selectionStart);
   var after = content.substring(message[0].selectionEnd, content.length);
   message.val(before + formatCode + after);
   
   FormattingLib.refreshPreview();
   DefaultLib.closeDialog();
   
   $('input[type="text"][name="emphasis_background"]').val('');
   $('input[type="text"][name="emphasis_quote"]').val('');
}


/*
* Updates the display in a "pick color" dialog, given the component (red, green or blue) being 
* edited.
*/

FormattingLib.updateColor = function(comp)
{
   var redComp = $('input[type="range"][name="red_comp"]').val();
   var greenComp = $('input[type="range"][name="green_comp"]').val();
   var blueComp = $('input[type="range"][name="blue_comp"]').val();
   
   if(comp === 'red')
      $('.colorShow[data-color-comp="red"]').css('background-color', 'rgb(' + redComp + ',0,0)');
   else if(comp === 'green')
      $('.colorShow[data-color-comp="green"]').css('background-color', 'rgb(0,' + greenComp + ',0)');
   else if(comp === 'blue')
      $('.colorShow[data-color-comp="blue"]').css('background-color', 'rgb(0,0,' + blueComp + ')');
   
   $('.colorShow[data-color-comp="mix"]').css('background-color', 'rgb(' + redComp + ',' + greenComp + ',' + blueComp + ')');
}

/*
* Inserted the picked color into the textarea, using insertTags() for convenience.
*/

FormattingLib.pickColor = function()
{
   var redComp = $('input[type="range"][name="red_comp"]').val();
   var greenComp = $('input[type="range"][name="green_comp"]').val();
   var blueComp = $('input[type="range"][name="blue_comp"]').val();
   
   var openingTag = "[rgb=" + redComp + ',' + greenComp + ',' + blueComp + ']';
   var closingTag = "[/rgb]";
   
   FormattingLib.insertTags(openingTag, closingTag);
   DefaultLib.closeDialog();
   
   // Resets window
   $('.colorShow[data-color-comp="red"]').css('background-color', 'rgb(255,0,0)');
   $('input[type="range"][name="red_comp"]').val('255');
   $('.colorShow[data-color-comp="green"]').css('background-color', 'rgb(0,255,0)');
   $('input[type="range"][name="green_comp"]').val('255');
   $('.colorShow[data-color-comp="blue"]').css('background-color', 'rgb(0,0,255)');
   $('input[type="range"][name="blue_comp"]').val('255');
   $('.colorShow[data-color-comp="mix"]').css('background-color', 'rgb(255,255,255)');
}

/*
* Sends an AJAX request to retrieve user's emoticons and shortcuts. The events to put them in the 
* text are binded as well.
*/

FormattingLib.loadShortcuts = function()
{
   if(DefaultLib.isHandlingAJAX())
      return;

   $.ajax({
   type: 'GET',
   url: DefaultLib.httpPath + 'ajax/GetMyShortcuts.php', 
   timeout: 5000,
   success: function(data)
   {
      DefaultLib.doneWithAJAX();
      if(data.length !== 0)
      {
         if(data === 'DB error')
         {
            alert('Une erreur est survenue avec la base de données. Réessayez plus tard.');
         }
         else if(data !== 'Empty library')
         {
            $('#emoticonsDisplay').html(data);
            
            $("#emoticonsDisplay .emoticon").on('click', function ()
            {
               var shortcut = $(this).attr('data-shortcut');
               FormattingLib.insertShortcut(shortcut);
            });
            
            $('#emoticonsDisplay').fadeIn(500);
         }
         else
         {
            alert('Vous n\'avez actuellement aucune émoticône dans votre librairie.');
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

// Binds events

$(document).ready(function()
{
   // Main events (visible buttons)
   $("#buttonBold").on('click', function () { FormattingLib.insertTags('[b]', '[/b]'); });
   $("#buttonItalic").on('click', function () { FormattingLib.insertTags('[i]', '[/i]'); });
   $("#buttonUnderlined").on('click', function () { FormattingLib.insertTags('[u]', '[/u]'); });
   $("#buttonStrikethrough").on('click', function () { FormattingLib.insertTags('[s]', '[/s]'); });
   $("#buttonTitle").on('click', function () { FormattingLib.insertTags('[t]', '[/t]'); });
   $("#buttonList").on('click', function () { FormattingLib.insertTags('* ', ''); });
   $("#buttonHyperlink").on('click', function () { DefaultLib.openDialog('#addHyperlink'); });
   $("#buttonColors").on('click', function () { DefaultLib.openDialog('#pickColor'); });
   $("#buttonTextCenter").on('click', function () { FormattingLib.insertTags('[centre]', '[/centre]'); });
   $("#buttonTextRight").on('click', function () { FormattingLib.insertTags('[droite]', '[/droite]'); });
   $("#buttonQuote").on('click', function () { FormattingLib.insertTags('[cite]', '[/cite]'); });
   $("#buttonHiddenText").on('click', function () { FormattingLib.insertTags('[cacher]', '[/cacher]'); });
   $("#buttonSpoiler").on('click', function () { FormattingLib.insertTags('[spoiler]', '[/spoiler]'); });
   $("#buttonImage").on('click', function () { DefaultLib.openDialog('#integrateImg'); });
   $("#buttonVideo").on('click', function () { DefaultLib.openDialog('#integrateVideo'); });
   $("#buttonEmoticons").on('click', function ()
   {
      if($('#emoticonsDisplay').html() === '')
      {
         FormattingLib.loadShortcuts();
      }
      else
      {
         if($('#emoticonsDisplay').is(':visible'))
            $('#emoticonsDisplay').fadeOut(500);
         else
            $('#emoticonsDisplay').fadeIn(500);
      }
   });
   
   // Text emphasis (for articles only)
   if($("#buttonEmphasisBis").length)
   {
      $("#buttonEmphasisBis").on('click', function()
      {
         var openingTag = '!bloc[Titre du bloc][';
         FormattingLib.insertTags(openingTag, ']');
      });
   }
   
   // Extended formatting (for articles only)
   if($("#buttonSummary").length)
   {
      $("#buttonSummary").on('click', function()
      {
         var message = $('textarea[name=message]');
         var content = message.val();
         var before = content.substring(0, message[0].selectionStart);
         var after = content.substring(message[0].selectionEnd, content.length);
         var summaryBaseCode = "!resume[Point fort 1;\nPoint fort 2;\nSéparez avec des point-virgules]";
         summaryBaseCode += "[\nPoint faible 1;\nPoint faible 2;\nSéparez avec des point-virgules]"
         message.val(before + summaryBaseCode + after);
         
         if(typeof SegmentEditorLib !== 'undefined')
         {
            if(SegmentEditorLib.previewEnabled)
               SegmentEditorLib.preview();
         }
      });
   }
   
   // Events in dialogs
   $("#addHyperlink .triggerDialog").on('click', FormattingLib.insertHyperlink);
   $("#addHyperlink .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   $("#pickColor .triggerDialog").on('click', FormattingLib.pickColor);
   $("#pickColor .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   $("#integrateImg .triggerDialog").on('click', FormattingLib.insertImg);
   $("#integrateImg .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   $("#integrateVideo .triggerDialog").on('click', FormattingLib.insertVideo);
   $("#integrateVideo .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   
   if($("#buttonEmphasis").length && $("#putEmphasis").length)
   {
      $("#buttonEmphasis").on('click', function () { DefaultLib.openDialog('#putEmphasis'); });
      $("#putEmphasis .triggerDialog").on('click', FormattingLib.insertEmphasis);
      $("#putEmphasis .closeDialog").on('click', function () { DefaultLib.closeDialog(); });
   }
   
   // Events in color pick dialog
   $('#pickColor input[type="range"][name="red_comp"]').on('change', function () { FormattingLib.updateColor('red'); });
   $('#pickColor input[type="range"][name="green_comp"]').on('change', function () { FormattingLib.updateColor('green'); });
   $('#pickColor input[type="range"][name="blue_comp"]').on('change', function () { FormattingLib.updateColor('blue'); });
});
