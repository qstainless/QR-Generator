<?php

declare(strict_types=1);

namespace Walermo\QrCode;

use BaconQrCode\Common\ErrorCorrectionLevel as BaconErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use BaconQrCode\Renderer\Eye\CompositeEye;
use BaconQrCode\Renderer\Eye\EyeInterface;
use BaconQrCode\Renderer\Eye\PointyEye;
use BaconQrCode\Renderer\Eye\SimpleCircleEye;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Module\ModuleInterface;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Writer as BaconWriter;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

final class QrCodeGenerator
{
    /**
     * @throws ValidationException
     */
    public function generate(string $data, ?QrCodeOptions $options = null): ResultInterface
    {
        $options ??= new QrCodeOptions();

        $qrCode = new QrCode(
            data: $data,
            encoding: $options->getEncoding(),
            errorCorrectionLevel: $options->getErrorCorrectionLevel(),
            size: $options->getSize(),
            margin: $options->getMargin(),
            roundBlockSizeMode: $options->getRoundBlockSizeMode(),
            foregroundColor: $options->getForegroundColor(),
            backgroundColor: $options->getBackgroundColor()
        );

        return $this->createQrWithLogo($qrCode, $data, $options);
    }

    /**
     * @throws ValidationException
     */
    public function createQrWithLogo(QrCode $qrCode, string $data, ?QrCodeOptions $options = null): ResultInterface
    {
        $options ??= new QrCodeOptions();
        $writer = new PngWriter();

        try {
            $logoPath = $options->getLogoPath();
            $result = $logoPath !== null
                ? $writer->write($qrCode, $this->createLogo($options))
                : $writer->write($qrCode);

            $writer->validateResult($result, $data);
        } catch (ValidationException $e) {
            throw new ValidationException('QR code generation failed: ' . $e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * @throws ValidationException
     */
    public function saveToFile(string $data, string $targetPath, ?QrCodeOptions $options = null): void
    {
        $this->generate($data, $options)->saveToFile($targetPath);
    }

    public function generateStyledSvg(string $data, ?QrCodeOptions $options = null): string
    {
        $options ??= new QrCodeOptions();
        $options = $this->normalizeLogoPosition($data, $options);

        $writer = new BaconWriter(
            new ImageRenderer(
                $this->createRendererStyle($options),
                new SvgImageBackEnd()
            )
        );

        $svg = $writer->writeString(
            $data,
            $options->getEncodingValue(),
            $this->convertErrorCorrectionLevel($options->getErrorCorrectionLevel())
        );

        return $this->addLogoToStyledSvg($svg, $options);
    }

    public function saveStyledSvgToFile(string $data, string $targetPath, ?QrCodeOptions $options = null): void
    {
        file_put_contents($targetPath, $this->generateStyledSvg($data, $options));
    }

    public function createLogo(QrCodeOptions $options): Logo
    {
        return new Logo(
            path: (string) $options->getLogoPath(),
            resizeToWidth: $options->getLogoResizeToWidth(),
            punchoutBackground: $options->shouldPunchoutLogoBackground()
        );
    }

    private function createRendererStyle(QrCodeOptions $options): RendererStyle
    {
        return new RendererStyle(
            $options->getSize(),
            $options->getMargin(),
            $this->createModule($options),
            $this->createEye($options),
            $this->createFill($options)
        );
    }

    private function createModule(QrCodeOptions $options): ModuleInterface
    {
        return match ($options->getModuleShape()) {
            ModuleShape::Dots => new DotsModule(DotsModule::MEDIUM),
            ModuleShape::Round => new RoundnessModule(RoundnessModule::MEDIUM),
            ModuleShape::Square => SquareModule::instance(),
        };
    }

    private function createEye(QrCodeOptions $options): EyeInterface
    {
        return match ($options->getEyeShape()) {
            EyeShape::Pointy => PointyEye::instance(),
            EyeShape::Circle => new CompositeEye(SquareEye::instance(), SimpleCircleEye::instance()),
            EyeShape::Square => SquareEye::instance(),
        };
    }

    private function createFill(QrCodeOptions $options): Fill
    {
        $backgroundColor = $this->toBaconRgb($options->getBackgroundColor());
        $foregroundColor = $this->toBaconRgb($options->getForegroundColor());

        return Fill::withForegroundColor(
            $backgroundColor,
            $foregroundColor,
            $this->createEyeFill(
                $options->getTopLeftEyeOuterColor() ?? $options->getEyeOuterColor(),
                $options->getTopLeftEyeInnerColor() ?? $options->getEyeInnerColor()
            ),
            $this->createEyeFill(
                $options->getTopRightEyeOuterColor() ?? $options->getEyeOuterColor(),
                $options->getTopRightEyeInnerColor() ?? $options->getEyeInnerColor()
            ),
            $this->createEyeFill(
                $options->getBottomLeftEyeOuterColor() ?? $options->getEyeOuterColor(),
                $options->getBottomLeftEyeInnerColor() ?? $options->getEyeInnerColor()
            )
        );
    }

    private function createEyeFill(?Color $outerColor, ?Color $innerColor): EyeFill
    {
        return new EyeFill(
            $this->toBaconRgbOrNull($outerColor),
            $this->toBaconRgbOrNull($innerColor)
        );
    }

    private function convertErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): BaconErrorCorrectionLevel
    {
        return match ($errorCorrectionLevel) {
            ErrorCorrectionLevel::Low => BaconErrorCorrectionLevel::L(),
            ErrorCorrectionLevel::Medium => BaconErrorCorrectionLevel::M(),
            ErrorCorrectionLevel::Quartile => BaconErrorCorrectionLevel::Q(),
            ErrorCorrectionLevel::High => BaconErrorCorrectionLevel::H(),
        };
    }

    private function toBaconRgb(Color $color): Rgb
    {
        return new Rgb($color->getRed(), $color->getGreen(), $color->getBlue());
    }

    private function toBaconRgbOrNull(?Color $color): ?Rgb
    {
        if ($color === null) {
            return null;
        }

        return $this->toBaconRgb($color);
    }

    private function addLogoToStyledSvg(string $svg, QrCodeOptions $options): string
    {
        $logoPath = $options->getLogoPath();
        if ($logoPath === null || !is_file($logoPath)) {
            return $svg;
        }

        $imageSize = @getimagesize($logoPath);
        if (!is_array($imageSize) || $imageSize[0] < 1 || $imageSize[1] < 1) {
            return $svg;
        }

        $mimeType = mime_content_type($logoPath);
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            return $svg;
        }

        $targetWidth = $options->getLogoResizeToWidth();
        $aspectRatio = $imageSize[1] / $imageSize[0];
        $targetHeight = max(1, (int) round($targetWidth * $aspectRatio));
        if ($options->hasCustomLogoPosition()) {
            $x = (int) $options->getLogoX();
            $y = (int) $options->getLogoY();
        } else {
            $x = max(0, (int) round(($options->getSize() - $targetWidth) / 2));
            $y = max(0, (int) round(($options->getSize() - $targetHeight) / 2));
        }
        $logoDataUri = sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode((string) file_get_contents($logoPath))
        );

        $punchout = '';
        if ($options->shouldPunchoutLogoBackground()) {
            $punchout = sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" fill="%s" />',
                $x,
                $y,
                $targetWidth,
                $targetHeight,
                $options->getBackgroundColor()->getHex()
            );
        }

