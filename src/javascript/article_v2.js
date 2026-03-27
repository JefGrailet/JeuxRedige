const miniatureLightbox = document.getElementById("miniature-lightbox");

miniatureLightbox.addEventListener("toggle", (evt) => {
   if (!window.lastTrigger) {
      return;
   }
   const comment = evt.currentTarget.querySelector("[data-comment]");
   const videoSource = evt.currentTarget.querySelector("video");
   const img = evt.currentTarget.querySelector("img");
   if (evt.newState === "open") {
      const mediaData = JSON.parse(window.lastTrigger.dataset.mediaData);
      const mediaLink = evt.currentTarget.querySelector("a");

      switch (mediaData.mediaType) {
         case "image":
            {
               videoSource.style.display = "none";
               img.style.removeProperty("display");

               img.src = mediaData.full.src;
               img.alt = mediaData.comment;

               mediaLink.href = mediaData.full.src;

               comment.textContent = mediaData.comment;
            }
            break;

         case "video":
            {
               img.style.display = "none";
               videoSource.style.removeProperty("display");
               videoSource.querySelector("source").src = mediaData.full.src;
               videoSource.querySelector("source").type = mediaData.mimeType;
               videoSource.load();

               mediaLink.href = mediaData.full.src;

               comment.textContent = mediaData.comment;
            }
            break;

         default:
            break;
      }
   } else {
      setTimeout(() => {
         comment.textContent = "";
         img.src = "";
         img.alt = "";
         if (videoSource) {
            videoSource.pause();
            videoSource.currentTime = 0;
         }
      }, 500);
   }
});

const btnShare = document.querySelector("[data-share]");

if (navigator.share && btnShare) {
   btnShare.style.display = "initial";
   btnShare.addEventListener("click", async (e) => {
      try {
         const shareData = JSON.parse(e.currentTarget.dataset?.share || "{}");
         await navigator.share({
            title: shareData.title,
            text: shareData.text,
            url: window.location.href,
         });
      } catch (err) {
      }
   });
} else {
   btnShare?.remove();
}

const navigationPrevLink = document.querySelector(
   "[data-navigation-prev-link]",
);
const navigationNextLink = document.querySelector(
   "[data-navigation-next-link]",
);

const listNavigationNextLinkSegmentTitles = Array.from(
   navigationNextLink.querySelectorAll("[data-article-segment-title-idx]") ||
      [],
);
const listNavigationPrevLinkSegmentTitles = Array.from(
   navigationPrevLink.querySelectorAll("[data-article-segment-title-idx]") ||
      [],
);

const listNavigationLinks = Array.from(
   document.querySelectorAll("[data-dynamic-nav-idx]") || [],
);
const listSummaryLinks = Array.from(
   document.querySelectorAll("[data-navigation-summary-link]") || [],
);

const listPageSegments = Array.from(
   document.querySelectorAll("[data-article-segment-content-idx]") || [],
);

const listPageSegmentsTitle = Array.from(
   document.querySelectorAll(".subtitle [data-article-segment-title-idx]") ||
      [],
);

const firstVisibleSegment = listPageSegments.find(
   (item) => item.checkVisibility() === true,
);
let indexCurrentVisibleItem = Number(
   firstVisibleSegment.dataset.articleSegmentContentIdx,
);

const REGEX_PAGE_PARAM = /\/page\/\d+/;
const updateUrl = (newPage) => {
   const url = new URL(window.location.href);

   if (REGEX_PAGE_PARAM.test(url.pathname)) {
      url.pathname = url.pathname.replace(
         REGEX_PAGE_PARAM,
         `/page/${newPage + 1}`,
      );
   } else {
      url.pathname = url.pathname.replace(/\/$/, "") + `/page/${newPage + 1}`;
   }

   history.replaceState({}, "", url);
};
let navigationPrevLinkTitle = null;
let navigationNextLinkTitle = null;

listNavigationLinks.forEach((item) => {
   item.addEventListener("click", (e) => {
      e.preventDefault();
      const linkIdx = Number(e.currentTarget.dataset.dynamicNavIdx);
      listPageSegments[indexCurrentVisibleItem].style.display = "none";
      listPageSegmentsTitle[indexCurrentVisibleItem].style.display = "none";

      // After switch
      indexCurrentVisibleItem = linkIdx;

      listSummaryLinks.forEach((link) => {
         link.parentElement.classList.remove("active");
      });
      listSummaryLinks[indexCurrentVisibleItem].parentElement.classList.add(
         "active",
      );

      navigationPrevLink.dataset.dynamicNavIdx = indexCurrentVisibleItem - 1;
      navigationNextLink.dataset.dynamicNavIdx = indexCurrentVisibleItem + 1;

      listNavigationNextLinkSegmentTitles.forEach((title) => {
         title.style.display = "none";
      });

      navigationNextLinkTitle = navigationNextLink.querySelector(
         `[data-article-segment-title-idx="${Number(navigationNextLink.dataset.dynamicNavIdx)}"]`,
      );
      if (navigationNextLinkTitle) {
         navigationNextLinkTitle.removeAttribute("style");
      }

      listNavigationPrevLinkSegmentTitles.forEach((title) => {
         title.style.display = "none";
      });

      navigationPrevLinkTitle = navigationPrevLink.querySelector(
         `[data-article-segment-title-idx="${Number(navigationPrevLink.dataset.dynamicNavIdx)}"]`,
      );
      if (navigationPrevLinkTitle) {
         navigationPrevLinkTitle.removeAttribute("style");
      }

      listPageSegments[indexCurrentVisibleItem].removeAttribute("style");
      listPageSegmentsTitle[indexCurrentVisibleItem].removeAttribute("style");

      updateUrl(indexCurrentVisibleItem);

      if (indexCurrentVisibleItem > 0) {
         navigationPrevLink.removeAttribute("style");
         navigationNextLink.removeAttribute("style");
      }

      if (indexCurrentVisibleItem === 0) {
         navigationPrevLink.style.display = "none";
         navigationNextLink.removeAttribute("style");
      }
      if (indexCurrentVisibleItem === listPageSegments.length - 1) {
         navigationNextLink.style.display = "none";
         navigationPrevLink.removeAttribute("style");
      }

      document.body.style.minHeight = "200vh";

      window.scrollTo({
         top: 0,
         behavior: "instant",
      });

      requestAnimationFrame(() => {
         document.body.style.minHeight = "";
      });
   });
});
