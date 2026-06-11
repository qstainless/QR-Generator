<?php

declare(strict_types=1);

use BaconQrCode\Common\ErrorCorrectionLevel as BaconErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Walermo\QrCode\QrCodeGenerator;
use Walermo\QrCode\QrCodeOptions;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$autoloadReady = is_file($autoloadPath);

if ($autoloadReady) {
    require $autoloadPath;
}

$form = getFormState($_POST);
$analysis = null;
$pngDataUri = null;
$errorMessage = null;

if ($autoloadReady && isset($_GET['analyze'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        echo json_encode(analyzeConfiguration($form), JSON_THROW_ON_ERROR);
    } catch (Throwable $throwable) {
        http_response_code(500);
        echo json_encode([
            'ready' => false,
            'message' => $throwable->getMessage(),
        ], JSON_THROW_ON_ERROR);
    }

    exit;
}

if ($autoloadReady) {
    $analysis = analyzeConfiguration($form);
}

if ($autoloadReady && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['analyze'])) {
    try {
        $result = (new QrCodeGenerator())->generate($form['data'], createOptionsFromForm($form));
        $pngDataUri = 'data:image/png;base64,' . base64_encode($result->getString());
    } catch (Throwable $throwable) {
        $errorMessage = $throwable->getMessage();
    }
}

function getFormState(array $source): array
{
    return [
        'data' => $source['data'] ?? 'https://example.com',
        'size' => (string) ($source['size'] ?? '300'),
        'margin' => (string) ($source['margin'] ?? '10'),
        'error_correction' => 'high',
        'foreground' => $source['foreground'] ?? '#000000',
        'background' => $source['background'] ?? '#ffffff',
        'logo_path' => $source['logo_path'] ?? '',
        'logo_width' => (string) ($source['logo_width'] ?? '70'),
        'logo_punchout' => isset($source['logo_punchout']) ? '1' : '',
        'active_tab' => $source['active_tab'] ?? 'content',
    ];
}

function analyzeConfiguration(array $form): array
{
    $data = trim((string) $form['data']);
    if ($data === '') {
        return [
            'ready' => false,
            'message' => 'Enter QR data to begin the layout analysis.',
        ];
    }

    $size = max(50, (int) $form['size']);
    $margin = max(0, (int) $form['margin']);
    $logoPath = trim((string) $form['logo_path']);
    $logoWidth = max(1, (int) $form['logo_width']);

    $matrixData = getMatrixData($data, $size, $margin);
    $minimumSize = findMinimumSize($data, $form, $margin);
    $currentLayoutValid = isLayoutValid($data, $form, $size, $margin);

    $layoutMessage = $currentLayoutValid
        ? 'This payload validates at the current size and margin.'
        : 'Increase the size or reduce the margin until the validator can read the code reliably.';

    $logo = [
        'path_ready' => $logoPath !== '' && is_file($logoPath),
        'current_valid' => false,
        'maximum_width' => null,
        'message' => 'Add a local logo path after the layout is stable to calculate a passing maximum width.',
    ];

    if ($logoPath !== '') {
        if (!is_file($logoPath)) {
            $logo['message'] = 'The logo path does not point to a readable local file.';
        } elseif (!$currentLayoutValid) {
            $logo['message'] = 'Fix the layout tab first so the base QR code validates before adding a logo.';
        } else {
            $maximumLogoWidth = findMaximumLogoWidth($data, $form, $size, $margin);
            $currentLogoValid = isLogoValid($data, $form, $logoWidth);

            $logo['current_valid'] = $currentLogoValid;
            $logo['maximum_width'] = $maximumLogoWidth;
            $logo['message'] = $maximumLogoWidth === null
                ? 'A passing logo width could not be determined for the current settings.'
                : sprintf(
                    'The largest passing center logo width for the current payload is %d px.',
                    $maximumLogoWidth
                );
        }
    }

    return [
        'ready' => true,
        'message' => 'Analysis updated.',
        'layout' => [
            'current_valid' => $currentLayoutValid,
            'message' => $layoutMessage,
            'minimum_size' => $minimumSize,
            'margin_note' => 'Margin acts as an outer quiet zone in the current API, so size is the real density control.',
            'version' => $matrixData['version'],
            'module_count' => $matrixData['module_count'],
            'pixels_per_module' => $matrixData['pixels_per_module'],
        ],
        'logo' => $logo,
    ];
}

