<?php

namespace App\Enums;

enum CommandStatus: string
{
    case Queued = 'queued';
    case Leased = 'leased';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
