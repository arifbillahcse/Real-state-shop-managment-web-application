// ============ Mobile Nav Toggle ============
const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');

navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    navToggle.classList.toggle('open');
});

// Close mobile menu when a link is clicked
navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        navToggle.classList.remove('open');
    });
});

// ============ Navbar shadow + Back-to-top on scroll ============
const navbar = document.getElementById('navbar');
const backTop = document.getElementById('backTop');

window.addEventListener('scroll', () => {
    if (window.scrollY > 30) navbar.classList.add('scrolled');
    else navbar.classList.remove('scrolled');

    if (window.scrollY > 400) backTop.classList.add('show');
    else backTop.classList.remove('show');

    highlightNav();
});

// ============ Active Nav Link on Scroll ============
const sections = document.querySelectorAll('section[id]');
const menuLinks = navLinks.querySelectorAll('a');

function highlightNav() {
    let current = '';
    sections.forEach(sec => {
        const top = sec.offsetTop - 120;
        if (window.scrollY >= top) current = sec.id;
    });
    menuLinks.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === '#' + current);
    });
}

// ============ Reveal on Scroll ============
const revealEls = document.querySelectorAll(
    '.about-text, .about-img, .product-card, .why-card, .contact-info, .contact-form, .section-head'
);
revealEls.forEach(el => el.classList.add('reveal'));

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

revealEls.forEach(el => observer.observe(el));

// ============ Animated Counters ============
const counters = document.querySelectorAll('.stat h3');
let counted = false;

function toBengali(num) {
    const bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return String(num).replace(/\d/g, d => bn[d]);
}

function runCounters() {
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-count');
        let count = 0;
        const step = Math.max(1, Math.ceil(target / 60));
        const timer = setInterval(() => {
            count += step;
            if (count >= target) { count = target; clearInterval(timer); }
            counter.textContent = toBengali(count) + (target === 100 ? '%' : '+');
        }, 25);
    });
}

const statsObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !counted) {
            counted = true;
            runCounters();
        }
    });
}, { threshold: 0.4 });

const statsSection = document.querySelector('.stats');
if (statsSection) statsObserver.observe(statsSection);

// ============ Contact Form (front-end only) ============
const form = document.getElementById('contactForm');
const formMsg = document.getElementById('formMsg');

form.addEventListener('submit', e => {
    e.preventDefault();
    const data = new FormData(form);
    const name = data.get('name').trim();
    const phone = data.get('phone').trim();
    const message = data.get('message').trim();

    if (!name || !phone || !message) {
        formMsg.textContent = 'অনুগ্রহ করে নাম, মোবাইল ও বার্তা পূরণ করুন।';
        formMsg.className = 'form-msg err';
        return;
    }

    formMsg.textContent = 'ধন্যবাদ ' + name + '! আপনার বার্তা গ্রহণ করা হয়েছে। আমরা শীঘ্রই যোগাযোগ করব।';
    formMsg.className = 'form-msg ok';
    form.reset();

    setTimeout(() => { formMsg.textContent = ''; }, 6000);
});

// ============ Footer Year ============
document.getElementById('year').textContent = new Date().getFullYear();
