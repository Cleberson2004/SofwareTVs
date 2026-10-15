<?php

namespace App\Enums;

enum AdjustmentMode: string
{
    case EdgeAdjust = 'EDGE_ADJUST';
    case Fit = 'FIT';
    case Fill = 'FILL';
    case Stretch = 'STRETCH';
    case Reject = 'REJECT';
}
