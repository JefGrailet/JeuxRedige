const listArticlesLink = document.querySelectorAll(
   ":is(#articlesPool, #articlesPoolIndex) li",
);
listArticlesLink.forEach(($item) => {
   $item.addEventListener("click", (e) => {
      e.preventDefault();

      listArticlesLink.forEach(($el) => {
         $el.style.viewTransitionName = "none";
         const extra = $el.querySelector(".articleThumbnailExtra");
         if (extra) {
            $el.querySelector(".articleThumbnailExtra").style.viewTransitionName =
            "none";
         }
      });

      const link = e.currentTarget.querySelector("a").href;
      const articleTitle = e.currentTarget.querySelector(".articleTitle a");

      const articleExtra = e.currentTarget.querySelector(
         ".articleThumbnailExtra",
      );
      e.currentTarget.style.viewTransitionName = "article-header-image";

      window.location.href = link;
   });
});
