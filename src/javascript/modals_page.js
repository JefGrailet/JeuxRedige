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
      const mediaData = JSON.parse(e.source.dataset.mediaData);

      e.target.querySelector("img").src = mediaData.mini.src;
      e.target.querySelector("[name=url_img]").value = mediaData.full.srcRelative;
      e.target.querySelector(".title span").textContent = pageData.title || "Sommaire";
   } else {
      // const imgSize = e.target.querySelector("[name=format_img]").value;
      // const imgPosition = e.target.querySelector("[name=floating_img]").value;
      // const imgPath = e.target.querySelector("[name=url_img]").value;
      // const imgNote = e.target.querySelector("[name=comment_img]").value;
   }
});

const previewMediaModal = document.getElementById("preview-media");

previewMediaModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const mediaData = JSON.parse(e.source.dataset.mediaData);

      e.target.querySelector(".content img").src = mediaData.full.src;
      e.target.querySelector("[data-date]").textContent = mediaData.uploadDate;
      e.target.querySelector("a").href = mediaData.full.src;
   }
});

const deleteMediaModal = document.getElementById("delete-media");

deleteMediaModal?.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const mediaData = JSON.parse(e.source.dataset.mediaData);

      e.target.querySelector(".content img").src = mediaData.mini.src;
      document.getElementById("delete-media-btn").dataset.mediaData = e.source.dataset.mediaData;
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
