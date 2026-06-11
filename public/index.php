<?php

declare(strict_types=1);

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Walermo\QrCode\QrCodeGenerator;
use Walermo\QrCode\QrCodeOptions;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$autoloadReady = is_file($autoloadPath);

if ($autoloadReady) {
    require $autoloadPath;
}

$form = [
    'data' => $_POST['data'] ?? 'https://example.com',
    'size' => (string) ($_POST['size'] ?? '300'),
    'margin' => (string) ($_POST['margin'] ?? '10'),
    'error_correction' => $_POST['error_correction'] ?? 'high',
    'foreground' => $_POST['foreground'] ?? '#000000',
    'background' => $_POST['background'] ?? '#ffffff',
    'logo_path' => $_POST['logo_path'] ?? '',
    'logo_width' => (string) ($_POST['logo_width'] ?? '70'),
    'logo_punchout' => isset($_POST['logo_punchout']) ? '1' : '',
];

$pngDataUri = null;
$errorMessage = null;

if ($autoloadReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $options = (new QrCodeOptions())
            ->withSize((int) $form['size'])
            ->withMargin((int) $form['margin'])
            ->withErrorCorrectionLevel(match (strtolower($form['error_correction'])) {
                'low' => ErrorCorrectionLevel::Low,
                'medium' => ErrorCorrectionLevel::Medium,
                'quartile' => ErrorCorrectionLevel::Quartile,
                default => ErrorCorrectionLevel::High,
            })
            ->withForegroundColor(hexToColor($form['foreground']))
            ->withBackgroundColor(hexToColor($form['background']))
            ->withLogoPath($form['logo_path'] !== '' ? $form['logo_path'] : null)
            ->withLogoResizeToWidth((int) $form['logo_width'])
            ->withLogoPunchoutBackground($form['logo_punchout'] === '1');

        $result = (new QrCodeGenerator())->generate($form['data'], $options);
        $pngDataUri = 'data:image/png;base64,' . base64_encode($result->getString());
    } catch (Throwable $throwable) {
        $errorMessage = $throwable->getMessage();
    }
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
            --panel: #fffdf8;
            --ink: #1c1917;
            --muted: #6b645c;
            --line: #d8d0c4;
            --accent: #136f63;
            --accent-strong: #0b4f47;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top right, rgba(19, 111, 99, 0.12), transparent 30%),
                linear-gradient(180deg, #f8f5ef 0%, var(--bg) 100%);
            color: var(--ink);
        }
        main {
            max-width: 1100px;
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
            max-width: 680px;
            color: var(--muted);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-top: 12px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 24px;
        }
        .panel {
            background: rgba(255, 253, 248, 0.92);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 18px 50px rgba(28, 25, 23, 0.08);
        }
        form {
            display: grid;
            gap: 16px;
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
        input, select, textarea, button {
            font: inherit;
        }
        input, select, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
            color: var(--ink);
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox input {
            width: auto;
        }
        button {
            border: 0;
            border-radius: 999px;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            color: #fff;
            cursor: pointer;
        }
        .hint, .status {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .status.error {
            color: #9f1239;
        }
        .preview-wrap {
            display: grid;
            gap: 18px;
            align-content: start;
        }
        .preview {
            display: grid;
            place-items: center;
            min-height: 360px;
            border: 1px dashed var(--line);
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.8), rgba(248,245,239,0.9));
            padding: 18px;
        }
        .preview img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        code {
            background: rgba(19, 111, 99, 0.08);
            padding: 2px 6px;
            border-radius: 6px;
        }
        @media (max-width: 860px) {
            .layout, .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <p class="eyebrow">Package Playground</p>
        <h1>QR generator, package first.</h1>
        <p class="sub">This site is a small local playground for the standalone <code>walermo/qr-code</code> package. The reusable code lives in <code>src/</code>; this form is only here to help you explore options quickly.</p>
    </section>

    <section class="layout">
        <div class="panel">
            <?php if (!$autoloadReady): ?>
                <p class="status error">Dependencies are not installed yet. Run <code>composer install</code> in <code>/Users/gce/Sites/qrgenerator.test</code> and refresh this page.</p>
            <?php endif; ?>

            <?php if ($errorMessage !== null): ?>
                <p class="status error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post">
                <label>
                    QR data
                    <textarea name="data"><?= htmlspecialchars($form['data'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>

                <div class="grid">
                    <label>
                        Size
                        <input type="number" name="size" min="50" max="2000" value="<?= htmlspecialchars($form['size'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        Margin
                        <input type="number" name="margin" min="0" max="100" value="<?= htmlspecialchars($form['margin'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        Error correction
                        <select name="error_correction">
                            <option value="low" <?= $form['error_correction'] === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $form['error_correction'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="quartile" <?= $form['error_correction'] === 'quartile' ? 'selected' : '' ?>>Quartile</option>
                            <option value="high" <?= $form['error_correction'] === 'high' ? 'selected' : '' ?>>High</option>
                        </select>
                    </label>
                    <label>
                        Logo width
                        <input type="number" name="logo_width" min="10" max="400" value="<?= htmlspecialchars($form['logo_width'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        Foreground
                        <input type="color" name="foreground" value="<?= htmlspecialchars($form['foreground'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        Background
                        <input type="color" name="background" value="<?= htmlspecialchars($form['background'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                </div>

                <label>
                    Logo path on disk
                    <input type="text" name="logo_path" placeholder="/absolute/path/to/logo.png" value="<?= htmlspecialchars($form['logo_path'], ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="logo_punchout" value="1" <?= $form['logo_punchout'] === '1' ? 'checked' : '' ?>>
                    Punch out logo background
                </label>

                <button type="submit">Generate QR code</button>
                <p class="hint">Start small here, then we can widen the package API to expose more of Endroid's features as you discover what you want to keep.</p>
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
            <div class="panel">
                <p class="hint">Current package entry points:</p>
                <p class="hint"><code>QrCodeGenerator::generate()</code></p>
                <p class="hint"><code>QrCodeGenerator::saveToFile()</code></p>
                <p class="hint"><code>QrCodeOptions</code> for size, margin, colors, error correction, and logo settings.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
