<?php

declare(strict_types=1);

use BaconQrCode\Common\ErrorCorrectionLevel as BaconErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Walermo\QrCode\EyeShape;
use Walermo\QrCode\ModuleShape;
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
        $generator = new QrCodeGenerator();
        $options = createOptionsFromForm($form);

        if (shouldUseStyledPreview($form)) {
            $svg = $generator->generateStyledSvg($form['data'], $options);
            $pngDataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
        } else {
            if (!isValidationMemorySafe((int) $form['size'], (int) $form['margin'])) {
                throw new RuntimeException(sprintf(
                    'The validation reader would exceed available PHP memory at this rendered size. Keep the QR body at or below roughly %d px for live PNG preview.',
                    getValidationSafeMaxInnerSize((int) $form['margin'])
                ));
            }

            $result = $generator->generate($form['data'], $options);
            $pngDataUri = 'data:image/png;base64,' . base64_encode($result->getString());
        }
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
        'eye_outer' => $source['eye_outer'] ?? '',
        'eye_inner' => $source['eye_inner'] ?? '',
        'top_left_eye_outer' => $source['top_left_eye_outer'] ?? '',
        'top_left_eye_inner' => $source['top_left_eye_inner'] ?? '',
        'top_right_eye_outer' => $source['top_right_eye_outer'] ?? '',
        'top_right_eye_inner' => $source['top_right_eye_inner'] ?? '',
        'bottom_left_eye_outer' => $source['bottom_left_eye_outer'] ?? '',
        'bottom_left_eye_inner' => $source['bottom_left_eye_inner'] ?? '',
        'module_shape' => $source['module_shape'] ?? 'square',
        'eye_shape' => $source['eye_shape'] ?? 'square',
        'logo_path' => $source['logo_path'] ?? '',
        'logo_width' => (string) ($source['logo_width'] ?? '70'),
        'logo_x' => isset($source['logo_x']) ? trim((string) $source['logo_x']) : '',
        'logo_y' => isset($source['logo_y']) ? trim((string) $source['logo_y']) : '',
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
    $validationSafe = isValidationMemorySafe($size, $margin);
    $currentLayoutValid = isLayoutValid($data, $form, $size, $margin);

    $layoutMessage = !$validationSafe
        ? sprintf(
            'The validator would exceed available PHP memory at this rendered size. Keep the QR body at or below roughly %d px for live validation.',
            getValidationSafeMaxInnerSize($margin)
        )
        : ($currentLayoutValid
            ? 'This payload validates at the current size and margin.'
            : 'Increase the size or reduce the margin until the validator can read the code reliably.');

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
            'validation_safe' => $validationSafe,
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
        ->withBackgroundColor(hexToColor((string) $form['background']))
        ->withEyeOuterColor(parseOptionalHexColor((string) $form['eye_outer']))
        ->withEyeInnerColor(parseOptionalHexColor((string) $form['eye_inner']))
        ->withTopLeftEyeOuterColor(parseOptionalHexColor((string) $form['top_left_eye_outer']))
        ->withTopLeftEyeInnerColor(parseOptionalHexColor((string) $form['top_left_eye_inner']))
        ->withTopRightEyeOuterColor(parseOptionalHexColor((string) $form['top_right_eye_outer']))
        ->withTopRightEyeInnerColor(parseOptionalHexColor((string) $form['top_right_eye_inner']))
        ->withBottomLeftEyeOuterColor(parseOptionalHexColor((string) $form['bottom_left_eye_outer']))
        ->withBottomLeftEyeInnerColor(parseOptionalHexColor((string) $form['bottom_left_eye_inner']))
        ->withModuleShape(parseModuleShape((string) $form['module_shape']))
        ->withEyeShape(parseEyeShape((string) $form['eye_shape']));

    if (!$includeLogo) {
        return $options;
    }

    $logoPath = trim((string) $form['logo_path']);
    [$logoX, $logoY] = parseLogoPositionInputs($form);

    return $options
        ->withLogoPath($logoPath !== '' ? $logoPath : null)
        ->withLogoResizeToWidth($logoWidthOverride ?? max(1, (int) $form['logo_width']))
        ->withLogoPosition($logoX, $logoY)
        ->withLogoPunchoutBackground($form['logo_punchout'] === '1');
}

