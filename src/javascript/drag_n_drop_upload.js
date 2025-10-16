const listUploadDropzone = document.querySelectorAll("[data-upload-dropzone]");
const listDragNDropError = document.querySelectorAll("[data-incorrect-upload]");

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

// listDragNDropError.forEach((item) => {
//     item.classList.add("hidden");
//     item.querySelector("button").addEventListener("click", () => {
//         item.classList.add("hidden");
//     });
// });

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
   const previewContainer = document.querySelector(`[data-preview-dropzone="${element.dataset.uploadInputDropzone}"]`)

   Array.from(element.files).forEach((file) => {
      const tpl = previewUploadTemplateRaw.content.cloneNode(true);
      console.log(tpl)

      const img = tpl.querySelector("img");
      img.src = URL.createObjectURL(file);

      previewContainer.append(tpl)
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
