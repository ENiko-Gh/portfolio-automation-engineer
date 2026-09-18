/**
 * Custom Animations
 * Advanced animation effects and interactions
 */

// ============================================
// TERMINAL TYPING EFFECT
// ============================================

class TerminalTyping {
    constructor(element, text, speed = 50) {
        this.element = element;
        this.text = text;
        this.speed = speed;
        this.currentIndex = 0;
        
        this.type();
    }
    
    type() {
        if (this.currentIndex < this.text.length) {
            this.element.textContent += this.text.charAt(this.currentIndex);
            this.currentIndex++;
            setTimeout(() => this.type(), this.speed);
        }
    }
}

// Initialize terminal typing when in view
function initTerminalTyping() {
    const terminalCommands = document.querySelectorAll('.typing-effect');
    
    if (terminalCommands.length === 0) return;
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('typed')) {
                const text = entry.target.textContent;
                entry.target.textContent = '';
                new TerminalTyping(entry.target, text);
                entry.target.classList.add('typed');
            }
        });
    }, { threshold: 0.5 });
    
    terminalCommands.forEach(cmd => observer.observe(cmd));
}

// ============================================
// PARALLAX EFFECT
// ============================================

class ParallaxEffect {
    constructor() {
        this.parallaxElements = document.querySelectorAll('[data-parallax]');
        
        if (this.parallaxElements.length > 0) {
            this.init();
        }
    }
    
    init() {
        window.addEventListener('scroll', () => {
            this.parallaxElements.forEach(el => {
                const speed = el.getAttribute('data-parallax') || 0.5;
                const yPos = -(window.pageYOffset * speed);
                el.style.transform = `translateY(${yPos}px)`;
            });
        });
    }
}

// ============================================
// CURSOR TRAIL EFFECT
// ============================================

class CursorTrail {
    constructor() {
        this.trail = [];
        this.trailLength = 10;
        
        this.init();
    }
    
    init() {
        document.addEventListener('mousemove', (e) => {
            this.addTrailDot(e.clientX, e.clientY);
        });
    }
    
    addTrailDot(x, y) {
        const dot = document.createElement('div');
        dot.className = 'cursor-trail-dot';
        dot.style.left = x + 'px';
        dot.style.top = y + 'px';
        document.body.appendChild(dot);
        
        setTimeout(() => dot.remove(), 500);
    }
}

// ============================================
// MAGNETIC BUTTONS
// ============================================

class MagneticButtons {
    constructor() {
        this.buttons = document.querySelectorAll('.btn-primary, .btn-secondary');
        
        this.init();
    }
    
    init() {
        this.buttons.forEach(button => {
            button.addEventListener('mousemove', (e) => {
                const rect = button.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                button.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
            });
            
            button.addEventListener('mouseleave', () => {
                button.style.transform = '';
            });
        });
    }
}

// ============================================
// TEXT REVEAL ANIMATION
// ============================================

class TextReveal {
    constructor() {
        this.textElements = document.querySelectorAll('[data-text-reveal]');
        
        if (this.textElements.length > 0) {
            this.init();
        }
    }
    
    init() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.revealText(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        this.textElements.forEach(el => observer.observe(el));
    }
    
    revealText(element) {
        const text = element.textContent;
        element.textContent = '';
        element.style.opacity = '1';
        
        text.split('').forEach((char, index) => {
            const span = document.createElement('span');
            span.textContent = char;
            span.style.opacity = '0';
            span.style.animation = `fadeIn 0.5s ease-out ${index * 0.03}s forwards`;
            element.appendChild(span);
        });
    }
}

// ============================================
// 3D TILT EFFECT ON CARDS
// ============================================

class CardTiltEffect {
    constructor() {
        this.cards = document.querySelectorAll('.bento-card, .cert-card, .project-card');
        
        this.init();
    }
    
    init() {
        this.cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });
    }
}

// ============================================
// INITIALIZE ALL ANIMATIONS
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize terminal typing
    initTerminalTyping();
    
    // Initialize parallax (disabled on mobile for performance)
    if (window.innerWidth > 768) {
        new ParallaxEffect();
        new MagneticButtons();
        new CardTiltEffect();
    }
    
    // Initialize text reveal
    new TextReveal();
    
    console.log('Advanced animations initialized');
});