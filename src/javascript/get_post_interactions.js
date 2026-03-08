const listInteractionBtns = document.querySelectorAll("[data-get-post-interactions-btn]");

const getPostInteractions = async (e) => {
   const idPost = e.currentTarget.dataset.idPost;
   const popover = document.querySelector(`#postInfo-${idPost}`);

   if (popover.dataset.hasBeenLoaded === "true") {
      popover.showPopover()
      return;
   }

   popover.dataset.hasBeenLoaded = "true";

   const req = await fetch(`${DefaultLib.httpPath}ajax/GetPostInteractions.php?id_post=${idPost}`);
   const res = await req.text();

   if (res === 'no interaction') {
      popover.querySelector("[data-content]").innerHTML = "<p>Aucun utilisateur n\'a encore interagi avec ce message.</p>"
   } else {
      popover.querySelector("[data-content]").innerHTML = res;
   }

   popover.showPopover()
}

listInteractionBtns.forEach((item) => {
   item.addEventListener("click", getPostInteractions)
})
