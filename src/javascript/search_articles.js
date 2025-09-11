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

$("[data-dropdown-keywords]").select2({
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
            results: data.map((item) => ({ text: item, id: item })),
         };
      },
   },
   // templateResult: formatState,
});

const urlParams = new URLSearchParams(window.location.search);
const listKeywords = urlParams.getAll('keywords[]');

listKeywords.forEach((keyword) => {
   const option = new Option(keyword, keyword, true, true);
   $("[data-dropdown-keywords]").append(option).trigger('change');
});
