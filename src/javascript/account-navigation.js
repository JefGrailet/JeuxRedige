const sections = document.querySelectorAll("section");
const links = document.querySelectorAll("nav a");

const options = {
    threshold: 0.5,
}

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            links.forEach(link => link.classList.remove("active"));

            document
                .querySelector(`nav a[href="#${entry.target.id}"]`)
                .classList.add("active");
        }
    });
}, options);

sections.forEach(section => observer.observe(section));