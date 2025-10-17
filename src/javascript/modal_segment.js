const deletePageModal = document.getElementById("delete-page");

deletePageModal.addEventListener("toggle", (e) => {
   if (e.newState === "open") {
      const pageData = JSON.parse(e.source.dataset.pageData);

      e.target.querySelector("[name=id_segment]").value = pageData.id;
      e.target.querySelector(".title span").textContent = pageData.title || "Sommaire";
   }
});
