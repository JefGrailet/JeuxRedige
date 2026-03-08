import * as z from "./libs/zod.module.js";

window.z = z;

window.z.config(z.locales.fr());

const formValidation = async (e) => {
   const form = e.currentTarget;
   if (!("isDirty" in form.dataset)) {
      return;
   }

   e.preventDefault();
   const schemaName = form.dataset.formSchemaValidation;
   let schema = new Function(
      document.querySelector(`[data-form-schema=${schemaName}]`)?.textContent
   )();

   if (!schema) {
      form.dataset.isValid = "true";
      return true;
   }

   const formData = new FormData(form);
   if (e?.submitter?.hasAttribute("value")) {
      const inputActionType = document.createElement("input");
      inputActionType.setAttribute("type", "hidden")
      inputActionType.setAttribute("value", e.submitter.getAttribute("value"))
      inputActionType.setAttribute("name", "action");
      form.appendChild(inputActionType);
   }

   const payload = {}
   formData.entries().forEach((item) => {
      const [key, value] = item;

      if (key in payload || key.includes("[]")) {
         payload[key] = [
            ...(payload?.[key] ? payload[key] : []),
            value
         ].filter(Boolean);
      } else {
         payload[key] = value;
      }
   })

   const validator = await schema.safeParseAsync(payload);

   form.querySelectorAll(":not(.alert):is(.error)").forEach((item) => {
      item.classList.remove("error");
      item.removeAttribute("aria-invalid");
      item.removeAttribute("aria-errormessage");
   });

   const bannerError = form.querySelector(
      `[data-form-error=${schemaName}]`
   );
   bannerError.innerHTML = "";

   if (!validator.success) {
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

      return false;
   }

   form.dataset.isValid = "true";

   return true;
};

const formSubmission = async (e) => {
   const form = e.currentTarget;
   form.dataset.isDirty = "";
   form.dataset.isValid = "";

   const isFormValid = await formValidation(e);

   if (!isFormValid) {
      const schemaName = form.dataset.formSchemaValidation;
      const bannerError = form.querySelector(
         `[data-form-error=${schemaName}]`
      );
      if (bannerError) {
         bannerError.scrollIntoView();
      }
      return;
   }

   form.submit();
};

const listForms = document.querySelectorAll("[data-form-schema-validation]");
listForms.forEach((item) => {
   item.addEventListener("submit", formSubmission);
   item.addEventListener("input", formValidation);
});
