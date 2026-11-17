<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Generator</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
    <section class="hero">
        <p class="eyebrow">Package Playground</p>
        <h1>Build the code, then push the styling.</h1>
        <p class="sub">This playground now follows the order the QR engine actually cares about: payload first, then layout viability, then logo limits, then colors. The goal is to stop you from wandering into unreadable combinations before you hit generate.</p>
    </section>

    <section class="layout">
        <div class="panel">
            <?php if (!$autoloadReady): ?>
                <p class="status error">Dependencies are not installed yet. Run <code>composer install</code> in <code>/Users/gce/Sites/qrgenerator.test</code> and refresh this page.</p>
            <?php endif; ?>

            <?php if ($errorMessage !== null): ?>
                <p class="status error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post" id="qr-form">
                <input type="hidden" name="active_tab" id="active_tab" value="<?= htmlspecialchars($form['active_tab'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="tablist" role="tablist" aria-label="QR configuration steps">
                    <button class="tab <?= $form['active_tab'] === 'content' ? 'active' : '' ?>" type="button" data-tab="content">
                        Content
                        <span>Payload and destination</span>
                    </button>
                    <button class="tab <?= $form['active_tab'] === 'layout' ? 'active' : '' ?>" type="button" data-tab="layout">
                        Layout
                        <span>Size, quiet zone, readability</span>
                    </button>
                    <button class="tab <?= $form['active_tab'] === 'logo' ? 'active' : '' ?>" type="button" data-tab="logo">
                        Logo
                        <span>Measured against validation</span>
                    </button>
                    <button class="tab <?= $form['active_tab'] === 'colors' ? 'active' : '' ?>" type="button" data-tab="colors">
                        Colors
                        <span>Foreground and background</span>
                    </button>
                    <button class="tab <?= $form['active_tab'] === 'style' ? 'active' : '' ?>" type="button" data-tab="style">
                        Style
                        <span>Module and eye shapes</span>
                    </button>
                </div>

                <section class="tab-panel <?= $form['active_tab'] === 'content' ? 'active' : '' ?>" data-panel="content">
                    <label>
                        QR data
                        <textarea name="data" id="data"><?= htmlspecialchars($form['data'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <p class="hint">The payload determines the QR version and module count, so everything else downstream depends on this field.</p>
                </section>

                <section class="tab-panel <?= $form['active_tab'] === 'layout' ? 'active' : '' ?>" data-panel="layout">
                    <div class="grid">
                        <label>
                            Error correction
                            <select name="error_correction" id="error_correction">
                                <option value="low" <?= $form['error_correction'] === 'low' ? 'selected' : '' ?>>Low (7%)</option>
                                <option value="medium" <?= $form['error_correction'] === 'medium' ? 'selected' : '' ?>>Medium (15%)</option>
                                <option value="quartile" <?= $form['error_correction'] === 'quartile' ? 'selected' : '' ?>>Quartile (25%)</option>
                                <option value="high" <?= $form['error_correction'] === 'high' ? 'selected' : '' ?>>High (30%)</option>
                            </select>
                        </label>
                        <label>
                            Size
                            <input type="number" name="size" id="size" min="50" max="2400" value="<?= htmlspecialchars($form['size'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Margin
                            <input type="number" name="margin" id="margin" min="0" max="200" value="<?= htmlspecialchars($form['margin'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                    <p class="hint">Error correction adds redundancy so a damaged code can still scan, but higher levels can make codes harder for some readers to decode. Size controls density; margin adds a quiet zone outside the QR body.</p>
                </section>

                <section class="tab-panel <?= $form['active_tab'] === 'logo' ? 'active' : '' ?>" data-panel="logo">
                    <label>
                        Logo path on disk
                        <input type="text" name="logo_path" id="logo_path" placeholder="/absolute/path/to/logo.png" value="<?= htmlspecialchars($form['logo_path'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <div class="grid">
                        <label>
                            Logo width
                            <input type="number" name="logo_width" id="logo_width" min="1" max="400" value="<?= htmlspecialchars($form['logo_width'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Punchout
                            <input type="text" value="<?= $form['logo_punchout'] === '1' ? 'Enabled' : 'Disabled' ?>" readonly>
                        </label>
                    </div>
                    <div class="grid">
                        <label>
                            Logo X
                            <input type="number" name="logo_x" id="logo_x" min="0" step="1" placeholder="Center" value="<?= htmlspecialchars($form['logo_x'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Logo Y
                            <input type="number" name="logo_y" id="logo_y" min="0" step="1" placeholder="Center" value="<?= htmlspecialchars($form['logo_y'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                    <label class="pill">
                        <input type="checkbox" name="logo_punchout" value="1" <?= $form['logo_punchout'] === '1' ? 'checked' : '' ?>>
                        Punch out logo background
                    </label>
                    <p class="hint">Leave X and Y blank to keep the logo centered. When set, the styled renderer clamps the logo away from the three finder eyes with 10 px of padding.</p>
                </section>

                <section class="tab-panel <?= $form['active_tab'] === 'colors' ? 'active' : '' ?>" data-panel="colors">
                    <div class="grid">
                        <label>
                            Foreground
                            <input type="color" name="foreground" id="foreground" value="<?= htmlspecialchars($form['foreground'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Background
                            <input type="color" name="background" id="background" value="<?= htmlspecialchars($form['background'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                    <div class="grid">
                        <label>
                            Eye outer color
                            <input type="text" name="eye_outer" id="eye_outer" placeholder="#000000" value="<?= htmlspecialchars($form['eye_outer'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Eye inner color
                            <input type="text" name="eye_inner" id="eye_inner" placeholder="#000000" value="<?= htmlspecialchars($form['eye_inner'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                    <div class="eye-color-grid">
                        <fieldset class="eye-color-fieldset">
                            <legend>Top-left eye override</legend>
                            <div class="grid">
                                <label>
                                    Outer
                                    <input type="text" name="top_left_eye_outer" id="top_left_eye_outer" placeholder="Use global eye outer" value="<?= htmlspecialchars($form['top_left_eye_outer'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                                <label>
                                    Inner
                                    <input type="text" name="top_left_eye_inner" id="top_left_eye_inner" placeholder="Use global eye inner" value="<?= htmlspecialchars($form['top_left_eye_inner'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="eye-color-fieldset">
                            <legend>Top-right eye override</legend>
                            <div class="grid">
                                <label>
                                    Outer
                                    <input type="text" name="top_right_eye_outer" id="top_right_eye_outer" placeholder="Use global eye outer" value="<?= htmlspecialchars($form['top_right_eye_outer'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                                <label>
                                    Inner
                                    <input type="text" name="top_right_eye_inner" id="top_right_eye_inner" placeholder="Use global eye inner" value="<?= htmlspecialchars($form['top_right_eye_inner'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="eye-color-fieldset">
                            <legend>Bottom-left eye override</legend>
                            <div class="grid">
                                <label>
                                    Outer
                                    <input type="text" name="bottom_left_eye_outer" id="bottom_left_eye_outer" placeholder="Use global eye outer" value="<?= htmlspecialchars($form['bottom_left_eye_outer'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                                <label>
                                    Inner
                                    <input type="text" name="bottom_left_eye_inner" id="bottom_left_eye_inner" placeholder="Use global eye inner" value="<?= htmlspecialchars($form['bottom_left_eye_inner'], ENT_QUOTES, 'UTF-8') ?>">
                                </label>
                            </div>
                        </fieldset>
                    </div>
                    <p class="hint">Leave eye color fields blank to inherit the main QR foreground color. Any eye-specific color override switches the preview to the styled SVG renderer.</p>
                </section>

                <section class="tab-panel <?= $form['active_tab'] === 'style' ? 'active' : '' ?>" data-panel="style">
                    <div class="grid">
                        <label>
                            Module shape
                            <select name="module_shape" id="module_shape">
                                <option value="square" <?= $form['module_shape'] === 'square' ? 'selected' : '' ?>>Square</option>
                                <option value="dots" <?= $form['module_shape'] === 'dots' ? 'selected' : '' ?>>Dots</option>
                                <option value="round" <?= $form['module_shape'] === 'round' ? 'selected' : '' ?>>Rounded</option>
                            </select>
                        </label>
                        <label>
                            Eye shape
                            <select name="eye_shape" id="eye_shape">
                                <option value="square" <?= $form['eye_shape'] === 'square' ? 'selected' : '' ?>>Square</option>
                                <option value="pointy" <?= $form['eye_shape'] === 'pointy' ? 'selected' : '' ?>>Pointy</option>
                                <option value="circle" <?= $form['eye_shape'] === 'circle' ? 'selected' : '' ?>>Circle</option>
                            </select>
                        </label>
                    </div>
                    <p class="hint">Non-square styling switches the preview to the Bacon-backed SVG renderer so the shapes are visible.</p>
                </section>

                <div class="actions">
                    <p class="meta">Package entry points stay the same for now: <code>QrCodeGenerator::generate()</code> and <code>QrCodeOptions</code>.</p>
                    <div class="action-buttons">
                        <button class="primary" type="submit">Generate QR Code</button>
                        <?php if ($downloadFiles !== null): ?>
                            <button class="secondary" type="button" id="save-qr" data-svg="<?= htmlspecialchars($downloadFiles['svg'], ENT_QUOTES, 'UTF-8') ?>">Save Generated QR Code</button>
                        <?php endif; ?>
                    </div>
                    <p class="meta" id="save-status" role="status" aria-live="polite" hidden></p>
                </div>
            </form>
        </div>

        <div class="preview-wrap">
            <div class="panel preview">
                <?php if ($pngDataUri !== null): ?>
                    <img src="<?= $pngDataUri ?>" alt="Generated QR code preview">
                <?php else: ?>
                    <p class="hint">Generate a code to preview it here.</p>
                <?php endif; ?>
            </div>

            <div class="panel analysis-band">
                <div class="analysis">
                    <p class="status <?= $analysis !== null && ($analysis['ready'] ?? false) ? 'ok' : '' ?>" id="analysis-message">
                        <?= htmlspecialchars($analysis['message'] ?? 'Analysis will appear here as you work through the tabs.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <div class="metrics">
                        <div class="metric">
                            <div class="metric-label">QR version</div>
                            <div class="metric-value" id="metric-version"><?= htmlspecialchars((string) ($analysis['layout']['version'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Module count</div>
                            <div class="metric-value" id="metric-modules"><?= htmlspecialchars((string) ($analysis['layout']['module_count'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Pixels per module</div>
                            <div class="metric-value" id="metric-ppm"><?= htmlspecialchars((string) ($analysis['layout']['pixels_per_module'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Max passing logo width</div>
                            <div class="metric-value" id="metric-logo-max"><?= htmlspecialchars(isset($analysis['logo']['maximum_width']) && $analysis['logo']['maximum_width'] !== null ? (string) $analysis['logo']['maximum_width'] . ' px' : '—', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="status <?= !empty($analysis['layout']['current_valid']) ? 'ok' : 'error' ?>" id="layout-status">
                        <?= htmlspecialchars($analysis['layout']['message'] ?? 'Layout analysis is waiting for payload data.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="hint" id="layout-constraints">
                        <?php if (isset($analysis['layout']['minimum_size'])): ?>
                            Minimum size: <?= htmlspecialchars((string) $analysis['layout']['minimum_size'], ENT_QUOTES, 'UTF-8') ?> px. <?= htmlspecialchars((string) ($analysis['layout']['margin_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            Structural limits will appear here.
                        <?php endif; ?>
                    </p>
                </div>

                <div>
                    <p class="status <?= !empty($analysis['logo']['current_valid']) ? 'ok' : '' ?>" id="logo-status">
                        <?= htmlspecialchars($analysis['logo']['message'] ?? 'Logo analysis will appear once a local file path is present.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php if ($autoloadReady): ?>
<script src="app.js"></script>
<?php endif; ?>
</body>
</html>
