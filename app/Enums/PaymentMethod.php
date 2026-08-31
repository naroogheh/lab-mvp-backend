<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'ONLINE';
    case CardToCard = 'CARD_TO_CARD';
}
