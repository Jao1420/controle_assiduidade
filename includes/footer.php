</div><!-- /.main-content -->

<footer class="app-footer">
    <div class="text-center">
        <span class="text-muted small">
            <i class="bi bi-calendar-check me-1"></i>
            Controle de Absenteísmo &copy; <?= date('Y') ?>
        </span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ($pageScripts as $src): ?>
        <script src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
