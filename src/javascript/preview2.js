const articlePreviewContainer = document.getElementById(
   "articleContentPreviewWrapper"
);
const previewZone = document.getElementById("previewZone");
const content = document.getElementById("page-content");

let ignoreScrollEvents = false
const syncScroll = (master, slave) => {
   master.addEventListener("scroll", () => {
      const ignore = ignoreScrollEvents;
      ignoreScrollEvents = false;
      if (ignore) return

      ignoreScrollEvents = true;

      const percentageMaster = master.scrollTop / (master.scrollHeight - master.offsetHeight) * 100;
      slave.scrollTop = (percentageMaster / 100) * (slave.scrollHeight - slave.offsetHeight);
   })
}

syncScroll(previewZone, content);
syncScroll(content, previewZone);

const preview = async () => {
   if (!previewZone.checkVisibility()) {
      observer.disconnect()
      return;
   }

   if (content.value.trim().length === 0) {
      previewZone.innerHTML = "";
      return;
   }

   const payload = new FormData();
   payload.append("what", "segment");
   payload.append("message", content.value);

   const req = await fetch(DefaultLib.httpPath + "ajax/Preview.php", {
      method: "POST",
      body: payload,
   });

   previewZone.style.height = `${content.offsetHeight}px`;
   previewZone.innerHTML = await req.text();
   observer.observe(content, { attributes: true });

   const percentageMaster = content.scrollTop / (content.scrollHeight - content.offsetHeight) * 100;
   previewZone.scrollTop = (percentageMaster / 100) * (previewZone.scrollHeight - previewZone.offsetHeight);
};

const autoPreviewSwitch = document.getElementById("auto_preview");
window.addEventListener("pageshow", () => {
  articlePreviewContainer?.classList.toggle(
      "preview",
      autoPreviewSwitch.checked
   );
});

autoPreviewSwitch?.addEventListener("change", async (e) => {
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
      if (mutation.type === "attributes") {
         previewZone.style.height = mutation.target.style.height;
      }
   }
});

