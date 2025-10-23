const deletePageModal = document.getElementById("delete-page");

deletePageModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const pageData = JSON.parse(e.source.dataset.pageData);

      e.target.querySelector("[name=id_segment]").value = pageData.id;
      e.target.querySelector(".title span").textContent = pageData.title || "Sommaire";
   }
});

const integrateMediaPageModal = document.getElementById("integrate-media");

integrateMediaPageModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const mediaData = JSON.parse(e.source.closest("[data-media-data]").dataset.mediaData);

      e.target.querySelector("img").src = mediaData.mini.src;
      e.target.querySelector("[name=url_img]").value = mediaData.full.srcRelative;
   }
});

const previewMediaModal = document.getElementById("preview-media");

previewMediaModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const mediaData = JSON.parse(e.source.closest("[data-media-data]").dataset.mediaData);

      const img = e.target.querySelector(".content img");
      img.src = mediaData.full.src;
      img.width = mediaData.full.size.width;
      img.height = mediaData.full.size.height;

      e.target.querySelector("[data-date]").textContent = mediaData.uploadDate;
      e.target.querySelector("a").href = mediaData.full.src;
   }
});

const deleteMediaModal = document.getElementById("delete-media");

deleteMediaModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const mediaDataRaw = e.source.closest("[data-media-data]").dataset.mediaData;
      const mediaData = JSON.parse(mediaDataRaw);

      e.target.querySelector(".content img").src = mediaData.mini.src;
      document.getElementById("delete-media-btn").dataset.mediaData = mediaDataRaw;
   }
});

const deleteMediaBtn = document.getElementById("delete-media-btn");
deleteMediaBtn?.addEventListener("click", async (e) => {
   const mediaData = JSON.parse(e.target.dataset.mediaData);
   const filePath = mediaData.full.src;

   const formData = new FormData();
   formData.append("fileToDelete", filePath);

   const req = await fetch(DefaultLib.httpPath + 'ajax/DeleteUploadedFile.php', {
      method: "POST",
      body: formData,
   })

   const res = await req.text();
   if (res === "ok") {
      document.querySelector(`[data-media-data='${e.target.dataset.mediaData}']`)?.closest("li").remove();
      deleteMediaModal?.close();
   } else {

   }
});
