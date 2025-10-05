import * as z from "./zod.module.js";

window.z = z;

const formValidation = (e) => {
   const form = e.currentTarget;
   if (!("isDirty" in form.dataset)) {
      return;
   }

   e.preventDefault();
   const schemaName = e.currentTarget.dataset.formSchemaValidation;
   let schema = new Function(
      document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent
   )();
   if (schema) {
      const formData = new FormData(form);
      if (!formData.has("keywords[]")) {
         formData.append("keywords[]", "");
      }

      const validator = schema.safeParse(Object.fromEntries(formData));

      form.querySelectorAll(":not(.alert):is(.error)").forEach((item) => {
         item.classList.remove("error");
         item.removeAttribute("aria-invalid");
         item.removeAttribute("aria-errormessage");
      });

      if (!validator.success) {
         const bannerError = form.querySelector(
            `[data-form-error=${schemaName}]`
         );
         bannerError.innerHTML = "";
         validator.error.issues.forEach((item) => {
            const li = document.createElement("li");
            li.textContent = item.message;

            item.path.forEach((path) => {
               const inputRelated = form.querySelector(
                  `[name="${String(path)}"]`
               );

               if (inputRelated) {
                  inputRelated.classList.add("error");
                  inputRelated.ariaInvalid = "true";
               }
            });

            bannerError?.appendChild(li);
         });

         return;
      }
   }
};

const formSubmission = (e) => {
   const form = e.currentTarget;
   form.dataset.isDirty = "";

   if (!formValidation(e)) {
      return;
   }

   form.submit();
};

const listForms = document.querySelectorAll("[data-form-schema-validation]");
listForms.forEach((item) => {
   item.addEventListener("submit", formSubmission);
   item.addEventListener("input", formValidation);
});
