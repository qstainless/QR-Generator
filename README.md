# Walermo QR Code

A package-first QR code generator for personal PHP projects.

## Goals

- Keep the core library framework-agnostic
- Expose a clean PHP API that can grow toward more of Endroid's feature set
- Leave `public/` available as a small playground for trying options in the browser

## Install

```bash
composer install
```

## Usage

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Walermo\QrCode\QrCodeGenerator;
use Walermo\QrCode\QrCodeOptions;

$options = (new QrCodeOptions())
    ->withSize(320)
    ->withMargin(16)
    ->withLogoPath(__DIR__ . '/public/logo.png')
    ->withLogoResizeToWidth(72);

$generator = new QrCodeGenerator();
$result = $generator->generate('https://example.com', $options);
$result->saveToFile(__DIR__ . '/qr.png');
```

## Demo

After installing dependencies, open [http://qrgenerator.test](http://qrgenerator.test) to use the browser demo in `public/index.php`.

## Structure

- `src/`: package source
- `tests/`: package tests
- `public/`: optional local playground, not part of the package API
