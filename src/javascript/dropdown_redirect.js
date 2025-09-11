const listDropdownsRedirect = document.querySelectorAll("[data-dropdown-redirect]");

listDropdownsRedirect.forEach((item) => {
   item.addEventListener("change", (e) => {
      window.location = e.currentTarget.value;
   });
});
