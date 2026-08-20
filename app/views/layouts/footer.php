            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; 2026 <a href="#">JAGAPADI</a>.</strong>
        Dikembangkan oleh Tim PLS | BPS Kabupaten Jember
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 1.0.0
        </div>
    </footer>
</div>

<!-- jQuery (dimuat di header.php) -->
<!-- Bootstrap 4 -->
<script src="<?= BASE_URL ?>public/vendor/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="<?= BASE_URL ?>public/vendor/js/adminlte.min.js"></script>
<!-- Sinkronisasi state parent/submenu sidebar -->
<script src="<?= BASE_URL ?>public/js/sidebar-state.js"></script>
<!-- Chart.js dimuat di header.php (v4.4.0) — jangan dimuat duplikat -->
<!-- Custom JavaScript -->
<script src="<?= BASE_URL ?>public/js/validation.js"></script>
<script src="<?= BASE_URL ?>public/js/loading.js"></script>
<script src="<?= BASE_URL ?>public/js/confirm-dialog.js"></script>
<!-- Mobile Enhancements -->
<script src="<?= BASE_URL ?>public/js/mobile-enhancements.js"></script>

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= BASE_URL ?>public/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
                
                // Check for updates
                registration.addEventListener('updatefound', function() {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New version available
                            if (confirm('Versi baru JAGAPADI tersedia. Muat ulang untuk menggunakan versi terbaru?')) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed: ', error);
            });
    });
    
    // Handle service worker updates
    navigator.serviceWorker.addEventListener('controllerchange', function() {
        window.location.reload();
    });
}
</script>

</body>
</html>