function getMatrixData(string $data, int $size, int $margin): array
{
    $baconQr = BaconEncoder::encode($data, BaconErrorCorrectionLevel::H(), 'UTF-8');
    $version = $baconQr->getVersion()->getVersionNumber();
    $moduleCount = $baconQr->getVersion()->getDimensionForVersion();

    return [
        'version' => $version,
        'module_count' => $moduleCount,
        'pixels_per_module' => round($size / $moduleCount, 2),
    ];
}

function createOptionsFromForm(
    array $form,
    ?int $sizeOverride = null,
    ?int $marginOverride = null,
    ?int $logoWidthOverride = null,
    bool $includeLogo = true
): QrCodeOptions {
    $options = (new QrCodeOptions())
        ->withSize($sizeOverride ?? max(1, (int) $form['size']))
        ->withMargin($marginOverride ?? max(0, (int) $form['margin']))
        ->withErrorCorrectionLevel(ErrorCorrectionLevel::High)
        ->withForegroundColor(hexToColor((string) $form['foreground']))
        ->withBackgroundColor(hexToColor((string) $form['background']));

    if (!$includeLogo) {
        return $options;
    }

    $logoPath = trim((string) $form['logo_path']);

    return $options
        ->withLogoPath($logoPath !== '' ? $logoPath : null)
        ->withLogoResizeToWidth($logoWidthOverride ?? max(1, (int) $form['logo_width']))
        ->withLogoPunchoutBackground($form['logo_punchout'] === '1');
}

function isLayoutValid(string $data, array $form, int $size, int $margin): bool
{
    try {
        (new QrCodeGenerator())->generate(
            $data,
            createOptionsFromForm($form, $size, $margin, null, false)
        );

        return true;
    } catch (Throwable) {
        return false;
    }
}

function isLogoValid(string $data, array $form, int $logoWidth): bool
{
    try {
        (new QrCodeGenerator())->generate(
            $data,
            createOptionsFromForm($form, null, null, $logoWidth, true)
        );

        return true;
    } catch (Throwable) {
        return false;
    }
}

function findMinimumSize(string $data, array $form, int $margin): ?int
{
    $low = 50;
    $high = max(120, (int) $form['size']);

    while ($high <= 2400 && !isLayoutValid($data, $form, $high, $margin)) {
        $high *= 2;
    }

    if ($high > 2400) {
        return null;
    }

    while ($low < $high) {
        $mid = intdiv($low + $high, 2);

        if (isLayoutValid($data, $form, $mid, $margin)) {
            $high = $mid;
        } else {
            $low = $mid + 1;
        }
    }

    return $low;
}

function findMaximumLogoWidth(string $data, array $form, int $size, int $margin): ?int
{
    $logoPath = trim((string) $form['logo_path']);
    if ($logoPath === '' || !is_file($logoPath)) {
        return null;
    }

    if (!isLogoValid($data, $form, 1)) {
        return 0;
    }

    $low = 1;
    $high = max(1, $size - ($margin * 2));

    while ($low < $high) {
        $mid = intdiv($low + $high + 1, 2);

        if (isLogoValid($data, $form, $mid)) {
            $low = $mid;
        } else {
            $high = $mid - 1;
        }
    }

    return $low;
}

