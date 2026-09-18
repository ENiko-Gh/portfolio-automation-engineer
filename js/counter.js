/**
 * Counter Animation
 * Animates numbers when they come into viewport
 */

class CounterAnimation {
    constructor() {
        this.counters = document.querySelectorAll('.counter');
        this.animatedCounters = new Set();
        
        this.init();
    }
    
    init() {
        if (this.counters.length === 0) return;
        
        // Create Intersection Observer
        const options = {
            threshold: 0.5,
            rootMargin: '0px'
        };
        
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.animatedCounters.has(entry.target)) {
                    this.animateCounter(entry.target);
                    this.animatedCounters.add(entry.target);
                }
            });
        }, options);
        
        // Observe all counters
        this.counters.forEach(counter => {
            this.observer.observe(counter);
        });
    }
    
    animateCounter(element) {
        const target = parseFloat(element.getAttribute('data-target'));
        const decimals = parseInt(element.getAttribute('data-decimals')) || 0;
        const duration = 2000; // 2 seconds
        const start = 0;
        const increment = target / (duration / 16); // 60fps
        
        let current = start;
        
        const updateCounter = () => {
            current += increment;
            
            if (current < target) {
                element.textContent = current.toFixed(decimals);
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = target.toFixed(decimals);
            }
        };
        
        updateCounter();
    }
}

// ============================================
// CIRCULAR PROGRESS ANIMATION
// ============================================

class CircularProgressAnimation {
    constructor() {
        this.progressBars = document.querySelectorAll('.progress-bar');
        this.animatedBars = new Set();
        
        this.init();
    }
    
    init() {
        if (this.progressBars.length === 0) return;
        
        const options = {
            threshold: 0.5
        };
        
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.animatedBars.has(entry.target)) {
                    this.animateProgress(entry.target);
                    this.animatedBars.add(entry.target);
                }
            });
        }, options);
        
        this.progressBars.forEach(bar => {
            this.observer.observe(bar);
        });
    }
    
    animateProgress(element) {
        const target = parseFloat(element.getAttribute('data-target'));
        const radius = 50;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (target / 100) * circumference;
        
        // Set initial state
        element.style.strokeDashoffset = circumference;
        
        // Animate to target
        setTimeout(() => {
            element.style.transition = 'stroke-dashoffset 2s ease-in-out';
            element.style.strokeDashoffset = offset;
        }, 100);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new CounterAnimation();
    new CircularProgressAnimation();
});