<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case RESERVED = 'reserved';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
