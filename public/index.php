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
require __DIR__ . '/view.php';
