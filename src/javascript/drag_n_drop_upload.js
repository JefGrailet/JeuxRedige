const listUploadDropzone = document.querySelectorAll("[data-upload-dropzone]");
const previewUploadTemplateRaw = document.querySelector("template[data-template-id=\"page-media-item\"]");

const toggleDragAndDropIndicator = (element, show = true) => {
    if (show) {
        element.classList.remove("paused");
    } else {
        element.classList.add("paused");
    }
};

listUploadDropzone.forEach((item) => {
    toggleDragAndDropIndicator(item, false);
});

listUploadDropzone.forEach((item) => {
    item.addEventListener("dragover", (e) => {
        e.preventDefault();
        toggleDragAndDropIndicator(item, true);
    });
});

["dragend", "dragleave"].forEach((event) => {
    listUploadDropzone.forEach((item) => {
        item.addEventListener(event, (e) => {
            e.preventDefault();
            toggleDragAndDropIndicator(item, false);
        });
    });
});

const generatePreviewsUploads = (e) => {
   const element = e.target;
   const previewContainer = document.querySelector(`[data-preview-dropzone="${element.dataset.uploadInputDropzone}"]`);

   const listMimeTypeAuthorized = element.getAttribute("accept").split(",").map((item) => item.trim());

   const errorsContainer = document.querySelector(`[data-errors-dropzone="${element.dataset.uploadInputDropzone}"]`);
   const listErrorsContainer = errorsContainer.querySelector("ul");
   listErrorsContainer.innerHTML = "";
   errorsContainer.hidden = true;

   const getFileErrors = (file) => {
      const fileSizeMb = file.size / 1024 / 1024;

      return {
         type: !listMimeTypeAuthorized.includes(file.type),
         size: fileSizeMb > 5,
      }
   }

   const errors = {
      "type": "L'extension de fichier est incorrecte",
      "size": "Le fichier est trop lourd"
   }

   Array.from(element.files).forEach((file) => {
      const listFileErrors = getFileErrors(file);

      if (Object.values(listFileErrors).every((item) => item === false)) {
         const tpl = previewUploadTemplateRaw.content.cloneNode(true);

         const img = tpl.querySelector("img");
         img.src = URL.createObjectURL(file);

         previewContainer.append(tpl)
      } else {
         errorsContainer.hidden = false;

         const fileErrorsKeysTriggered = Object.fromEntries(Object.entries(listFileErrors).filter(([_, value]) => value === true))
         const listFileErrorsMessage = Object.keys(fileErrorsKeysTriggered).map((item) => errors[item])

         const li = document.createElement("li")
         li.textContent = `${file.name} : ${listFileErrorsMessage.join(", ")}`;
         listErrorsContainer.append(li)
      }
   })
}

const dropImageObserver = new MutationObserver((mutationList) => {
    mutationList.forEach((mutation) => {
        switch (mutation.type) {
            case "attributes":
               generatePreviewsUploads(mutation);
               break;
            default:
               break;
        }
    });
});

listUploadDropzone.forEach((item) => {
   const input = item.querySelector("input[type=file]");
   input.addEventListener("change", generatePreviewsUploads);
   dropImageObserver.observe(input, {
        attributes: true,
        attributeOldValue: true,
        childList: false,
    });

    item.addEventListener("drop", (e) => {
        e.preventDefault();
        toggleDragAndDropIndicator(item, false);

        if (e.dataTransfer.items) {
            input.setAttribute("files", e.dataTransfer.files);
            input.files = e.dataTransfer.files;
        }
    });
});
