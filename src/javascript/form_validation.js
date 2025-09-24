import * as z from './zod.module.js';

window.z = z;

const formValidation = (e) => {
   e.preventDefault();
   const form = e.currentTarget;
   const schemaName = e.currentTarget.dataset.formSchemaValidation;
   let schema = new Function(document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent)();
   if (schema) {
      const formData = new FormData(form);
      const validator = schema.safeParse(Object.fromEntries(formData));

      form.querySelectorAll("input.error").forEach((item) => {
         item.classList.remove("error");
         item.removeAttribute("aria-invalid");
         item.removeAttribute("aria-errormessage");
      })

      if (!validator.success) {
         const bannerError = form.querySelector(`[data-form-error=${schemaName}]`);

         validator.error.issues.forEach((item) => {
            const li = document.createElement('li');
            li.textContent = item.message;

            item.path.forEach((path) => {
               const inputRelated = form.querySelector(`input[name="${String(path)}"]`);

                if (inputRelated) {
                    inputRelated.classList.add("error");
                    inputRelated.ariaInvalid = "true";
                }
            })

            bannerError?.appendChild(li);
        })

         return;
      }
   }
   form.submit();
}

const listForms = document.querySelectorAll("[data-form-schema-validation]");
listForms.forEach((item) => {
   item.addEventListener("submit", formValidation);
});

