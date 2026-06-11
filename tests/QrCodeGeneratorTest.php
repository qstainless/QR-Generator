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
}
