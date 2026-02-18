const miniatureLightbox = document.getElementById("miniature-lightbox");

miniatureLightbox.addEventListener("toggle", (evt) => {
   const comment = evt.currentTarget.querySelector("[data-comment]");
   const videoSource = evt.currentTarget.querySelector("video");
   const img = evt.currentTarget.querySelector("img");

   if (evt.newState === "open") {
      const mediaData = JSON.parse(evt.source.dataset.mediaData);
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
            videoSource.querySelector("source").src = mediaData.full.src;
            videoSource.querySelector("source").type = mediaData.mimeType;
            videoSource.load();

            mediaLink.href = mediaData.full.src;

            comment.textContent = mediaData.comment;
         }
            break;

         default:
            break;
      }
   } else {
      comment.textContent = "";
      img.src = "";
      img.alt = "";
      if (videoSource) {
         videoSource.pause();
         videoSource.currentTime = 0;
      }
   }
});


const btnShare = document.querySelector('[data-share]');

if (navigator.share) {
   btnShare.style.display = 'initial';
   btnShare.addEventListener('click', async (e) => {
      try {
         const shareData = JSON.parse(e.currentTarget.dataset?.share || '{}')
         await navigator.share({
            title: shareData.title,
            text: shareData.text,
            url: window.location.href
         });
         console.log('Contenu partagé avec succès !');
      } catch (err) {
         // L'utilisateur a annulé ou le partage a échoué
         console.log(`Erreur ou annulation : ${err}`);
      }
   });
} 