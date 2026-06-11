<?php

declare(strict_types=1);

namespace Walermo\QrCode\Tests;

use Endroid\QrCode\Writer\Result\ResultInterface;
use PHPUnit\Framework\TestCase;
use Walermo\QrCode\QrCodeGenerator;

final class QrCodeGeneratorTest extends TestCase
{
    public function testGenerateReturnsResultInterface(): void
    {
        $generator = new QrCodeGenerator();
        $result = $generator->generate('https://example.com');

        self::assertInstanceOf(ResultInterface::class, $result);
    }
}
