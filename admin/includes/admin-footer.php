<?php
/**
 * admin/includes/admin-footer.php — Admin Panel Footer + Scripts
 * admin-header.php খোলা tags বন্ধ করে।
 * Usage: প্রতিটি admin page-এর শেষে include।
 */
?>
    </main><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Toast container (already inside admin-main but keep safety) -->
<div class="toasts" id="toasts" aria-live="polite" aria-atomic="true"></div>

<!-- Config for JS -->
<script>
    window.WCB_CONFIG = {
        apiUrl:    "<?= e(base_url('api')) ?>",
        adminUrl:  "<?= e(admin_url()) ?>",
        baseUrl:   "<?= e(base_url()) ?>",
        lang:      "en",
        currency:  "<?= e(setting('default_currency', 'BDT')) ?>",
        isAdmin:   true,
        adminRole: "<?= e($currentAdmin['role'] ?? 'manager') ?>"
    };
</script>

<!-- Core scripts -->
<script src="<?= e(base_url('js/api.js')) ?>"></script>
<script src="<?= e(base_url('js/main.js')) ?>"></script>
<script src="<?= e(admin_url('assets/admin.js')) ?>"></script>

<?php if (!empty($admin_extra_js)): foreach ((array) $admin_extra_js as $js): ?>
    <script src="<?= e(admin_url($js)) ?>"></script>
<?php endforeach; endif; ?>

</body>
</html>