function hexToColor(string $hex): Color
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) !== 6) {
        return new Color(0, 0, 0);
    }

    return new Color(
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Generator</title>
    <style>
:root {
    color-scheme: light;
    --bg: #f4f1ea;
    --panel: rgba(255, 253, 248, 0.94);
    --ink: #1c1917;
    --muted: #6b645c;
    --line: #d8d0c4;
    --accent: #136f63;
    --accent-strong: #0b4f47;
    --accent-soft: rgba(19, 111, 99, 0.1);
    --warning: #9f1239;
    --ok: #166534;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    background:
        radial-gradient(circle at top right, rgba(19, 111, 99, 0.12), transparent 30%),
        linear-gradient(180deg, #f8f5ef 0%, var(--bg) 100%);
    color: var(--ink);
}

main {
    max-width: 1180px;
    margin: 0 auto;
    padding: 32px 20px 48px;
}

.hero {
    margin-bottom: 24px;
}

.eyebrow {
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-size: 0.78rem;
    color: var(--accent-strong);
    margin: 0 0 10px;
}

h1 {
    font-size: clamp(2.2rem, 4vw, 4.6rem);
    line-height: 0.95;
    margin: 0;
}

.sub {
    max-width: 760px;
    color: var(--muted);
    font-size: 1.05rem;
    line-height: 1.6;
    margin-top: 12px;
}

.layout {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 24px;
}

.panel {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 18px 50px rgba(28, 25, 23, 0.08);
}

form {
    display: grid;
    gap: 18px;
}

.tablist {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 6px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.7);
}

.tab {
    border: 0;
    border-radius: 12px;
    padding: 10px 12px;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    text-align: left;
}

.tab.active {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
    color: #fff;
}

.tab span {
    display: block;
    font-size: 0.78rem;
    opacity: 0.8;
    margin-top: 4px;
}

.tab-panel {
    display: none;
    gap: 16px;
}

.tab-panel.active {
    display: grid;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

label {
    display: grid;
    gap: 8px;
    font-size: 0.95rem;
}

input,
textarea,
button {
    font: inherit;
}

input,
textarea {
    width: 100%;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 12px 14px;
    background: #fff;
    color: var(--ink);
}

textarea {
    min-height: 180px;
    resize: vertical;
}

button.primary {
    border: 0;
    border-radius: 999px;
    padding: 14px 18px;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
    color: #fff;
    cursor: pointer;
}

.hint,
.status,
.meta,
.metric-label,
.metric-value {
    line-height: 1.5;
}

.hint,
.meta,
.metric-label {
    color: var(--muted);
    font-size: 0.92rem;
}

.status {
    font-size: 0.96rem;
    margin: 0;
}

.status.error {
    color: var(--warning);
}

.status.ok {
    color: var(--ok);
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.76);
    color: var(--ink);
    font-size: 0.9rem;
}

.analysis {
    display: grid;
    gap: 16px;
}

.metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.metric {
    padding: 14px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.72);
}

.metric-value {
    font-size: 1.3rem;
    margin-top: 4px;
}

.preview-wrap {
    display: grid;
    gap: 18px;
    align-content: start;
}

.preview {
    display: grid;
    place-items: center;
    min-height: 380px;
    border: 1px dashed var(--line);
    border-radius: 18px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.84), rgba(248, 245, 239, 0.92));
    padding: 18px;
}

