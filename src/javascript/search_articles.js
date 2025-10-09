function formatState(state) {
   if (state.loading) {
      return "Chargement...";
   }

   const $tpl = $(`
      <div class="keyword-result">
         ${state}
      </div>
   `);

   return $tpl;
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
      ...customOptions,
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
               results: data.map((item) => ({ text: item, id: item })),
            };
         },
      },
   }).on("select2:select select2:unselect", (e) => {
      const form = e.target.closest("form[data-is-dirty]")
      if (form) {
         form.dispatchEvent(new Event('input', { bubbles: true }));
      }
   })
});


const urlParams = new URLSearchParams(window.location.search);
const listKeywords = urlParams.getAll('keywords[]');

listKeywords.forEach((keyword) => {
   const option = new Option(keyword, keyword, true, true);
   $("[data-dropdown-keywords]").append(option).trigger('change');
});
