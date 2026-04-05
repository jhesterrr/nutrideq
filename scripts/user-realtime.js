// scripts/user-realtime.js
// Real-time synchronization for the Client Dashboard
(function() {
    'use strict';
    const POLL_INTERVAL = 30000; // 30 seconds

    function init() {
        console.log('Client Real-time Engine Started');
        updateUserStats();
        setInterval(updateUserStats, POLL_INTERVAL);
    }

    function updateUserStats() {
        fetch('api/dashboard_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.role === 'regular') {
                    updateMacros(data.macros);
                    updateWater(data.water);
                    updateUnread(data.unread_messages);
                }
            })
            .catch(err => console.error('User sync error:', err));
    }

    function updateMacros(macros) {
        if (!macros) return;
        
        // Update values
        document.getElementById('p_val').innerText = `${macros.protein}g`;
        document.getElementById('c_val').innerText = `${macros.carbs}g`;
        document.getElementById('f_val').innerText = `${macros.fats}g`;

        // Update rings (213.6 is full circumference)
        const updateRing = (id, val, target) => {
            const bar = document.getElementById(id);
            if (!bar) return;
            const pct = Math.min((val / (target || 100)), 1);
            const offset = 213.6 - (213.6 * pct);
            bar.style.strokeDashoffset = offset;
        };

        // Static targets for demo
        updateRing('p_bar', macros.protein, 150);
        updateRing('c_bar', macros.carbs, 250);
        updateRing('f_bar', macros.fats, 70);
    }

    function updateWater(glasses) {
        const count = document.getElementById('waterCount');
        const wave = document.getElementById('waterWave');
        if (count) count.innerText = glasses;
        if (wave) wave.style.height = `${Math.min(glasses * 12.5, 100)}%`;
    }

    function updateUnread(count) {
        // Find the "Messages" card and update unread count if it exists
        const quickActions = document.querySelectorAll('.command-tile-info p');
        quickActions.forEach(p => {
            if (p.innerText.includes('unread')) {
                p.innerText = `${count} unread`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
