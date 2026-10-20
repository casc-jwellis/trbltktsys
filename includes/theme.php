<?php

// Site name and theme color, set in Admin Settings -> Theme. Stored in a
// single-row table (migration 022), read on every page through
// theme_settings(), and applied by overriding the --brand CSS tokens that
// assets/css/style.css defines (see theme_css()).

const THEME_DEFAULT = 'institution';

/**
 * The selectable colors. Each carries its own light- and dark-mode pair
 * because the dark theme needs a brighter shade to stay legible on a dark
 * background; [brand, hover] where hover is darker in light mode and lighter
 * in dark mode, matching how the buttons already behave.
 */
const THEME_PRESETS = [
    'institution' => ['label' => 'Institution Blue', 'light' => ['#004E81', '#003a61'], 'dark' => ['#3d94d1', '#62aee0']],
    'indigo'      => ['label' => 'Indigo',           'light' => ['#4f46e5', '#4338ca'], 'dark' => ['#6366f1', '#818cf8']],
    'teal'        => ['label' => 'Teal',             'light' => ['#0f766e', '#115e59'], 'dark' => ['#0d9488', '#14b8a6']],
    'green'       => ['label' => 'Green',            'light' => ['#15803d', '#166534'], 'dark' => ['#16a34a', '#22c55e']],
    'burgundy'    => ['label' => 'Burgundy',         'light' => ['#9f1239', '#881337'], 'dark' => ['#e11d48', '#fb7185']],
    'orange'      => ['label' => 'Orange',           'light' => ['#c2410c', '#9a3412'], 'dark' => ['#ea580c', '#f97316']],
    'purple'      => ['label' => 'Purple',           'light' => ['#7e22ce', '#6b21a8'], 'dark' => ['#9333ea', '#a855f7']],
    'slate'       => ['label' => 'Slate',            'light' => ['#334155', '#1e293b'], 'dark' => ['#64748b', '#94a3b8']],
];

/** Whether the theme_settings table exists yet (migration 022). */
function theme_settings_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('theme_settings');
    }
    return $result;
}

/**
 * The saved theme as ['site_name' => ?string, 'color' => string]. Falls back
 * to the defaults -- never throws -- when there's no config yet (the
 * installer), the table doesn't exist yet (pending migration), or the
 * database is unreachable: this runs on every page, including the ones that
 * have to work before any of that is in place.
 */
function theme_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = ['site_name' => null, 'color' => THEME_DEFAULT];
    if (!file_exists(__DIR__ . '/../config/config.php')) {
        return $settings;
    }

    try {
        $row = db()->query('SELECT site_name, color FROM theme_settings WHERE id = 1')->fetch();
    } catch (Throwable $e) {
        return $settings;
    }

    if ($row) {
        $name = trim((string) ($row['site_name'] ?? ''));
        $settings['site_name'] = $name !== '' ? $name : null;
        if (isset(THEME_PRESETS[$row['color']])) {
            $settings['color'] = $row['color'];
        }
    }
    return $settings;
}

/** Saves the site name (blank = use the configured default) and color. */
function save_theme_settings(string $siteName, string $color): void
{
    $stmt = db()->prepare(
        'INSERT INTO theme_settings (id, site_name, color) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE site_name = VALUES(site_name), color = VALUES(color)'
    );
    $stmt->execute([$siteName !== '' ? $siteName : null, $color]);
}

/**
 * The CSS that applies a preset: the light tokens on :root and the dark
 * tokens under [data-bs-theme="dark"], same selectors as style.css so it
 * simply overrides them. Only ever built from THEME_PRESETS, never from
 * user input.
 */
function theme_css(string $key): string
{
    $preset = THEME_PRESETS[$key] ?? THEME_PRESETS[THEME_DEFAULT];
    return ':root{--brand:' . $preset['light'][0] . ';--brand-hover:' . $preset['light'][1] . ';}'
        . '[data-bs-theme="dark"]{--brand:' . $preset['dark'][0] . ';--brand-hover:' . $preset['dark'][1] . ';}';
}

/** The browser-tab icon: the navbar's "TT" brand mark in the chosen color. */
function theme_favicon_svg(string $key): string
{
    $preset = THEME_PRESETS[$key] ?? THEME_PRESETS[THEME_DEFAULT];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        . '<rect width="32" height="32" rx="9.6" fill="' . $preset['light'][0] . '"/>'
        . '<text x="16" y="21" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="13" font-weight="700" fill="#ffffff">TT</text>'
        . '</svg>';
}
