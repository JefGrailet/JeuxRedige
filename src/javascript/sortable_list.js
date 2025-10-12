const listSortableLists = document.querySelectorAll("[data-sortable-list]");

const sortableList = (list) => {
   const listOptions = JSON.parse(list.dataset.sortableList);

   const getItemsChanged = (start, end) => {
      const listChildren = list.querySelectorAll(":scope > *");
      const listChildrenUpdated = Array.from(listChildren).slice(start, end + 1);

      return listChildrenUpdated;
   }

   const sleep = ms => new Promise(r => setTimeout(r, ms));


   new Sortable(list, {
      animation: 150,
      sort: true,
      // handle: ".handle",
      // ghostClass: "blue-background-class",
      onUpdate: async (e) => {
         const listChildrenUpdated = getItemsChanged(
            Math.min(e.oldIndex, e.newIndex),
            Math.max(e.oldIndex, e.newIndex)
         );

         listChildrenUpdated.forEach((item, idx) => {
            const articleIndexContainer = item.querySelector("[data-segment-index]")
            articleIndexContainer.textContent = idx + Math.min(e.oldIndex, e.newIndex) + 1;
         });

         for (let index = 0; index < listChildrenUpdated.length - 1; index++) {
            const firstElement = listChildrenUpdated[index];
            const secondElement = listChildrenUpdated[index + 1];

            const articleId = Number(firstElement.dataset.articleId);
            const firstSegmentId = Number(firstElement.dataset.segmentId);
            const secondSegmentId = Number(secondElement.dataset.segmentId);

            fetch("./ajax/SwitchSegmentsJSON.php", {
               method: "POST",
               headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/json'
               },
               body: JSON.stringify({
                  id_article: articleId,
                  id_segment1: firstSegmentId,
                  id_segment2: secondSegmentId,
               }),
            })
            await sleep(250);
         }
      },
      ...listOptions,
   });
};

listSortableLists.forEach((item) => {
   sortableList(item);
});
