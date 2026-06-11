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
    private ?int $logoX = null;
    private ?int $logoY = null;
    private ModuleShape $moduleShape = ModuleShape::Square;
    private EyeShape $eyeShape = EyeShape::Square;
    private ?Color $eyeOuterColor = null;
    private ?Color $eyeInnerColor = null;
    private ?Color $topLeftEyeOuterColor = null;
    private ?Color $topLeftEyeInnerColor = null;
    private ?Color $topRightEyeOuterColor = null;
    private ?Color $topRightEyeInnerColor = null;
    private ?Color $bottomLeftEyeOuterColor = null;
    private ?Color $bottomLeftEyeInnerColor = null;

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

    public function getLogoX(): ?int
    {
        return $this->logoX;
    }

    public function getLogoY(): ?int
    {
        return $this->logoY;
    }

    public function hasCustomLogoPosition(): bool
    {
        return $this->logoX !== null && $this->logoY !== null;
    }

    public function withLogoPosition(?int $logoX, ?int $logoY): self
    {
        if (($logoX === null) !== ($logoY === null)) {
            throw new \InvalidArgumentException('Logo X and Y must either both be set or both be null.');
        }

        if ($logoX !== null && $logoX < 0) {
            throw new \InvalidArgumentException('Logo X position cannot be negative.');
        }

        if ($logoY !== null && $logoY < 0) {
            throw new \InvalidArgumentException('Logo Y position cannot be negative.');
        }

        $clone = clone $this;
        $clone->logoX = $logoX;
        $clone->logoY = $logoY;

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

    public function getTopLeftEyeOuterColor(): ?Color
    {
        return $this->topLeftEyeOuterColor;
    }

    public function withTopLeftEyeOuterColor(?Color $topLeftEyeOuterColor): self
    {
        $clone = clone $this;
        $clone->topLeftEyeOuterColor = $topLeftEyeOuterColor;

        return $clone;
    }

    public function getTopLeftEyeInnerColor(): ?Color
    {
        return $this->topLeftEyeInnerColor;
    }

    public function withTopLeftEyeInnerColor(?Color $topLeftEyeInnerColor): self
    {
        $clone = clone $this;
        $clone->topLeftEyeInnerColor = $topLeftEyeInnerColor;

        return $clone;
    }

    public function getTopRightEyeOuterColor(): ?Color
    {
        return $this->topRightEyeOuterColor;
    }

    public function withTopRightEyeOuterColor(?Color $topRightEyeOuterColor): self
    {
        $clone = clone $this;
        $clone->topRightEyeOuterColor = $topRightEyeOuterColor;

        return $clone;
    }

    public function getTopRightEyeInnerColor(): ?Color
    {
        return $this->topRightEyeInnerColor;
    }

    public function withTopRightEyeInnerColor(?Color $topRightEyeInnerColor): self
    {
        $clone = clone $this;
        $clone->topRightEyeInnerColor = $topRightEyeInnerColor;

        return $clone;
    }

    public function getBottomLeftEyeOuterColor(): ?Color
    {
        return $this->bottomLeftEyeOuterColor;
    }

    public function withBottomLeftEyeOuterColor(?Color $bottomLeftEyeOuterColor): self
    {
        $clone = clone $this;
        $clone->bottomLeftEyeOuterColor = $bottomLeftEyeOuterColor;

        return $clone;
    }

    public function getBottomLeftEyeInnerColor(): ?Color
    {
        return $this->bottomLeftEyeInnerColor;
    }

    public function withBottomLeftEyeInnerColor(?Color $bottomLeftEyeInnerColor): self
    {
        $clone = clone $this;
        $clone->bottomLeftEyeInnerColor = $bottomLeftEyeInnerColor;

        return $clone;
    }
}
