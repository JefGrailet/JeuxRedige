const deletePageModal = document.getElementById("delete-page");

deletePageModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const pageData = JSON.parse(e.source.dataset.pageData);

      e.target.querySelector("[name=id_segment]").value = pageData.id;
      e.target.querySelector(".title span").textContent =
         pageData.title || "Sommaire";
   }
});

const integrateMediaPageModal = document.getElementById("integrate-media");

integrateMediaPageModal?.addEventListener("toggle", (e) => {
   const videoSource = e.target.querySelector("video");
   if (e.newState === "open") {
      const mediaData = JSON.parse(
         e.source.closest("[data-media-data]").dataset.mediaData
      );

      const img = e.target.querySelector("img");

      e.target.querySelector("input[name='media_type']").value = mediaData.mediaType;
      e.target.querySelector("[name=media_url]").value = mediaData.full.srcRelative;

      const listMediaFormat = e.target.querySelector("select[name='media_format']");
      const listValueNotAllowedVideo = ["0.5", "1.5", "2.0", "3.0", "5.0"];

      Array.from(listMediaFormat.options).forEach((item) => {
         if (listValueNotAllowedVideo.includes(item.value) && mediaData.mediaType === "video") {
            item.hidden = true;
         } else {
            item.hidden = false;
         }
      })

      const imgAltText = document.getElementById("altText").parentNode;

      switch (mediaData.mediaType) {
         case "image":
            {
               img.src = mediaData.mini.src;

               videoSource.style.display = "none";
               img.style.removeProperty("display");
            }
            break;
         case "video":
            {
               videoSource.src = mediaData.full.src;
               videoSource.type = mediaData.mimeType;
               videoSource.style.removeProperty("display");

               img.style.display = "none";
            }
            break;

         default:
            break;
      }
   } else {
      if (videoSource) {
         videoSource.pause();
         videoSource.currentTime = 0;
      }
   }
});

const previewMediaModal = document.getElementById("preview-media");

previewMediaModal?.addEventListener("toggle", (e) => {
   const videoSource = e.target.querySelector(".content video");

   if (e.newState === "open") {
      const mediaData = JSON.parse(
         e.source.closest("[data-media-data]").dataset.mediaData
      );

      const img = e.target.querySelector(".content img");

      switch (mediaData.mediaType) {
         case "image":
            {
               img.style.removeProperty("display");
               img.src = mediaData.full.src;
               img.width = mediaData.full.size.width;
               img.height = mediaData.full.size.height;

               videoSource.style.display = "none";
            }
            break;
            case "video":
               {
               videoSource.style.removeProperty("display");
               videoSource.src = mediaData.full.src;
               videoSource.type = mediaData.mimeType;

               img.style.display = "none";
            }
            break;

         default:
            break;
      }

      e.target.querySelector("[data-date]").textContent = mediaData.uploadDate;
      e.target.querySelector("a").href = mediaData.full.src;
   } else {
      if (videoSource) {
         videoSource.pause();
         videoSource.currentTime = 0;
      }
   }
});

const deleteMediaModal = document.getElementById("delete-media");

deleteMediaModal?.addEventListener("toggle", (e) => {
   const img = e.target.querySelector(".content img");
   const videoSource = e.target.querySelector(".content video");

   if (e.newState === "open") {
      const mediaDataRaw =
         e.source.closest("[data-media-data]").dataset.mediaData;
      const mediaData = JSON.parse(mediaDataRaw);

      switch (mediaData.mediaType) {
         case "image":
            {
               img.src = mediaData.mini.src;
               img.style.removeProperty("display");

               videoSource.style.display = "none";
            }
            break;
            case "video":
               {
               videoSource.style.removeProperty("display");
               videoSource.src = mediaData.full.src;

               img.style.display = "none";
            }
            break;

         default:
            break;
      }

      document.getElementById("delete-media-btn").dataset.mediaData =
         mediaDataRaw;
   } else {
      if (videoSource) {
         videoSource.pause();
         videoSource.currentTime = 0;
      }
   }
});

const deleteMediaBtn = document.getElementById("delete-media-btn");
deleteMediaBtn?.addEventListener("click", async (e) => {
   const mediaData = JSON.parse(e.target.dataset.mediaData);
   const filePath = mediaData.full.src;

   const formData = new FormData();
   formData.append("fileToDelete", filePath);

   const req = await fetch(
      DefaultLib.httpPath + "ajax/DeleteUploadedFile.php",
      {
         method: "POST",
         body: formData,
      }
   );

   const res = await req.text();
   if (res === "ok") {
      document.getElementById(mediaData.id)?.remove();
      deleteMediaModal?.close();
   } else {
      alert("Une erreur est survenue");
   }
});
