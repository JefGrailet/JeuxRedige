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

const showTooltip = (e) => {
   const popoverTargetEl = document.getElementById("tooltip-editor-tool");
   popoverTargetEl.showPopover({ source: e.target });
}

const hideTooltip = (e) => {
   const popoverTargetEl = document.getElementById("tooltip-editor-tool");
   popoverTargetEl.hidePopover();
}

document.querySelectorAll("button[data-editor-tool]").forEach((item) => {
   item.addEventListener("click", editorAction);

   // item.addEventListener("mouseover", showTooltip);
   // item.addEventListener("focus", showTooltip);

   // item.addEventListener("mouseout", hideTooltip);
   // item.addEventListener("blur", hideTooltip);
});

const hexToRGB = (color) => {
  const r = parseInt(color.substr(1,2), 16)
  const g = parseInt(color.substr(3,2), 16)
  const b = parseInt(color.substr(5,2), 16)

  return {r, g, b}
}

const onSubmitFormSuccessfully = (e) => {
   const form = e.currentTarget.querySelector("form");

   if (form?.dataset.isValid !== "true" && "formSchemaValidation" in form.dataset) {
      return;
   }

   const formData = new FormData(form);
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
         const URLImage = formDataJSON.url_img;
         const formatImg = formDataJSON.format_img;
         const floatingImg = formDataJSON.floating_img;
         const altText = formDataJSON.alt_img;

         let tagName = formatImg === "mini" ? "mini" : "img";
         let imgNote = altText.trim().length > 0 ? `[${altText}]` : "";
         let imgSize = formatImg === "mini" ? null : formatImg;
         let imgPosition = floatingImg === "normal" ? null : floatingImg;

         let imgOptions = [imgSize, imgPosition].filter(Boolean).join(";");
         if (imgOptions.length > 0) {
            imgOptions = `;${imgOptions}`;
         }

         insertTags(`!${tagName}[${URLImage}${imgOptions}]`, imgNote);
      }
      break;

      case "integrate-external-img": {
         const URLImage = e.currentTarget.querySelector("input[name='url_img']").value;
         const altTextImage = e.currentTarget.querySelector("input[name='alt_img']").value;

         insertTags(`!img[${URLImage}]`, altTextImage ? `[${altTextImage}]` : "");
      }
      break;

      case "integrate-yt-video": {
         const URLYoutube = formDataJSON.yt_video;
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

   form.removeAttribute("data-is-dirty");
   form.removeAttribute("data-is-valid");
   form.reset();
}

document.querySelectorAll("dialog").forEach((item) => {
   item.addEventListener("close", onSubmitFormSuccessfully);
});
