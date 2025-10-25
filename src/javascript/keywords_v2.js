const formatResult = (state) => {
   if (state.loading) {
      return "Chargement...";
   }

   const $state = $(
      `<span>
         ${state.text} ${state.alreadyExist ? "" : `<span class="badge info">nouveau</span>`}
      </span>`
   );

   return $state;
}

$("[data-dropdown-keywords]").each((_, el) => {
   let customOptions = {};
   if (el.dataset.dropdownKeywords) {
      customOptions = JSON.parse(el.dataset.dropdownKeywords);
   }

   $(el).select2({
      placeholder: "Entrez un mot-clef",
      minimumInputLength: 2,
      minimumResultsForSearch: 2,
      language: "fr",
      cache: true,
      width: '100%',
      ajax: {
         delay: 250,
         url: "./ajax/FindKeywordsJSON.php",
         dataType: "json",
         data: function (params) {
            const query = {
               keyword: params.term,
            };

            return query;
         },
         processResults: function (data) {
            return {
               results: data.map((item) => ({ text: item, id: item, alreadyExist: true })),
            };
         },
      },
      templateResult: formatResult,
      ...customOptions,
   })
   .on("select2:select select2:unselect", (e) => {
      const form = e.target.closest("form[data-is-dirty]")
      if (form) {
         form.dispatchEvent(new Event('input', { bubbles: true }));
      }
   });
});


const urlParams = new URLSearchParams(window.location.search);
let listKeywords = urlParams.getAll('keywords[]');
if (listKeywords.length === 0) {
   listKeywords = urlParams.getAll('keywords');
}

listKeywords.forEach((keyword) => {
   const option = new Option(keyword, keyword, true, true);
   $("[data-dropdown-keywords]").append(option).trigger('change');
});
