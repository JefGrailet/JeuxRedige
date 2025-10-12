const listModalTriggers = document.querySelectorAll('[data-trigger-modal="delete-segment"]');
const listModals = document.querySelectorAll('dialog[data-modal]');

const setSegment = (e) => {
   document.querySelector("[data-modal=delete-segment] [name=id_segment]").value = e.target.dataset.segmentId;
}

listModalTriggers.forEach((item) => {
   item.addEventListener("click", setSegment);
});
