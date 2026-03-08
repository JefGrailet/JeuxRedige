const listOuterText = document.querySelectorAll(
   "[data-ellipse-scrolling-text-outer]"
);

const listTextWrapper = document.querySelectorAll(
   "[data-ellipse-scrolling-text-wrapper]"
);

const handleMouseEnter = (e) => {
   let outerEl = e.currentTarget.querySelector("[data-ellipse-scrolling-text-outer]");
   if (e.currentTarget.tagName === "A") {
      outerEl = e.currentTarget.closest("li").querySelector("[data-ellipse-scrolling-text-outer]");
   }

   const innerEl = outerEl.querySelector("[data-ellipse-scrolling-text-inner]");

   const diff = innerEl.offsetWidth - outerEl.offsetWidth;

   if (diff > 0) {
      const duration = Number((diff / 50).toFixed(2));
      innerEl.style.transitionDuration = `${duration}s`;
      innerEl.style.left = `${-diff}px`;
   }
};

const handleMouseLeave = (e) => {
   const outerEl = e.currentTarget.querySelector("[data-ellipse-scrolling-text-outer]");
   const innerEl = outerEl.querySelector("[data-ellipse-scrolling-text-inner]");

   innerEl.style.transitionDuration = "0.3s";
   innerEl.style.left = "0px";
};

listTextWrapper.forEach((item) => {
   item.addEventListener("mouseenter", handleMouseEnter);
   item.addEventListener("mouseleave", handleMouseLeave);

   item.addEventListener("focus", handleMouseEnter);
   item.addEventListener("blur", handleMouseLeave);
});
