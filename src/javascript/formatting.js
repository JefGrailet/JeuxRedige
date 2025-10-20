const textarea = document.getElementById("page-content");

const insertTags = (openingTag, closingTag) => {
   const content = textarea.value;

   const before = content.substring(0, textarea.selectionStart);
   const selection = content.substring(textarea.selectionStart, textarea.selectionEnd);
   const after = content.substring(textarea.selectionEnd, content.length);
   textarea.value = before + openingTag + selection + closingTag + after;

   const evt = new Event("inserttags");
   document.dispatchEvent(evt);
}

const editorAction = (e) => {
   const tool = e.currentTarget.dataset.editorTool;

   switch (tool) {
      case "bold":
         insertTags('[b]', '[/b]');
         break;
      case "italic":
         insertTags('[i]', '[/i]');
         break;
      case "underlined":
         insertTags('[u]', '[/u]');
         break;
      case "strikethrough":
         insertTags('[i]', '[/i]');
         break;
      case "title":
         insertTags('[t]', '[/t]');
         break;
      case "formatting_list":
         insertTags('* ', '\n');
         break;
      case "formatting_center-aligned":
         insertTags('[centre]', '[/centre]');
         break;
      case "formatting_right-aligned":
         insertTags('[/droite]', '[/droite]');
         break;
      case "formatting_spoiler":
         insertTags('[cacher]', '[/cacher]');
         break;
      case "formatting_spoiler_bis":
         insertTags('[spoiler]', '[/spoiler]');
         break;
      // case "formatting_picture":
      //    insertTags('* ', '\n');
      //    break;
      // case "formatting_video":
      //    insertTags('* ', '\n');
      //    break;
      // case "formatting_smilies":
      //    insertTags('* ', '\n');
      //    break;
      default:
         break;
   }
}

document.querySelectorAll("button[data-editor-tool]").forEach((item) => {
   item.addEventListener("click", editorAction);
})
