const listModals = document.querySelectorAll('dialog[data-modal]');

const setSegment = (e) => {
   document.querySelector("[data-modal=delete-media]").value = e.target.dataset.segmentId;
}


listModals.forEach((item) => {
   item.addEventListener("toggle", (e) => {
      console.log(e )
      console.log(e.source )
   })
})
