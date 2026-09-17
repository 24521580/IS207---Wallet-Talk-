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
