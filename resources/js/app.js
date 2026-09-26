document.getElementById('nav-toggle')?.addEventListener('click', () => {
    document.getElementById('mobile-nav')?.classList.toggle('hidden');
});

// Toast thành công tự ẩn sau 4 giây; toast lỗi giữ lại cho tới khi người dùng đóng.
document.querySelectorAll('[data-toast="success"]').forEach((toast) => {
    setTimeout(() => {
        toast.classList.add('opacity-0', '-translate-y-1');
        setTimeout(() => toast.remove(), 320);
    }, 4000);
});

document.querySelectorAll('[data-toast-close]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('[data-toast]')?.remove();
    });
});

// ── Dark Mode ────────────────────────────────────────────────────────────────

(function initDarkMode() {
    const html        = document.documentElement;
    const STORAGE_KEY = 'vinoi-theme';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function isDark() {
        return html.classList.contains('dark');
    }

    /** Áp dụng theme lên <html> và cập nhật tất cả toggle buttons */
    function applyTheme(dark) {
        html.classList.toggle('dark', dark);

        const tooltip   = dark ? 'Chuyển sang chế độ sáng' : 'Chuyển sang chế độ tối';

        // Desktop toggle
        const btnDesktop     = document.getElementById('theme-toggle');
        const moonDesktop    = document.getElementById('theme-icon-moon');
        const sunDesktop     = document.getElementById('theme-icon-sun');
        if (btnDesktop) {
            btnDesktop.title = tooltip;
            btnDesktop.setAttribute('aria-label', tooltip);
            moonDesktop?.classList.toggle('hidden', dark);
            sunDesktop?.classList.toggle('hidden', !dark);
        }

        // Mobile drawer toggle
        const btnMobile  = document.getElementById('theme-toggle-mobile');
        const moonMobile = document.getElementById('theme-icon-moon-mobile');
        const sunMobile  = document.getElementById('theme-icon-sun-mobile');
        const labelMobile = document.getElementById('theme-toggle-mobile-label');
        if (btnMobile) {
            moonMobile?.classList.toggle('hidden', dark);
            sunMobile?.classList.toggle('hidden', !dark);
            if (labelMobile) labelMobile.textContent = tooltip;
        }
    }

    /** Lưu và áp dụng theme mới */
    function setTheme(dark) {
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch (_) { /* private browsing */ }
        applyTheme(dark);
    }

    // ── Init: đọc localStorage, fallback về system preference ────────────────

    let saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch (_) {}

    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    const startDark   = saved === 'dark' || (saved === null && prefersDark);

    // Apply class lên <html> ngay lập tức để tránh flash of unstyled content
    html.classList.toggle('dark', startDark);

    // Sync icons + tooltips sau khi DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        applyTheme(startDark);
    });

    // ── Click handlers ────────────────────────────────────────────────────────

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        setTheme(!isDark());
    });

    document.getElementById('theme-toggle-mobile')?.addEventListener('click', () => {
        setTheme(!isDark());
    });

    // ── Theo dõi system preference thay đổi (chỉ khi user chưa chọn thủ công) ──

    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        let pref = null;
        try { pref = localStorage.getItem(STORAGE_KEY); } catch (_) {}
        if (pref === null) applyTheme(e.matches); // chưa có lựa chọn thủ công → theo system
    });
})();
