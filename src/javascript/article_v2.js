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

const miniatureLightbox = document.getElementById("miniature-lightbox");

miniatureLightbox.addEventListener("toggle", (evt) => {
   const comment = evt.currentTarget.querySelector("[data-comment]");
   const videoSource = evt.currentTarget.querySelector("video");

   if (evt.newState === "open") {
      const mediaData = JSON.parse(evt.source.dataset.mediaData);
      const img = evt.currentTarget.querySelector("img");
      const mediaLink = evt.currentTarget.querySelector("a");

      switch (mediaData.mediaType) {
         case "image": {
            videoSource.style.display = "none";
            img.style.removeProperty("display");

            img.src = mediaData.full.src;
            img.width = mediaData.full.size.width;
            img.height = mediaData.full.size.height;
            img.alt = mediaData.comment;

            mediaLink.href = mediaData.full.src;

            comment.textContent = mediaData.comment;
         }
         break;

         case "video": {
            img.style.display = "none";
            videoSource.style.removeProperty("display");

            videoSource.src = mediaData.full.src;
            videoSource.type = mediaData.mimeType;
            mediaLink.href = mediaData.full.src;
         }
            break;

         default:
            break;
      }


   } else {
      comment.textContent = "";
      if (videoSource) {
         videoSource.pause();
         videoSource.currentTime = 0;
      }
   }
});
