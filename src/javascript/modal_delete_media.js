const deleteImageModal = document.getElementById("delete-media");

deleteImageModal.addEventListener("toggle", (evt) => {
   const img = evt.currentTarget.querySelector("img");
   if (evt.newState === "open") {
      const deleteBtnPayload = JSON.parse(evt.source.dataset.media);
      evt.currentTarget.dataset.media = evt.source.dataset.media;

      img.src = deleteBtnPayload.new;
   } else {
      if (evt.source === null) {
         const payload = JSON.parse(evt.currentTarget.dataset.media);

         const inputFile = document.querySelector(
            `input[name="${payload.linkedMedia.inputName}"]`
         );
         inputFile.value = null;
         img.src = "";
         const linkedMedia = document.getElementById(payload.linkedMedia.id);
         linkedMedia.src = payload.original;

         const deleteBtn = document.getElementById(payload.deleteButtonId);
         deleteBtn.inert = true;
      }
   }
});
