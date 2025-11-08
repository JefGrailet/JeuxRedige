const listUploadDropzone = document.querySelectorAll("[data-upload-dropzone]");

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


const generateUploadItem = (id) => {
   const template = document.getElementById(id);
   const firstChild = template.querySelector(":scope > *");

   tpl.querySelector("li").dataset.mediaType = mediaData.mediaType;
   tpl.querySelector("li").dataset.mediaData = JSON.stringify(mediaData);
   tpl.querySelector("li").id = id;
}

const generatePreviewsUploads = (e) => {
   const element = e.target;
   const {
      name,
      limitSize: { image: imageLimitSize, video: videoLimitSize } = {},
      request: { url: urlRequest, name: inputName } = {}
   } = JSON.parse(element.dataset.configDragNDrop);

   const listMimeTypeAuthorized = element.getAttribute("accept").split(",").map((item) => item.trim());

   const errorsContainer = document.querySelector(`[data-errors-dropzone="${name}"]`);
   const listErrorsContainer = errorsContainer.querySelector("ul");
   const listErrorsCounter = errorsContainer.querySelector("[data-nb-file-errors]");

   listErrorsContainer.innerHTML = "";
   errorsContainer.hidden = true;

   const getFileErrors = (file) => {
      const fileSizeMb = file.size / 1024 / 1024;
      const isImage = file.type.split("/")[0] === "image";

      return {
         type: !listMimeTypeAuthorized.includes(file.type),
         ...(imageLimitSize || videoLimitSize ? {
            size: isImage ? fileSizeMb > Number(imageLimitSize) : fileSizeMb > Number(videoLimitSize),
         } : {})
      }
   }

   const errors = {
      "type": "L'extension de fichier est incorrecte",
      "size": "Le fichier est trop lourd"
   }

   Array.from(element.files).forEach(async (file) => {
      const listFileErrors = getFileErrors(file);
      if (Object.values(listFileErrors).every((item) => item === false)) {
         if (urlRequest && inputName) {
            const data = new FormData()
            data.append(inputName, file)

            const req = await fetch(DefaultLib.httpPath + urlRequest, {
                  method: "POST",
                  body: data,
               })
            const res = await req.json();

            if ("success" in res) {
               new Function(
                  "res",
                  document.querySelector(`[data-upload-callback-success=${name}]`)?.textContent
               )(res)
            } else {
               errorsContainer.hidden = false;
               const li = document.createElement("li");
               li.textContent = res.error;
               listErrorsContainer.append(li)
               listErrorsCounter.textContent = "(1)";
            }
         } else {
            new Function(
               "res",
               document.querySelector(`[data-upload-callback-success=${name}]`)?.textContent
            )(file)
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
