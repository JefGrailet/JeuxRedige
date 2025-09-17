const select = document.querySelector("[data-select-user-prefs]");
const listPrefsContainer = document.querySelectorAll("[data-prefs-container]");

const toggleOtherOptions = (isEnabled) => {
   listPrefsContainer.forEach((item) => item.inert = isEnabled !== "yes")
};

toggleOtherOptions(select.value);

select.addEventListener("change", (e) => {
   toggleOtherOptions(e.currentTarget.value);
});
