const textarea = document.getElementById("page-content");

const insertTags = (openingTag, closingTag = "") => {
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
         insertTags('[s]', '[/s]');
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
         insertTags('[droite]', '[/droite]');
         break;
      case "formatting_spoiler":
         insertTags('[cacher]', '[/cacher]');
         break;
      case "formatting_spoiler_bis":
         insertTags('[spoiler]', '[/spoiler]');
         break;
      case "formatting_summary":
         insertTags('!resume[Point fort 1;\nPoint fort 2;\nSéparez avec des point-virgules]', '[\nPoint faible 1;\nPoint faible 2;\nSéparez avec des point-virgules]');
         break;
      case "formatting_block":
         insertTags('!bloc[Titre du bloc]', '[Contenu\nNote : La couleur d\'arrière-plan s\'adapte en fonction du type d\'article]');
         break;
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
});

const hexToRGB = (color) => {
  const r = parseInt(color.substr(1,2), 16)
  const g = parseInt(color.substr(3,2), 16)
  const b = parseInt(color.substr(5,2), 16)

  return {r, g, b}
}

const submitForm = (e) => {
   const formData = new FormData(e.currentTarget);
   const formDataJSON = Object.fromEntries(formData);

   switch (e.currentTarget.closest("dialog").dataset.modal) {
      case "hyperlink-media":
         if (formDataJSON.hyperlink_title.trim() === "") {
            insertTags(`[url]${formDataJSON.hyperlink}`, '[/url]');
         } else {
            insertTags(`[url=${formDataJSON.hyperlink}]${formDataJSON.hyperlink_title}`, '[/url]');
         }
      break;

      case "select-color":
         const {r, g, b} = hexToRGB(formDataJSON.text_color);
         insertTags(`[rgb=${r},${g},${b}]`, '[/rgb]');
      break;

      case "integrate-media": {
         const URLImage = e.currentTarget.querySelector("input[name='url_img']");
         const ratioImg = e.currentTarget.querySelector("select[name='format_img']")
         const floatingImg = e.currentTarget.querySelector("select[name='floating_img']")
         const commentImg = e.currentTarget.querySelector("select[name='comment_img']")

         let tagName = ratioImg === "mini" ? "mini" : "img";
         let imgNote = commentImg.trim().length > 0 ? `[${commentImg}]` : null;
         let imgSize = ratioImg === "mini" ? "" : ratioImg;

         let imgOptions = []
         // !mini[tagNameeefefefe][efefe]

         insertTags(`!${tagName}[${URLImage}]`, imgNote);
      }
      break;

      case "integrate-external-img": {
         const URLImage = e.currentTarget.querySelector("input[name='url_img']").value;
         const altTextImage = e.currentTarget.querySelector("input[name='alt_img']").value

         insertTags(`!img[${URLImage}]`, altTextImage ? `[${altTextImage}]` : "");
      }
      break;

      case "integrate-yt-video": {
         const URLYoutube = e.currentTarget.querySelector("input[name='yt_video']").value;
         const selectSize = e.currentTarget.querySelector("select[name='ratio_video']");
         let size = selectSize.value;
         const listValidSizes = Array.from(selectSize.options).map((item) => item.value);
         if (!listValidSizes.includes(size) || size === "default") {
            size = "";
         }

         insertTags(`!video[${URLYoutube};${size}]`);
      }
      break;

      default:
         break;
   }
}

document.querySelectorAll("dialog form").forEach((item) => {
   item.addEventListener("submit", submitForm);
});
