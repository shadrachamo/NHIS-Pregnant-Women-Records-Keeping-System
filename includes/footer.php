    </div><!-- /.nhis-content -->
</div><!-- /.nhis-wrapper -->

<!-- ===== FOOTER ===== -->
<footer class="nhis-footer">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span class="text-muted small">
                    &copy; <?= date('Y') ?>
                    <?= getSetting('office_name', 'NHIS Twifo Praso District Office') ?>
                    &mdash; Pregnancy Exemption Registration System v<?= APP_VERSION ?>
                </span>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted small">
                    <em>Prototype &ndash; Internal Records Management Solution</em>
                </span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<?php if (isset($extraJs)): ?>
<script>
<?= $extraJs ?>
</script>
<?php endif; ?>

</body>
</html>
