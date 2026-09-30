<?php

namespace App\Enums;

/**
 * Why an AI feature can or cannot run right now. The page tells these
 * apart because each one asks something different of the person: nothing
 * (disabled), a subscription (not in plan), or patience (limit reached).
 */
enum AiAvailability: string
{
    case Available = 'available';
    case Disabled = 'disabled';
    case NotInPlan = 'not_in_plan';
    case LimitReached = 'limit_reached';
}
