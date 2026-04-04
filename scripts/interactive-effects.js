/* interactive-effects.js - Global Mouse Interactivity & Animations */

(function () {
    'use strict';

    // Prevent multiple initializations
    if (window.interactiveEffectsInitialized) return;
    window.interactiveEffectsInitialized = true;

    console.log('Elite Interactive Effects Loaded');

    // 1. Mouse Follower / Cursor (Subtle trailing)
    const initCursor = () => {
        // Only on desktop
        if (window.innerWidth < 1025) return;
        
        const cursor = document.createElement('div');
        cursor.id = 'nd-cursor';
        cursor.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 32px;
            height: 32px;
            border: 2px solid #2e8b5711;
            border-radius: 50%;
            pointer-events: none;
            z-index: 100000;
            transition: transform 0.15s cubic-bezier(0.23, 1, 0.32, 1), background 0.3s ease;
            transform: translate(-50%, -50%);
            display: none;
            backdrop-filter: blur(1px);
        `;
        document.body.appendChild(cursor);

        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            cursor.style.display = 'block';
            cursor.style.transform = `translate(${mouseX}px, ${mouseY}px) scale(1)`;
        });

        // Click effect
        document.addEventListener('mousedown', () => {
            cursor.style.transform = `translate(${mouseX}px, ${mouseY}px) scale(0.8)`;
            cursor.style.background = 'rgba(46, 139, 87, 0.05)';
        });

        document.addEventListener('mouseup', () => {
            cursor.style.transform = `translate(${mouseX}px, ${mouseY}px) scale(1.1)`;
            cursor.style.background = 'transparent';
        });

        // Hover scale on interactive elements
        const interactive = 'a, button, .btn, .card, .benefit-card, .nutrition-card, input, select';
        document.addEventListener('mouseover', (e) => {
            if (e.target.closest(interactive)) {
                cursor.style.width = '64px';
                cursor.style.height = '64px';
                cursor.style.borderColor = '#2e8b5777';
            }
        });

        document.addEventListener('mouseout', (e) => {
            if (e.target.closest(interactive)) {
                cursor.style.width = '32px';
                cursor.style.height = '32px';
                cursor.style.borderColor = '#2e8b5711';
            }
        });
    };

    // 2. Magnetic Buttons & Icons
    const initMagnetic = () => {
        const magnets = document.querySelectorAll('.btn, .social-links a, .logo-img, .benefit-icon, .nav-links a i');

        magnets.forEach((mag) => {
            mag.addEventListener('mousemove', (e) => {
                const rect = mag.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                mag.style.transform = `translate(${x * 0.35}px, ${y * 0.35}px) scale(1.1)`;
            });

            mag.addEventListener('mouseleave', () => {
                mag.style.transform = '';
            });
        });
    };

    // 3. Parallax Card Tilt (Subtle)
    const initTilt = () => {
        const cards = document.querySelectorAll('.card, .benefit-card, .nutrition-card, .dashboard-section');

        cards.forEach((card) => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px) scale(1.02)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });
    };

    // 4. Reveal Animations on Scroll (Refined for Instant Visibility)
    const initScrollReveal = () => {
        // Only target elements that are NOT meant to be instantly visible or have a specific class
        const sections = document.querySelectorAll('.reveal-on-scroll, section.reveal, .card.animate');
        
        // If there are no specific reveal elements, we exit early to save performance
        if (sections.length === 0) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    revealElement(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        const revealElement = (el) => {
            el.classList.add('visible');
            el.style.opacity = '1';
            el.style.transform = 'translateY(0) scale(1)';
        };

        sections.forEach(sec => {
            // Initial state for specifically tagged elements
            sec.style.opacity = '0';
            sec.style.transform = 'translateY(30px) scale(0.95)';
            sec.style.transition = 'all 0.8s cubic-bezier(0.23, 1, 0.32, 1)';
            observer.observe(sec);
        });
    };

    const init = () => {
        initCursor();
        initMagnetic();
        initTilt();
        initScrollReveal();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
