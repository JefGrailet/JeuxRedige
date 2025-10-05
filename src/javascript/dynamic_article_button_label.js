const buttonLabel = document.querySelector("[data-dynamic-label]")
const select = document.querySelector("[data-article-type]");
const selectData = JSON.parse(select.dataset.articleType);

select.addEventListener("change", (e) => {
   const type = e.target.value;
   buttonLabel.textContent = selectData?.[type] ? selectData[type].toLowerCase() : "article";
})
