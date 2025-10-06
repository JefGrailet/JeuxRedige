const articleContent = document.querySelector("[data-article-content]");
const articleHeader = document.querySelector("[data-article-header]");

articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;

window.addEventListener("resize", () => {
   if (window.scrollY === 0) {
      articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;
   }
});

screen.orientation.addEventListener("change", () => {
   if (window.scrollY === 0) {
      articleContent.style.marginTop = `${articleHeader.offsetHeight}px`;
   }
});
