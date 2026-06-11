<?php

declare(strict_types=1);

namespace Walermo\QrCode\Tests;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\Result\ResultInterface;
use PHPUnit\Framework\TestCase;
use Walermo\QrCode\EyeShape;
use Walermo\QrCode\ModuleShape;
use Walermo\QrCode\QrCodeGenerator;
use Walermo\QrCode\QrCodeOptions;

final class QrCodeGeneratorTest extends TestCase
{
    public function testGenerateReturnsResultInterface(): void
    {
        $generator = new QrCodeGenerator();
        $result = $generator->generate('https://example.com');

        self::assertInstanceOf(ResultInterface::class, $result);
    }

    public function testOptionsAreImmutable(): void
    {
        $options = new QrCodeOptions();
        $resized = $options->withSize(512);

        self::assertNotSame($options, $resized);
        self::assertSame(300, $options->getSize());
        self::assertSame(512, $resized->getSize());
    }

    public function testSaveToFileWritesPng(): void
    {
        $targetPath = tempnam(sys_get_temp_dir(), 'walermo-qr-');
        self::assertIsString($targetPath);

        try {
            (new QrCodeGenerator())->saveToFile('https://example.com', $targetPath);

            self::assertFileExists($targetPath);
            self::assertSame('image/png', mime_content_type($targetPath));
            self::assertGreaterThan(0, filesize($targetPath));
        } finally {
            if (is_file($targetPath)) {
                unlink($targetPath);
            }
        }
    }

    public function testGenerateStyledSvgReturnsSvgMarkup(): void
    {
        $options = (new QrCodeOptions())
            ->withModuleShape(ModuleShape::Dots)
            ->withEyeShape(EyeShape::Circle);

        $svg = (new QrCodeGenerator())->generateStyledSvg('https://example.com', $options);

        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
    }

    public function testGenerateStyledSvgAppliesEyeColors(): void
    {
        $options = (new QrCodeOptions())
            ->withEyeOuterColor(new Color(255, 0, 0))
            ->withEyeInnerColor(new Color(0, 128, 255));

        $svg = (new QrCodeGenerator())->generateStyledSvg('https://example.com', $options);

        self::assertStringContainsString('#ff0000', strtolower($svg));
        self::assertStringContainsString('#0080ff', strtolower($svg));
    }

    public function testGenerateStyledSvgAppliesPerEyeColorOverrides(): void
    {
        $options = (new QrCodeOptions())
            ->withEyeOuterColor(new Color(255, 0, 0))
            ->withEyeInnerColor(new Color(0, 128, 255))
            ->withTopLeftEyeOuterColor(new Color(255, 200, 0))
            ->withTopLeftEyeInnerColor(new Color(120, 0, 255))
            ->withTopRightEyeOuterColor(new Color(0, 200, 120))
            ->withTopRightEyeInnerColor(new Color(255, 80, 140))
            ->withBottomLeftEyeOuterColor(new Color(40, 90, 255))
            ->withBottomLeftEyeInnerColor(new Color(255, 255, 255));

        $svg = strtolower((new QrCodeGenerator())->generateStyledSvg('https://example.com', $options));

        self::assertStringContainsString('#ffc800', $svg);
        self::assertStringContainsString('#7800ff', $svg);
        self::assertStringContainsString('#00c878', $svg);
        self::assertStringContainsString('#ff508c', $svg);
        self::assertStringContainsString('#285aff', $svg);
        self::assertStringContainsString('#ffffff', $svg);
    }

    public function testSaveStyledSvgToFileWritesSvg(): void
    {
        $targetPath = tempnam(sys_get_temp_dir(), 'walermo-qr-');
        self::assertIsString($targetPath);

        try {
            (new QrCodeGenerator())->saveStyledSvgToFile('https://example.com', $targetPath);

            self::assertFileExists($targetPath);
            self::assertStringContainsString('<svg', (string) file_get_contents($targetPath));
        } finally {
            if (is_file($targetPath)) {
                unlink($targetPath);
            }
        }
    }

    public function testGenerateStyledSvgEmbedsLogoImage(): void
    {
        $logoPath = tempnam(sys_get_temp_dir(), 'walermo-logo-');
        self::assertIsString($logoPath);

        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagepng($image, $logoPath);

        try {
            $options = (new QrCodeOptions())
                ->withModuleShape(ModuleShape::Dots)
                ->withLogoPath($logoPath)
                ->withLogoResizeToWidth(24);

            $svg = (new QrCodeGenerator())->generateStyledSvg('https://example.com', $options);

            self::assertStringContainsString('<image ', $svg);
            self::assertStringContainsString('data:image/png;base64,', $svg);
        } finally {
            if (is_file($logoPath)) {
                unlink($logoPath);
            }
        }
    }

    public function testGenerateStyledSvgUsesCustomLogoPosition(): void
    {
        $logoPath = tempnam(sys_get_temp_dir(), 'walermo-logo-');
        self::assertIsString($logoPath);

        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagepng($image, $logoPath);

        try {
            $options = (new QrCodeOptions())
                ->withModuleShape(ModuleShape::Dots)
                ->withLogoPath($logoPath)
                ->withLogoResizeToWidth(24)
                ->withLogoPosition(80, 80);

            $svg = (new QrCodeGenerator())->generateStyledSvg('https://example.com', $options);

            self::assertStringContainsString('<image x="83" y="80" width="24"', $svg);
        } finally {
            if (is_file($logoPath)) {
                unlink($logoPath);
            }
        }
    }

    public function testGenerateStyledSvgClampsLogoAwayFromBottomLeftEye(): void
    {
        $logoPath = tempnam(sys_get_temp_dir(), 'walermo-logo-');
        self::assertIsString($logoPath);

        $image = imagecreatetruecolor(8, 8);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagepng($image, $logoPath);

        try {
            $options = (new QrCodeOptions())
                ->withModuleShape(ModuleShape::Dots)
                ->withLogoPath($logoPath)
                ->withLogoResizeToWidth(24)
                ->withLogoPosition(10, 260);

            $svg = (new QrCodeGenerator())->generateStyledSvg('https://example.com', $options);

            self::assertStringContainsString('<image x="83" y="260" width="24"', $svg);
        } finally {
            if (is_file($logoPath)) {
                unlink($logoPath);
            }
        }
    }

    public function testRejectsInvalidSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR code size must be greater than zero.');

        (new QrCodeOptions())->withSize(0);
    }

    public function testRejectsNegativeMargin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR code margin cannot be negative.');

        (new QrCodeOptions())->withMargin(-1);
    }

    public function testRejectsEmptyEncoding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR code encoding cannot be empty.');

        (new QrCodeOptions())->withEncoding('');
    }

    public function testRejectsInvalidLogoWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logo resize width must be greater than zero.');

        (new QrCodeOptions())->withLogoResizeToWidth(0);
    }

    public function testRejectsIncompleteLogoPosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logo X and Y must either both be set or both be null.');

        (new QrCodeOptions())->withLogoPosition(10, null);
    }
}
