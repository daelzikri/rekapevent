/**
 * assets/js/activity-tracker.js
 * Tracker Aktivitas Pengguna, Ping Heartbeat, Idle Timeout 30 Menit, & Beacon Logout
 */

(function () {
    const IDLE_LIMIT_MS = 30 * 60 * 1000;       // 30 Menit = 1.800.000 ms
    const WARNING_TIME_MS = 29 * 60 * 1000;     // 29 Menit (Peringatan 1 menit sebelum)
    const PING_INTERVAL_MS = 2.5 * 60 * 1000;   // Ping tiap 2.5 menit jika ada aktivitas

    let lastActivityTime = Date.now();
    let hasActivitySinceLastPing = false;
    let warningModalInjected = false;
    let warningModalElement = null;

    // List event pendeteksi aktivitas manusia
    const activityEvents = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'];

    function recordActivity() {
        lastActivityTime = Date.now();
        hasActivitySinceLastPing = true;
        hideWarningModal();
    }

    activityEvents.forEach(evt => {
        window.addEventListener(evt, recordActivity, { passive: true });
    });

    // 1. Heartbeat Ping Loop (Kirim ke /auth/ping.php jika ada aktivitas)
    setInterval(() => {
        if (hasActivitySinceLastPing) {
            fetch('/auth/ping.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    hasActivitySinceLastPing = false;
                }
            })
            .catch(err => console.warn('Heartbeat ping failed:', err));
        }
    }, PING_INTERVAL_MS);

    // 2. Idle Timer Check Loop (Jalankan tiap detik untuk akurasi UI modal)
    setInterval(() => {
        const elapsed = Date.now() - lastActivityTime;

        if (elapsed >= IDLE_LIMIT_MS) {
            // Sesi habis -> Redirect logout
            window.location.href = '/auth/logout.php?error=Sesi+telah+berakhir+karena+30+menit+inaktivitas.';
        } else if (elapsed >= WARNING_TIME_MS) {
            // Sisa 1 menit -> Tampilkan Modal Peringatan
            const sisaDetik = Math.max(0, Math.ceil((IDLE_LIMIT_MS - elapsed) / 1000));
            showWarningModal(sisaDetik);
        } else {
            hideWarningModal();
        }
    }, 1000);

    // 3. Modal UI Generator
    function createModalElement() {
        const modal = document.createElement('div');
        modal.id = 'idle-warning-modal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md hidden transition-all duration-300';
        modal.innerHTML = `
            <div class="bg-slate-900 border border-amber-500/40 rounded-2xl p-6 max-w-md w-full shadow-2xl text-center space-y-4">
                <div class="w-12 h-12 rounded-full bg-amber-500/20 text-amber-400 mx-auto flex items-center justify-center border border-amber-500/30">
                    <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white">Sesi Akan Berakhir</h3>
                <p class="text-sm text-slate-300">
                    Anda tidak melakukan aktivitas selama 29 menit. Sesi Anda akan otomatis diakhiri dalam <strong id="idle-countdown-timer" class="text-amber-400 font-extrabold text-base">60</strong> detik.
                </p>
                <div class="pt-2">
                    <button id="idle-extend-session-btn" type="button" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        Lanjutkan Sesi Saya
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('idle-extend-session-btn').addEventListener('click', () => {
            recordActivity();
            // Kirim ping langsung
            fetch('/auth/ping.php', { method: 'POST' });
        });

        return modal;
    }

    function showWarningModal(sisaDetik) {
        if (!warningModalInjected) {
            warningModalElement = createModalElement();
            warningModalInjected = true;
        }
        warningModalElement.classList.remove('hidden');
        const timerSpan = document.getElementById('idle-countdown-timer');
        if (timerSpan) timerSpan.textContent = sisaDetik;
    }

    function hideWarningModal() {
        if (warningModalElement && !warningModalElement.classList.contains('hidden')) {
            warningModalElement.classList.add('hidden');
        }
    }

    // 4. SendBeacon saat browser / tab ditutup
    window.addEventListener('beforeunload', () => {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/auth/logout.php');
        }
    });

})();
