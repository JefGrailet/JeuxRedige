const listUploadFileInput = document.querySelectorAll("[data-upload-input]");

const previewUpload = (e) => {
   const container = e.currentTarget.closest("table");
   const img = container.querySelector("img");

   const file = e.currentTarget.files[0];

   img.src = URL.createObjectURL(file);
};

listUploadFileInput.forEach((item) => {
   item.addEventListener("change", previewUpload);
});
