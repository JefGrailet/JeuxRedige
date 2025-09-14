const Joi = window.joi;

const formValidation = (e) => {
   e.preventDefault();
   const form = e.currentTarget;
   const schemaName = e.currentTarget.dataset.formSchemaValidation;
   let schema = new Function(document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent)();

   if (schema) {
      const formData = new FormData(form);
      // schema = schema.options({ allowUnknown: true, abortEarly: false });
      const validator = schema.validate(Object.fromEntries(formData), { allowUnknown: true, abortEarly: false, errors : {language: "FR"} });
      if (validator.error) {
         console.log(validator)
         const bannerError = form.querySelector(`[data-form-error=${schemaName}]`);
         if (bannerError) {
            bannerError.style.whiteSpace = "pre";
            bannerError.textContent = validator.error.message.split(".").map((item) => `• ${item.trim()}`).join("\n");
         }
         return;
      }
   }
   form.submit();
}

const listForms = document.querySelectorAll("[data-form-schema-validation]");
listForms.forEach((item) => {
   item.addEventListener("submit", formValidation);
});
