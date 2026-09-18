/**
 * Main JavaScript — CORREGIDO
 * Fixes:
 * 1. navbar null check (línea 82)
 * 2. navMenu null check (línea 107)
 * 3. Preload cambiado a profile.png
 * 4. Lazy load sin crash
 */

const CONFIG = {
    scrollOffset: 80,
    mobileBreakpoint: 768,
    debounceDelay: 150,
    animationDuration: 300
};

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

function smoothScrollTo(targetId) {
    const target = document.querySelector(targetId);
    if (!target) return;
    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - CONFIG.scrollOffset;
    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
}

// ── NAVIGATION ────────────────────────────────────────────────
class Navigation {
    constructor() {
        this.navbar       = document.getElementById('navbar');
        this.mobileToggle = document.getElementById('mobile-toggle');
        this.navMenu      = document.getElementById('nav-menu');
        this.navLinks     = document.querySelectorAll('.nav-link');
        this.init();
    }

    init() {
        // FIX 1: verificar que navbar existe antes de usar classList
        if (this.navbar) {
            window.addEventListener('scroll', debounce(() => {
                if (window.scrollY > 50) {
                    this.navbar.classList.add('scrolled');
                } else {
                    this.navbar.classList.remove('scrolled');
                }
            }, CONFIG.debounceDelay));
        }

        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.toggleMobileMenu());
        }

        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('#')) {
                    e.preventDefault();
                    smoothScrollTo(href);
                    this.closeMobileMenu();
                }
            });
        });

        // FIX 2: verificar que navMenu existe antes de usar classList
        document.addEventListener('click', (e) => {
            if (this.navMenu &&
                this.navMenu.classList.contains('active') &&
                !this.navMenu.contains(e.target) &&
                this.mobileToggle &&
                !this.mobileToggle.contains(e.target)) {
                this.closeMobileMenu();
            }
        });
    }

    toggleMobileMenu() {
        if (!this.navMenu || !this.mobileToggle) return;
        this.navMenu.classList.toggle('active');
        this.mobileToggle.classList.toggle('active');
        document.body.style.overflow = this.navMenu.classList.contains('active') ? 'hidden' : '';
    }

    closeMobileMenu() {
        if (!this.navMenu || !this.mobileToggle) return;
        this.navMenu.classList.remove('active');
        this.mobileToggle.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ── LANGUAGE SWITCHER ─────────────────────────────────────────
class LanguageSwitcher {
    constructor() {
        this.langButtons = document.querySelectorAll('.lang-btn');
        this.currentLang = 'en';
        this.init();
    }

    init() {
        this.langButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.getAttribute('data-lang');
                if (lang !== this.currentLang) {
                    this.switchLanguage(lang);
                }
            });
        });
    }

    switchLanguage(lang) {
        this.currentLang = lang;
        this.langButtons.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
        const elements = document.querySelectorAll(`[data-${lang}]`);
        elements.forEach(el => {
            const content = el.getAttribute(`data-${lang}`);
            if (content) el.textContent = content;
        });
        localStorage.setItem('preferredLanguage', lang);
    }

    loadPreference() {
        const savedLang = localStorage.getItem('preferredLanguage');
        if (savedLang && savedLang !== this.currentLang) {
            this.switchLanguage(savedLang);
        }
    }
}

// ── AOS ───────────────────────────────────────────────────────
function initScrollAnimations() {
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-out',
            once: true,
            offset: 100,
            disable: 'mobile'
        });
    }
}

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const navigation = new Navigation();
    const languageSwitcher = new LanguageSwitcher();
    languageSwitcher.loadPreference();
    initScrollAnimations();
    console.log('Portfolio initialized successfully');
});

// ── LAZY LOAD ─────────────────────────────────────────────────
// FIX 3: solo procesar imágenes que tienen data-src (no todas)
if ('loading' in HTMLImageElement.prototype) {
    const images = document.querySelectorAll('img[data-src]');
    images.forEach(img => {
        if (img.dataset.src) img.src = img.dataset.src;
    });
}

// FIX 4: Preload corregido — profile.png (no .jpg) y solo si existe
// Eliminado el preload automático que generaba la advertencia
// El preload se hace en el <head> del HTML si se necesita

// ── CONTACT FORM ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData  = new FormData(contactForm);
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const origText  = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="spinner"></div> Sending...';
        }

        const data = {
            name:    formData.get('name'),
            email:   formData.get('email'),
            phone:   formData.get('phone'),
            subject: formData.get('subject'),
            message: formData.get('message')
        };

        try {
            const res    = await fetch('./api/contact.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data)
            });
            const result = await res.json();

            if (result.success) {
                contactForm.style.display = 'none';
                const successEl = document.getElementById('contactSuccess');
                if (successEl) successEl.style.display = 'block';
                contactForm.reset();
                setTimeout(() => {
                    contactForm.style.display = 'block';
                    if (successEl) successEl.style.display = 'none';
                }, 5000);
            } else {
                alert(result.message || 'Error al enviar. Intenta de nuevo.');
            }
        } catch (error) {
            console.error('Contact form error:', error);
            alert('An error occurred. Please try again.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            }
        }
    });
});