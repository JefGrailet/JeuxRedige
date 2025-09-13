const Joi = window.joi;

const formValidation = (e) => {
   e.preventDefault();

   const schemaName = e.currentTarget.dataset.formSchemaValidation;
   let schema = new Function(document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent)();

   if (schema) {
      const formData = new FormData(e.currentTarget);
      schema = schema.options({ allowUnknown: true });
      const validator = schema.validate(Object.fromEntries(formData));
      if (validator.error) {
         const bannerError = e.currentTarget.querySelector(`[data-form-error=${schemaName}]`);
         if (bannerError) {
            bannerError.textContent = validator.error.message
         }
      }
      console.log()
   } else {
      alert("okkk");
   }
}

const listForms = document.querySelectorAll("[data-form-schema-validation]");
listForms.forEach((item) => {
   item.addEventListener("submit", formValidation);
});
