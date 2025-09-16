import * as z from './zod.module.js';

window.z = z;

const formValidation = (e) => {
   e.preventDefault();
   const form = e.currentTarget;
   const schemaName = e.currentTarget.dataset.formSchemaValidation;
   let schema = new Function(document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent)();
   if (schema) {
      const formData = new FormData(form);
      const validator = schema.safeParse(Object.fromEntries(formData))
      if (!validator.success) {
         const bannerError = form.querySelector(`[data-form-error=${schemaName}]`);
         if (bannerError) {
            bannerError.style.whiteSpace = "pre";
            bannerError.textContent = validator.error.issues.map((item) => `• ${item.message}`).join("\n");
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

