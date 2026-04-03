// scripts/user-realtime.js
// NutriDeq User Dashboard Controller - Real-time Macros and Hydration Flow
(function () {
    'use strict';

    const POLL_INTERVAL = 10000; // 10 seconds

    function init() {
        console.log('User Smart Dashboard Online');
        updateDashboardData();
        fetchWater(); // Initial water fetch
        setInterval(updateDashboardData, POLL_INTERVAL);
    }

    // Hydration Tracker Logic
    function fetchWater() {
        fetch('api/water_tracker.php?action=get')
            .then(res => res.json())
            .then(data => {
                if (data.success) renderWater(data.glasses, data.target);
            });
    }

    window.updateWater = function (action) {
        // Optimistic UI for snappy feel
        const countEl = document.getElementById('waterCount');
        let current = parseInt(countEl.innerText);
        if (action === 'add') current++;
        else current = Math.max(0, current - 1);
        renderWater(current, 8);

        fetch(`api/water_tracker.php?action=${action}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) renderWater(data.glasses, data.target);
            });
    };

    function renderWater(count, target) {
        const countEl = document.getElementById('waterCount');
        const waveEl = document.getElementById('waterWave');
        if (!countEl || !waveEl) return;

        countEl.innerText = count;

        // Calculate percentage (max 100%)
        const pct = Math.min(100, (count / target) * 100);
        waveEl.style.height = `${pct}%`;

        // Dynamic Glow on full
        if (pct >= 100) {
            countEl.style.textShadow = '0 0 15px rgba(79, 172, 254, 0.6)';
        } else {
            countEl.style.textShadow = 'none';
        }
    }

    function updateDashboardData() {
        fetch('api/user_progress_data.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateRings(data.macros);
                }
            })
            .catch(err => console.error('Dashboard polling error:', err));
    }

    function updateRings(macros) {
        updateRing('p_bar', 'p_val', macros.protein);
        updateRing('c_bar', 'c_val', macros.carbs);
        updateRing('f_bar', 'f_val', macros.fats);
    }

    function updateRing(id, val_id, data) {
        const bar = document.getElementById(id);
        const label = document.getElementById(val_id);
        if (!bar || !label) return;

        // SVG circumference for r=34 is 2*PI*34 ~= 213.6
        const circumference = 213.6;
        const offset = circumference - (data.pct / 100) * circumference;
        bar.style.strokeDashoffset = offset;

        label.innerText = `${Math.round(data.current)} / ${data.target}g`;
    }

    // PDF Report Generator (Standardized Tool)
    window.generateClinicalReport = function (targetSelector = '.main-content', filename = 'NutriDeq-Clinical-Report.pdf') {
        const { jsPDF } = window.jspdf;
        const element = document.querySelector(targetSelector);
        if (!element) return;

        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

        html2canvas(document.querySelector('.page-container')).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save(filename);
            btn.innerHTML = originalContent;
        });
    };

    document.addEventListener('DOMContentLoaded', init);
})();
