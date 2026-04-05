/* premium-effects.js - Global Interactivity Engine */

(function() {
    'use strict';

    if (window.premiumEffectsInitialized) return;
    window.premiumEffectsInitialized = true;

    console.log('Premium Effects Engine Loaded');

    // 1. Organic Cursor & Spotlight Tracking
    const initTracking = () => {
        const cursor = document.getElementById('organicCursor');
        const spotlight = document.getElementById('spotlight');
        const aura = document.getElementById('cursorAura');
        
        if (!cursor && !spotlight) return;

        document.addEventListener('mousemove', (e) => {
            const { clientX, clientY } = e;
            
            // Move Cursor
            if (cursor) {
                cursor.style.transform = `translate(${clientX}px, ${clientY}px) scale(1)`;
                cursor.style.opacity = '1';
            }

            // Move Aura
            if (aura) {
                aura.style.left = `${clientX}px`;
                aura.style.top = `${clientY}px`;
            }

            // Move Spotlight (Offset for center)
            if (spotlight) {
                spotlight.style.left = `${clientX}px`;
                spotlight.style.top = `${clientY}px`;
            }
        });

        // Hover Grow Effect
        const interactives = 'a, button, .btn, .bento-stat, .command-tile, .card, .stat-card, input, select';
        document.addEventListener('mouseover', (e) => {
            if (e.target.closest(interactives)) {
                if (cursor) cursor.classList.add('grow');
                if (spotlight) spotlight.classList.add('focus');
            }
        });

        document.addEventListener('mouseout', (e) => {
            if (e.target.closest(interactives)) {
                if (cursor) cursor.classList.remove('grow');
                if (spotlight) spotlight.classList.remove('focus');
            }
        });

        // Click Effect
        document.addEventListener('mousedown', () => {
            if (cursor) cursor.style.transform += ' scale(0.8)';
        });
        
        document.addEventListener('mouseup', () => {
            if (cursor) cursor.style.transform += ' scale(1.1)';
        });
    };

    // 2. Staggered Entrance Animations
    const initStagger = () => {
        const elements = document.querySelectorAll('.stagger');
        elements.forEach((el, index) => {
            const delay = el.getAttribute('data-delay') || (index * 0.1);
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = `all 0.6s cubic-bezier(0.23, 1, 0.32, 1) ${delay}s`;
            
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 50);
        });
    };

    // 3. Hydration Wave Logic (Global helper)
    window.updateWaterDisplay = (count) => {
        const wave = document.getElementById('waterWave');
        const countEl = document.getElementById('waterCount');
        if (countEl) countEl.innerText = count;
        if (wave) {
            const height = Math.min(count * 12.5, 100); // 8 glasses = 100%
            wave.style.height = `${height}%`;
        }
    };

    const init = () => {
        initTracking();
        initStagger();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
