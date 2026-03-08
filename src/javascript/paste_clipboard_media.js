(async () => {
   const listClipboardUploadButton = document.querySelectorAll(
      "[data-clipboard-upload]"
   );

   const pasteClipboard = async (e) => {
      const element = e.target;
      const name = element.dataset.clipboardUpload;

      try {
         const clipboardContents = await navigator.clipboard.read();

         const img = clipboardContents.filter((item) =>
            item.types.includes("image/png")
         )[0];

         if (img) {
            const blob = await img.getType("image/png");


            const file = new File([blob], "img.png", {
               type: blob.type,
               lastModified: new Date().getTime(),
            });

            const container = new DataTransfer();
            container.items.add(file);

            const inputFile = document.querySelector(
               `[name="${name}"]`
            );

            inputFile.files = container.files;
            inputFile.dispatchEvent(new Event("change"));
         } else {
            alert("Image non trouvée dans le presse-papier");
         }
      } catch (_error) {
         alert("Image non trouvée dans le presse-papier");
      }
   };

   const getClipboardPermission = async (e) => {
      try {
         const permission = await navigator.permissions.query({
            name: "clipboard-read",
         });
         if (permission.state === "denied") {
            throw new Error("Permission d'accès au presse-papier refusée");
         } else {
            pasteClipboard(e);
         }
      } catch (error) {
         alert(error.message);
      }
   };

   try {
      await navigator.permissions.query({
         name: "clipboard-read",
      });
      listClipboardUploadButton.forEach((item) => {
         item.style.display = 'revert';
      });
   } catch (_error) {
      listClipboardUploadButton.forEach((item) => {
         item.remove();
      });
   }

   listClipboardUploadButton.forEach((item) => {
      item.addEventListener("click", getClipboardPermission);
   });
})();
