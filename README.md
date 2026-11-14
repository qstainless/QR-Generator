# Walermo QR Code

A package-first QR code generator for personal PHP projects, with a gradual shift toward Bacon-backed styling controls.

## Goals

- Keep the core library framework-agnostic
- Expose a clean PHP API that can grow toward more of Endroid's feature set
- Leave `public/` available as a small playground for trying options in the browser

## Install

```bash
composer install
```

## Usage

### Standard PNG

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

### Styled SVG

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\Color\Color;
use Walermo\QrCode\EyeShape;
use Walermo\QrCode\ModuleShape;
use Walermo\QrCode\QrCodeGenerator;
use Walermo\QrCode\QrCodeOptions;

$options = (new QrCodeOptions())
    ->withSize(320)
    ->withModuleShape(ModuleShape::Dots)
    ->withEyeShape(EyeShape::Circle)
    ->withEyeOuterColor(new Color(255, 0, 0))
    ->withEyeInnerColor(new Color(0, 128, 255));

$generator = new QrCodeGenerator();
$svg = $generator->generateStyledSvg('https://example.com', $options);

file_put_contents(__DIR__ . '/qr-styled.svg', $svg);
```

Current styled options:

- `withModuleShape()`: `Square`, `Dots`, `Round`
- `withEyeShape()`: `Square`, `Pointy`, `Circle`
- `withEyeOuterColor()`: set a separate outer anchor color
- `withEyeInnerColor()`: set a separate inner anchor color

## Demo

After installing dependencies, open [http://qrgenerator.test](http://qrgenerator.test) to use the browser demo in `public/index.php`. The demo is a local playground only and is excluded from Composer package archives.

## Tests

```bash
composer test
```

## Structure

- `src/`: package source
- `tests/`: package tests
- `public/`: optional local playground, not part of the package API

## Development & AI Attribution

This project was conceived, designed, and directed by its maintainer to address limitations encountered with existing QR code generation services, particularly paid feature restrictions and reliance on third-party redirects.

Development was assisted by OpenAI Codex and ChatGPT, including code generation, implementation, debugging, and exploration of technical approaches and library capabilities.

The project's objectives, requirements, technical direction, and final decisions were established and controlled by its maintainer. AI tools served as development assistants rather than independent project authors.

AI-assisted contributions were subject to human review and acceptance, and responsibility for the resulting software remains with the maintainer.
