<?php

namespace App\Enum;

enum PersonaType: string
{
    case SMALL_BUSINESS = 'small_business';
    case MARKETING = 'marketing';
    case CONTENT_CREATOR = 'content_creator';
    case CUSTOMER_SUPPORT = 'customer_support';
    case MARKET_RESEARCHER = 'market_researcher';
    case FREELANCER = 'freelancer';
    case PR_CRISIS = 'pr_crisis';

    public static function getValues(): array
    {
        return array_values(self::values());
    }
}
