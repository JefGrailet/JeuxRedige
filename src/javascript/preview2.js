const articlePreviewContainer = document.getElementById(
   "articleContentPreviewWrapper"
);
const previewZone = document.getElementById("previewZone");
const content = document.getElementById("page-content");

const preview = async () => {
   if (content.value.trim().length === 0 || !content.checkVisibility()) {
      return;
   }

   const payload = new FormData();
   payload.append("what", "article");
   payload.append("message", content.value);

   const req = await fetch(DefaultLib.httpPath + "ajax/Preview.php", {
      method: "POST",
      body: payload,
   });

   previewZone.innerHTML = await req.text();
};

document
   .getElementById("auto_preview")
   ?.addEventListener("change", async (e) => {
      articlePreviewContainer?.classList.toggle(
         "preview",
         e.currentTarget.checked
      );

      preview();
   });

document.addEventListener("inserttags", () => {
   preview();
});

content?.addEventListener("input", () => {
   preview();
});

const observer = new MutationObserver((mutationsList) => {
   for (const mutation of mutationsList) {
      console.log(mutation.type)
      if (mutation.type == "attributes") {
         previewZone.style.height = mutation.target.style.height;
      }
   }
});
observer.observe(content, { attributes: true });
