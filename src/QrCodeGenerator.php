<?php

declare(strict_types=1);

namespace Walermo\QrCode;

use BaconQrCode\Common\ErrorCorrectionLevel as BaconErrorCorrectionLevel;
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

        $writer = new BaconWriter(
            new ImageRenderer(
                $this->createRendererStyle($options),
                new SvgImageBackEnd()
            )
        );

        return $writer->writeString(
            $data,
            $options->getEncodingValue(),
            $this->convertErrorCorrectionLevel($options->getErrorCorrectionLevel())
        );
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
        $eyeFill = new EyeFill(
            $this->toBaconRgbOrNull($options->getEyeOuterColor()),
            $this->toBaconRgbOrNull($options->getEyeInnerColor())
        );

        return Fill::withForegroundColor(
            $backgroundColor,
            $foregroundColor,
            $eyeFill,
            $eyeFill,
            $eyeFill
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
}
