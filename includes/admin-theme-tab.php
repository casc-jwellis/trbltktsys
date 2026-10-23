<?php
$themeSettings = theme_settings();
$configuredName = configured_app_name();
?>

<?php if (!theme_settings_supported()): ?>
    <div class="alert alert-warning">The database is out of date — theme settings are unavailable until an administrator runs the pending updates on the <a href="admin-settings.php?tab=database">Database tab</a>.</div>
<?php else: ?>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_theme_settings">

    <div class="card mb-4">
        <div class="card-header">Site Name</div>
        <div class="card-body p-4">
            <label class="form-label" for="site_name">Name shown in the navigation bar, page titles, footer, and emails</label>
            <input class="form-control" id="site_name" name="site_name" maxlength="100"
                   placeholder="<?= e($configuredName) ?>" value="<?= e($themeSettings['site_name'] ?? '') ?>">
            <div class="form-text">Leave blank to use the name set during installation (<?= e($configuredName) ?>).</div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Theme Color</div>
        <div class="card-body p-4">
            <p class="text-body-secondary">Used for buttons, the logo mark, and the browser tab icon. Pick one to preview it on this page right away, then save to apply it for everyone. Each color has a matching brighter shade for dark mode.</p>
            <div class="theme-options" id="themePicker">
                <?php foreach (THEME_PRESETS as $key => $preset): ?>
                    <div>
                        <input type="radio" class="btn-check" name="color" id="theme_<?= e($key) ?>" value="<?= e($key) ?>"
                               data-light-brand="<?= e($preset['light'][0]) ?>" data-light-hover="<?= e($preset['light'][1]) ?>"
                               data-dark-brand="<?= e($preset['dark'][0]) ?>" data-dark-hover="<?= e($preset['dark'][1]) ?>"
                               <?= $themeSettings['color'] === $key ? 'checked' : '' ?>>
                        <label class="theme-option" for="theme_<?= e($key) ?>">
                            <span class="theme-swatch" style="background: <?= e($preset['light'][0]) ?>;"></span>
                            <span class="theme-swatch" style="background: <?= e($preset['dark'][0]) ?>;"></span>
                            <span class="theme-option-label"><?= e($preset['label']) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text mt-2">The two dots show the light-mode and dark-mode shades.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Theme Settings</button>
</form>

<?php endif; ?>
