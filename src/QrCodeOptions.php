<?php

declare(strict_types=1);

namespace Walermo\QrCode;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

final class QrCodeOptions
{
    private int $size = 300;
    private int $margin = 10;
    private string $encoding = 'UTF-8';
    private ErrorCorrectionLevel $errorCorrectionLevel = ErrorCorrectionLevel::High;
    private RoundBlockSizeMode $roundBlockSizeMode = RoundBlockSizeMode::Margin;
    private Color $foregroundColor;
    private Color $backgroundColor;
    private ?string $logoPath = null;
    private int $logoResizeToWidth = 70;
    private bool $logoPunchoutBackground = false;
    private ModuleShape $moduleShape = ModuleShape::Square;
    private EyeShape $eyeShape = EyeShape::Square;
    private ?Color $eyeOuterColor = null;
    private ?Color $eyeInnerColor = null;

    public function __construct()
    {
        $this->foregroundColor = new Color(0, 0, 0);
        $this->backgroundColor = new Color(255, 255, 255);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function withSize(int $size): self
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('QR code size must be greater than zero.');
        }

        $clone = clone $this;
        $clone->size = $size;

        return $clone;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function withMargin(int $margin): self
    {
        if ($margin < 0) {
            throw new \InvalidArgumentException('QR code margin cannot be negative.');
        }

        $clone = clone $this;
        $clone->margin = $margin;

        return $clone;
    }

    public function getEncoding(): Encoding
    {
        return new Encoding($this->encoding);
    }

    public function getEncodingValue(): string
    {
        return $this->encoding;
    }

    public function withEncoding(string $encoding): self
    {
        if (trim($encoding) === '') {
            throw new \InvalidArgumentException('QR code encoding cannot be empty.');
        }

        $clone = clone $this;
        $clone->encoding = $encoding;

        return $clone;
    }

    public function getErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    public function withErrorCorrectionLevel(ErrorCorrectionLevel $errorCorrectionLevel): self
    {
        $clone = clone $this;
        $clone->errorCorrectionLevel = $errorCorrectionLevel;

        return $clone;
    }

    public function getRoundBlockSizeMode(): RoundBlockSizeMode
    {
        return $this->roundBlockSizeMode;
    }

    public function withRoundBlockSizeMode(RoundBlockSizeMode $roundBlockSizeMode): self
    {
        $clone = clone $this;
        $clone->roundBlockSizeMode = $roundBlockSizeMode;

        return $clone;
    }

    public function getForegroundColor(): Color
    {
        return $this->foregroundColor;
    }

    public function withForegroundColor(Color $foregroundColor): self
    {
        $clone = clone $this;
        $clone->foregroundColor = $foregroundColor;

        return $clone;
    }

    public function getBackgroundColor(): Color
    {
        return $this->backgroundColor;
    }

    public function withBackgroundColor(Color $backgroundColor): self
    {
        $clone = clone $this;
        $clone->backgroundColor = $backgroundColor;

        return $clone;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function withLogoPath(?string $logoPath): self
    {
        $clone = clone $this;
        $clone->logoPath = $logoPath !== null && $logoPath !== '' ? $logoPath : null;

        return $clone;
    }

    public function getLogoResizeToWidth(): int
    {
        return $this->logoResizeToWidth;
    }

    public function withLogoResizeToWidth(int $logoResizeToWidth): self
    {
        if ($logoResizeToWidth < 1) {
            throw new \InvalidArgumentException('Logo resize width must be greater than zero.');
        }

        $clone = clone $this;
        $clone->logoResizeToWidth = $logoResizeToWidth;

        return $clone;
    }

    public function shouldPunchoutLogoBackground(): bool
    {
        return $this->logoPunchoutBackground;
    }

    public function withLogoPunchoutBackground(bool $logoPunchoutBackground): self
    {
        $clone = clone $this;
        $clone->logoPunchoutBackground = $logoPunchoutBackground;

        return $clone;
    }

    public function getModuleShape(): ModuleShape
    {
        return $this->moduleShape;
    }

    public function withModuleShape(ModuleShape $moduleShape): self
    {
        $clone = clone $this;
        $clone->moduleShape = $moduleShape;

        return $clone;
    }

    public function getEyeShape(): EyeShape
    {
        return $this->eyeShape;
    }

    public function withEyeShape(EyeShape $eyeShape): self
    {
        $clone = clone $this;
        $clone->eyeShape = $eyeShape;

        return $clone;
    }

    public function getEyeOuterColor(): ?Color
    {
        return $this->eyeOuterColor;
    }

    public function withEyeOuterColor(?Color $eyeOuterColor): self
    {
        $clone = clone $this;
        $clone->eyeOuterColor = $eyeOuterColor;

        return $clone;
    }

    public function getEyeInnerColor(): ?Color
    {
        return $this->eyeInnerColor;
    }

    public function withEyeInnerColor(?Color $eyeInnerColor): self
    {
        $clone = clone $this;
        $clone->eyeInnerColor = $eyeInnerColor;

        return $clone;
    }
}
