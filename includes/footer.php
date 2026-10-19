</div> <!-- Container Kapanışı -->

<footer class="bg-dark text-white text-center py-3 mt-auto">
    <div class="container">
        <p class="mb-0 small">&copy; <?= date('Y') ?> Webtoon Tracker - Tüm Hakları Saklıdır.</p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dark Mode Yönetim JavaScript Kodu -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;

        // İkonu temaya göre güncelle
        function updateIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-stars-fill');
            } else {
                themeIcon.classList.remove('bi-moon-stars-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        }

        // Başlangıç durumunu saklanan temaya göre yükle
        const currentTheme = localStorage.getItem('theme') || 'light';
        updateIcon(currentTheme);

        // Temayı değiştirme düğmesine tıklandığında çalışır
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                let activeTheme = htmlElement.getAttribute('data-bs-theme');
                let newTheme = activeTheme === 'dark' ? 'light' : 'dark';

                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateIcon(newTheme);
            });
        }
    });
</script>

</body>
</html>