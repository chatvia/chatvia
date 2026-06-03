<?php

namespace Domain\Conversation\Enums;

enum SessionTypeEnum: string
{
    case WIDGET = 'cs:w';
    case MEMBER = 'cs:m';
    case GUEST = 'cs:g';
}
