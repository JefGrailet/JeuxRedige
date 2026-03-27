const miniatureLightbox = document.getElementById("miniature-lightbox");

const navigationPrevLink = document.querySelector(
   "[data-navigation-prev-link]",
);
const navigationNextLink = document.querySelector(
   "[data-navigation-next-link]",
);

const listNavigationNextLinkSegmentTitles = Array.from(
   navigationNextLink?.querySelectorAll("[data-article-segment-title-idx]") ||
      [],
);
const listNavigationPrevLinkSegmentTitles = Array.from(
   navigationPrevLink?.querySelectorAll("[data-article-segment-title-idx]") ||
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
   firstVisibleSegment.dataset?.articleSegmentContentIdx || 0
);

const navigation = new Proxy(
   { currentIndex: firstVisibleSegment.dataset.articleSegmentContentIdx },
   {
      set(target, prop, value) {
         if (miniatureLightbox.open || value < 0 || value >= listPageSegments.length) {
            return true;
         }

         const oldValue = target[prop];
         getSegment(Number(oldValue), Number(value));
         target[prop] = value;

         return true;
      },
   },
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

      navigation.currentIndex = Number(e.currentTarget.dataset.dynamicNavIdx);
   });
});

const getSegment = (prevIndex, nextIndex) => {
   listPageSegments[prevIndex].style.display = "none";
   listPageSegmentsTitle[prevIndex].style.display = "none";

   listSummaryLinks.forEach((link) => {
      link.parentElement.classList.remove("active");
   });
   listSummaryLinks[nextIndex].parentElement.classList.add(
      "active",
   );

   navigationPrevLink.dataset.dynamicNavIdx = nextIndex - 1;
   navigationNextLink.dataset.dynamicNavIdx = nextIndex + 1;

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

   listPageSegments[nextIndex].removeAttribute("style");
   listPageSegmentsTitle[nextIndex].removeAttribute("style");

   updateUrl(nextIndex);

   if (nextIndex > 0) {
      navigationPrevLink.removeAttribute("style");
      navigationNextLink.removeAttribute("style");
   }

   if (nextIndex === 0) {
      navigationPrevLink.style.display = "none";
      navigationNextLink.removeAttribute("style");
   }
   if (nextIndex === listPageSegments.length - 1) {
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
};

const keyActions = {
   ArrowLeft: () => { navigation.currentIndex-- },
   ArrowRight: () => { navigation.currentIndex++ },
};

document.addEventListener("keydown", (event) => {
   const action = keyActions[event.key];
   if (action) {
      action();
   }
});