.preview img {
    max-width: 100%;
    height: auto;
    display: block;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.analysis-band {
    display: grid;
    gap: 14px;
}

code {
    background: rgba(19, 111, 99, 0.08);
    padding: 2px 6px;
    border-radius: 6px;
}

@media (max-width: 960px) {
    .layout,
    .grid,
    .metrics,
    .tablist {
        grid-template-columns: 1fr;
    }

    .actions {
        align-items: stretch;
    }
}

</style>
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
                </div>

                <section class="tab-panel <?= $form['active_tab'] === 'content' ? 'active' : '' ?>" data-panel="content">
                    <label>
                        QR data
                        <textarea name="data" id="data"><?= htmlspecialchars($form['data'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <p class="hint">The payload determines the QR version and module count, so everything else downstream depends on this field.</p>
                </section>

                <section class="tab-panel <?= $form['active_tab'] === 'layout' ? 'active' : '' ?>" data-panel="layout">
                    <div class="pill">Error correction: High</div>
                    <div class="grid">
                        <label>
                            Size
                            <input type="number" name="size" id="size" min="50" max="2400" value="<?= htmlspecialchars($form['size'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            Margin
                            <input type="number" name="margin" id="margin" min="0" max="200" value="<?= htmlspecialchars($form['margin'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                    <p class="hint">Size is the real density control here. Margin adds quiet zone outside the QR body, which is still useful but less structurally risky.</p>
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
                    <label class="pill">
                        <input type="checkbox" name="logo_punchout" value="1" <?= $form['logo_punchout'] === '1' ? 'checked' : '' ?>>
                        Punch out logo background
                    </label>
                    <p class="hint">The logo tab measures the current payload against the validator and caps the width to the largest passing square at the center.</p>
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
                    <p class="hint">Style tabs for anchors and module shapes are next once the structural guardrails feel right.</p>
                </section>

                <div class="actions">
                    <p class="meta">Package entry points stay the same for now: <code>QrCodeGenerator::generate()</code> and <code>QrCodeOptions</code>.</p>
                    <button class="primary" type="submit">Generate QR code</button>
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
<script>
const form = document.getElementById('qr-form');
const activeTabField = document.getElementById('active_tab');
const tabs = Array.from(document.querySelectorAll('.tab'));
const panels = Array.from(document.querySelectorAll('.tab-panel'));
const sizeInput = document.getElementById('size');
const logoWidthInput = document.getElementById('logo_width');
const analysisMessage = document.getElementById('analysis-message');
const layoutStatus = document.getElementById('layout-status');
const layoutConstraints = document.getElementById('layout-constraints');
const logoStatus = document.getElementById('logo-status');
const metricVersion = document.getElementById('metric-version');
const metricModules = document.getElementById('metric-modules');
const metricPpm = document.getElementById('metric-ppm');
const metricLogoMax = document.getElementById('metric-logo-max');

let debounceTimer = null;
let currentController = null;

function setActiveTab(tabName) {
    activeTabField.value = tabName;

    tabs.forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.tab === tabName);
    });

    panels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.panel === tabName);
    });
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setActiveTab(tab.dataset.tab));
});

function scheduleAnalysis() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(runAnalysis, 350);
}

async function runAnalysis() {
    if (currentController) {
        currentController.abort();
    }

    currentController = new AbortController();
    const formData = new FormData(form);

    analysisMessage.textContent = 'Rechecking payload, layout, and logo limits...';
    analysisMessage.className = 'status';

    try {
        const response = await fetch('?analyze=1', {
            method: 'POST',
            body: formData,
            signal: currentController.signal,
        });

        const result = await response.json();
        if (!result.ready) {
            analysisMessage.textContent = result.message || 'Analysis could not run yet.';
            analysisMessage.className = 'status error';
            return;
        }

        analysisMessage.textContent = result.message;
        analysisMessage.className = 'status ok';

        metricVersion.textContent = result.layout.version ?? '—';
        metricModules.textContent = result.layout.module_count ?? '—';
        metricPpm.textContent = result.layout.pixels_per_module ?? '—';
        metricLogoMax.textContent = result.logo.maximum_width === null ? '—' : `${result.logo.maximum_width} px`;

        layoutStatus.textContent = result.layout.message;
        layoutStatus.className = result.layout.current_valid ? 'status ok' : 'status error';

        const minimumSize = result.layout.minimum_size;
        layoutConstraints.textContent = minimumSize === null
            ? 'The payload is still too dense for the current constraints.'
            : `Minimum size: ${minimumSize} px. ${result.layout.margin_note ?? ''}`;

        if (typeof minimumSize === 'number') {
            sizeInput.min = String(minimumSize);
            if (Number(sizeInput.value) < minimumSize) {
                sizeInput.value = String(minimumSize);
            }
        }

        logoStatus.textContent = result.logo.message;
        logoStatus.className = result.logo.current_valid ? 'status ok' : 'status';

        if (typeof result.logo.maximum_width === 'number') {
            logoWidthInput.max = String(result.logo.maximum_width);
            if (Number(logoWidthInput.value) > result.logo.maximum_width) {
                logoWidthInput.value = String(result.logo.maximum_width);
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        analysisMessage.textContent = 'Analysis request failed.';
        analysisMessage.className = 'status error';
    }
}

form.addEventListener('input', scheduleAnalysis);
form.addEventListener('change', scheduleAnalysis);
runAnalysis();

</script>
<?php endif; ?>
</body>
</html>

