<?php

declare(strict_types=1);

namespace Walermo\QrCode;

enum EyeShape: string
{
    case Square = 'square';
    case Pointy = 'pointy';
    case Circle = 'circle';
}
