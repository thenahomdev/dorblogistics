<?php if (!empty($_SESSION['errors'])){ ?>

    <div class="alert alert-error">
        <ul>
            <?php foreach ($_SESSION['errors'] as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php unset($_SESSION['errors']); ?>

<?php } ?>

<?php if (isset($_SESSION['success'])){ ?>
    <div class="alert alert-success">
        <?php echo ($_SESSION['success']); ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php } ?>

<script>
    document.addEventListener("DOMContentLoaded", function()
    {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert =>
        {
            setTimeout(() => {
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 600);
            }, 4000);
        });
    });
</script>
