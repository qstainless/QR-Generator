<?php

declare(strict_types=1);

namespace Walermo\QrCode;

enum ModuleShape: string
{
    case Square = 'square';
    case Dots   = 'dots';
    case Round  = 'round';
}
