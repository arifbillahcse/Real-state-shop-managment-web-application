/* ===== Preloader ===== */
window.addEventListener('load', () => {
    setTimeout(() => {
        document.getElementById('preloader')?.classList.add('done');
    }, 1800);
});

/* ===== Particles ===== */
(function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    for (let i = 0; i < 22; i++) {
        const p = document.createElement('div');
        const size = Math.random() * 4 + 1.5;
        Object.assign(p.style, {
            position: 'absolute',
            width: size + 'px', height: size + 'px',
            borderRadius: '50%',
            background: Math.random() > .5
                ? `rgba(230,168,23,${Math.random()*.25+.08})`
                : `rgba(192,57,43,${Math.random()*.2+.06})`,
            top: Math.random() * 100 + '%',
            left: Math.random() * 100 + '%',
            animation: `particle-float ${Math.random()*8+6}s ease-in-out infinite`,
            animationDelay: `-${Math.random()*8}s`,
        });
        container.appendChild(p);
    }
    const style = document.createElement('style');
    style.textContent = `@keyframes particle-float{0%,100%{transform:translateY(0) scale(1);opacity:.7}50%{transform:translateY(-30px) scale(1.1);opacity:1}}`;
    document.head.appendChild(style);
})();

/* ===== Navbar ===== */
const navbar   = document.getElementById('navbar');
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
const backTop  = document.getElementById('backTop');

hamburger?.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    navLinks.classList.toggle('open');
});
navLinks?.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
        hamburger.classList.remove('open');
        navLinks.classList.remove('open');
    });
});

/* ===== Scroll Handler ===== */
const allSections = document.querySelectorAll('section[id]');
const navLinkEls  = navLinks?.querySelectorAll('.nav-link') ?? [];

window.addEventListener('scroll', () => {
    const y = window.scrollY;
    navbar.classList.toggle('scrolled', y > 40);
    backTop.classList.toggle('show', y > 500);
    // active nav
    let current = '';
    allSections.forEach(s => { if (y >= s.offsetTop - 160) current = s.id; });
    navLinkEls.forEach(a => {
        a.classList.toggle('active', a.getAttribute('href') === '#' + current);
    });
}, { passive: true });

/* ===== Reveal on Scroll ===== */
const reveals = document.querySelectorAll('[data-reveal]');
const revealObs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
            revealObs.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });
reveals.forEach(el => revealObs.observe(el));

/* ===== Animated Counters ===== */
function toBn(n) {
    return String(n).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[d]);
}
const countEls = document.querySelectorAll('.count-num');
let counted = false;
const statsObs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting && !counted) {
        counted = true;
        countEls.forEach(el => {
            const target = +el.dataset.count;
            let cur = 0;
            const step = Math.ceil(target / 55);
            const t = setInterval(() => {
                cur = Math.min(cur + step, target);
                el.textContent = toBn(cur);
                if (cur >= target) clearInterval(t);
            }, 30);
        });
    }
}, { threshold: .5 });
const statsEl = document.querySelector('.stats');
if (statsEl) statsObs.observe(statsEl);

/* ===== Contact Form ===== */
const form  = document.getElementById('contactForm');
const cfMsg = document.getElementById('cfMsg');
form?.addEventListener('submit', e => {
    e.preventDefault();
    const d = new FormData(form);
    const name = d.get('name')?.trim();
    const phone = d.get('phone')?.trim();
    const msg   = d.get('message')?.trim();
    if (!name || !phone || !msg) {
        cfMsg.textContent = '❌ অনুগ্রহ করে নাম, মোবাইল ও বার্তা পূরণ করুন।';
        cfMsg.className = 'cf-msg err';
        return;
    }
    const btn = form.querySelector('.cf-submit');
    btn.innerHTML = '<span>পাঠানো হচ্ছে...</span>';
    btn.disabled = true;
    setTimeout(() => {
        cfMsg.innerHTML = `✅ ধন্যবাদ <strong>${name}</strong>! আপনার বার্তা পাওয়া গেছে। আমরা শীঘ্রই যোগাযোগ করব।`;
        cfMsg.className = 'cf-msg ok';
        form.reset();
        btn.innerHTML = '<span>বার্তা পাঠান</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>';
        btn.disabled = false;
        setTimeout(() => { cfMsg.textContent = ''; cfMsg.className = 'cf-msg'; }, 7000);
    }, 1200);
});

/* ===== Year ===== */
const yr = document.getElementById('yr');
if (yr) yr.textContent = new Date().getFullYear();
