const buttonTemplate = document.querySelector('[data-tpl-id="button-toggle"]');
const visibleIconTemplate = document.querySelector('[data-tpl-id="visible-svg"]');
const hiddenIconTemplate = document.querySelector('[data-tpl-id="hidden-svg"]');

const toggleVisibility = (e) => {
   const input = e.currentTarget.parentNode.querySelector("input");
   const icon = e.currentTarget.querySelector("svg");

   if (input.type === "password") {
      input.type = "text"
      input.parentNode.querySelector("button").title="Cacher mot de passe"
      icon.replaceWith(document.importNode(hiddenIconTemplate.content, true).querySelector("svg"))
   } else {
      input.type = "password"
      input.parentNode.querySelector("button").title="Afficher mot de passe"
      icon.replaceWith(document.importNode(visibleIconTemplate.content, true).querySelector("svg"))
   }
}

document
   .querySelectorAll("input[type='password'][data-input-toggle-visibility]")
   .forEach((item) => {
      const clone = document.importNode(buttonTemplate.content, true);

      clone.querySelector("button").addEventListener("click", toggleVisibility);

      const wrappingElement = document.createElement("div");
      wrappingElement.classList.add("toggleInputVisibilityContainer");
      item.replaceWith(wrappingElement);
      wrappingElement.appendChild(item);
      wrappingElement.appendChild(clone);
   });
