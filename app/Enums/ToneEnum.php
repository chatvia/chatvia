<?php

namespace App\Enums;

enum ToneEnum: string
{
    case NEUTRAL = 'neutral';

    case FRUSTRATED = 'frustrated';

    case URGENT = 'urgent';

    case CONFUSED = 'confused';

    case SATISFIED = 'satisfied';

    case FORMAL = 'formal';

    case CASUAL = 'casual';
}
