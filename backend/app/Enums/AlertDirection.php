<?php

namespace App\Enums;

enum AlertDirection: string
{
    case BelowMinimum = 'below_min';
    case AboveMaximum = 'above_max';
}
