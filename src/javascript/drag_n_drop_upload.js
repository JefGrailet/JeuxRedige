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
   const listErrorsCounter = errorsContainer.querySelector("[data-nb-file-errors]");

   listErrorsContainer.innerHTML = "";
   errorsContainer.hidden = true;

   const getFileErrors = (file) => {
      const fileSizeMb = file.size / 1024 / 1024;
      const isImage = file.type.split("/")[0] === "image";

      return {
         type: !listMimeTypeAuthorized.includes(file.type),
         size: isImage ? fileSizeMb > 1 : fileSizeMb > 5,
      }
   }

   const errors = {
      "type": "L'extension de fichier est incorrecte",
      "size": "Le fichier est trop lourd"
   }

   Array.from(element.files).forEach(async (file) => {
      const listFileErrors = getFileErrors(file);

      if (Object.values(listFileErrors).every((item) => item === false)) {
         const data = new FormData()
         data.append('newFile', file)

         const req = await fetch(DefaultLib.httpPath + "/ajax/UploadFileJSON.php", {
               method: "POST",
               body: data,
            })
         const res = await req.json();

         if ("success" in res) {
            const { success: mediaData } = res;
            const tpl = previewUploadTemplateRaw.content.cloneNode(true);

            tpl.querySelector("li").dataset.media = mediaData.mediaType;
            tpl.querySelector("li").dataset.mediaData = JSON.stringify(mediaData);


            switch (mediaData.mediaType) {
               case "image": {
                  const img = tpl.querySelector("img");
                  img.src = mediaData.mini.src;
                  img.height = mediaData.mini.height;
                  img.width = mediaData.mini.width;

                  tpl.querySelector("video").remove();
               }
               break;
               case "video": {
                  const videoSource = tpl.querySelector("source");
                  videoSource.src = mediaData.full.src;
                  videoSource.type = mediaData.mimeType;

                  tpl.querySelector("img").remove();
               }
                  break;

               default:
                  break;
            }

            previewContainer.append(tpl)
         }
      } else {
         errorsContainer.hidden = false;

         const fileErrorsKeysTriggered = Object.fromEntries(Object.entries(listFileErrors).filter(([_, value]) => value === true))
         const listFileErrorsMessage = Object.keys(fileErrorsKeysTriggered).map((item) => errors[item])

         const li = document.createElement("li")
         li.textContent = `${file.name} : ${listFileErrorsMessage.join(", ")}`;
         listErrorsContainer.append(li)
         listErrorsCounter.textContent = `(${listErrorsContainer.childElementCount})`
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