        $image = sprintf(
            '%s<image x="%d" y="%d" width="%d" height="%d" preserveAspectRatio="none" href="%s" />',
            $punchout,
            $x,
            $y,
            $targetWidth,
            $targetHeight,
            htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8')
        );

        return str_replace('</svg>', $image . '</svg>', $svg);
    }

    private function normalizeLogoPosition(string $data, QrCodeOptions $options): QrCodeOptions
    {
        if (!$options->hasCustomLogoPosition()) {
            return $options;
        }

        $moduleCount = BaconEncoder::encode(
            $data,
            $this->convertErrorCorrectionLevel($options->getErrorCorrectionLevel()),
            $options->getEncodingValue()
        )->getVersion()->getDimensionForVersion();

        $size = $options->getSize();
        $logoSize = $options->getLogoResizeToWidth();
        $maxOrigin = max(0, $size - $logoSize);
        $x = min(max(0, (int) $options->getLogoX()), $maxOrigin);
        $y = min(max(0, (int) $options->getLogoY()), $maxOrigin);
        $exclusion = (int) ceil((7 * ($size / $moduleCount)) + 10);
        $bottomOverlapStart = $size - $exclusion - $logoSize;
        $safeMiddleStart = min($maxOrigin, max(0, $exclusion));
        $safeMiddleEnd = max($safeMiddleStart, min($maxOrigin, $bottomOverlapStart));

        if ($y < $exclusion) {
            $y = min($y, max(0, $exclusion - 1));
            $x = $this->clampLogoXToInterval($x, $exclusion, $size - $exclusion - $logoSize);

            if ($x === $exclusion && $exclusion > $size - $exclusion - $logoSize) {
                $y = $safeMiddleStart;
                $x = min(max(0, $x), $maxOrigin);
            }
        } elseif ($y > $bottomOverlapStart) {
            $y = max($safeMiddleEnd, min($y, $maxOrigin));
            $x = $this->clampLogoXToInterval($x, $exclusion, $maxOrigin);
        }

        return $options->withLogoPosition($x, $y);
    }

    private function clampLogoXToInterval(int $x, int $min, int $max): int
    {
        if ($min > $max) {
            return max(0, $x);
        }

        return min(max($x, $min), $max);
    }
}
