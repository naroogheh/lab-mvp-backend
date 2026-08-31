<?php

namespace App\Enums;

enum AiRequestStatus: string
{
    case Pending = 'PENDING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