function parseOptionalInt(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    return (int) $value;
}

function parseLogoPositionInputs(array $form): array
{
    $logoX = parseOptionalInt((string) $form['logo_x']);
    $logoY = parseOptionalInt((string) $form['logo_y']);

    if ($logoX === null || $logoY === null) {
        return [null, null];
    }

    return [$logoX, $logoY];
}

function parseModuleShape(string $shape): ModuleShape
{
    return ModuleShape::tryFrom(strtolower($shape)) ?? ModuleShape::Square;
}

function parseEyeShape(string $shape): EyeShape
{
    return EyeShape::tryFrom(strtolower($shape)) ?? EyeShape::Square;
}

function isLayoutValid(string $data, array $form, int $size, int $margin): bool
{
    if (!isValidationMemorySafe($size, $margin)) {
        return false;
    }

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
    if (!isValidationMemorySafe((int) $form['size'], (int) $form['margin'])) {
        return false;
    }

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
    $maxInnerSize = getValidationSafeMaxInnerSize($margin);

    if ($maxInnerSize !== null) {
        $high = min($high, $maxInnerSize);
    }

    while ($high <= 2400 && !isLayoutValid($data, $form, $high, $margin)) {
        $high *= 2;

        if ($maxInnerSize !== null && $high > $maxInnerSize) {
            $high = $maxInnerSize;
            break;
        }
    }

    if ($high > 2400 || !isLayoutValid($data, $form, $high, $margin)) {
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

function shouldUseStyledPreview(array $form): bool
{
    [$logoX, $logoY] = parseLogoPositionInputs($form);
    $hasEyeColorCustomization = trim((string) ($form['eye_outer'] ?? '')) !== ''
        || trim((string) ($form['eye_inner'] ?? '')) !== ''
        || trim((string) ($form['top_left_eye_outer'] ?? '')) !== ''
        || trim((string) ($form['top_left_eye_inner'] ?? '')) !== ''
        || trim((string) ($form['top_right_eye_outer'] ?? '')) !== ''
        || trim((string) ($form['top_right_eye_inner'] ?? '')) !== ''
        || trim((string) ($form['bottom_left_eye_outer'] ?? '')) !== ''
        || trim((string) ($form['bottom_left_eye_inner'] ?? '')) !== '';

    return parseModuleShape((string) $form['module_shape']) !== ModuleShape::Square
        || parseEyeShape((string) $form['eye_shape']) !== EyeShape::Square
        || $hasEyeColorCustomization
        || $logoX !== null
        || $logoY !== null;
}

function getValidationMemoryLimitBytes(): ?int
{
    $limit = ini_get('memory_limit');
    if ($limit === false || $limit === '' || $limit === '-1') {
        return null;
    }

    $limit = trim($limit);
    $unit = strtolower($limit[strlen($limit) - 1]);
    $value = (int) $limit;

    return match ($unit) {
        'g' => $value * 1024 * 1024 * 1024,
        'm' => $value * 1024 * 1024,
        'k' => $value * 1024,
        default => (int) $limit,
    };
}

function getValidationSafeMaxInnerSize(int $margin): ?int
{
    $memoryLimit = getValidationMemoryLimitBytes();
    if ($memoryLimit === null) {
        return null;
    }

    $available = $memoryLimit - memory_get_usage(true) - (16 * 1024 * 1024);
    if ($available <= 0) {
        return 0;
    }

    $outerLimit = (int) floor(sqrt(($available * 0.8) / 48));
    $innerLimit = $outerLimit - (2 * max(0, $margin));

    return max(0, $innerLimit);
}

function isValidationMemorySafe(int $size, int $margin): bool
{
    $maxInnerSize = getValidationSafeMaxInnerSize($margin);
    if ($maxInnerSize === null) {
        return true;
    }

    return $size <= $maxInnerSize;
}

function hexToColor(string $hex): Color
{
    $hex = ltrim($hex, '#');

    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        throw new InvalidArgumentException('Colors must use 6-digit hex values such as #112233.');
    }

    return new Color(
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}

function parseOptionalHexColor(string $hex): ?Color
{
    $hex = trim($hex);

    if ($hex === '') {
        return null;
    }

    return hexToColor($hex);
}
require __DIR__ . '/view.php';
