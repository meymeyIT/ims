{{-- Applies the saved light/dark theme BEFORE the page is drawn (prevents a white flash).
     Must stay inline and above the stylesheets. The toggle itself is resources/js/theme.js. --}}
<script>
    (function () {
        var theme = 'light';
        try { theme = localStorage.getItem('ims-theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
</script>
