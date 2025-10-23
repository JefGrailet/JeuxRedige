const articleContent = document.querySelector("[data-article-content]");
const articleHeader = document.querySelector("[data-article-header]");

if (window.scrollY === 0) {
   articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;
}

screen.orientation.addEventListener("change", () => {
   if (window.scrollY === 0) {
      articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;
   }
});

const observer = new IntersectionObserver((entries) => {
   if (entries[0].isIntersecting) {
      articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;
   }
});

observer.observe(document.querySelector("#pixel-to-watch"));

const miniaturePopover = document.getElementById("miniature-popover");

miniaturePopover.addEventListener("beforetoggle", (evt) => {
   const comment = evt.currentTarget.querySelector("[data-comment]");
   if (evt.newState === "open") {
      const mediaData = JSON.parse(evt.source.dataset.mediaData);
      const img = evt.currentTarget.querySelector("img");
      const imgLink = evt.currentTarget.querySelector("a");

      img.src = mediaData.full.src;
      img.width = mediaData.full.size.width;
      img.height = mediaData.full.size.height;
      img.alt = mediaData.comment;

      imgLink.href = mediaData.full.src;

      comment.textContent = mediaData.comment;
   } else {
      comment.textContent = "";
   }
});
