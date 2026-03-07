(() => {
   if ("command" in document.createElement("button")) {
      return;
   }
   const listModalTriggers = document.querySelectorAll('[data-trigger-modal]');
   const listModalCloseButtons = document.querySelectorAll('[data-close-modal]');

   const openModal = (e) => {
      const modalName = e.currentTarget.dataset.triggerModal;
      const modalToOpen = document.querySelector(`dialog[data-modal="${modalName}"]`);
      if (modalToOpen) {
         modalToOpen.showModal();
      }
   }

   listModalTriggers.forEach((item) => {
      item.addEventListener("click", openModal);
   });

   const closeModal = (e) => {
      const parentModal = e.currentTarget.closest("dialog");
      if(parentModal) {
         parentModal.close();
      }
   }

   listModalCloseButtons.forEach((item) => {
      item.addEventListener("click", closeModal);
   });
})();

const formLoginModal = document.getElementById("form-login");

formLoginModal.addEventListener("toggle", (evt) => {
   if (evt.newState === "open") {
      document.cookie = `last_page=${location.href}; path=/;`;
   }
})
