<?php

declare(strict_types=1);

namespace Walermo\QrCode;

use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
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

    public function createLogo(QrCodeOptions $options): Logo
    {
        return new Logo(
            path: (string) $options->getLogoPath(),
            resizeToWidth: $options->getLogoResizeToWidth(),
            punchoutBackground: $options->shouldPunchoutLogoBackground()
        );
    }
}
