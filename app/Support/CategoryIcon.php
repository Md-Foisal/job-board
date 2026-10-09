<?php

namespace App\Support;

use App\Models\Category;

/**
 * The icon a category is shown with. Categories are kept by staff, so this
 * covers the ones the board starts with; a category added later shows the
 * general briefcase until it is given an icon here.
 */
final class CategoryIcon
{
    private const ICONS = [
        'engineering' => 'code-bracket',
        'information-technology' => 'server-stack',
        'data-analytics' => 'chart-bar',
        'design' => 'paint-brush',
        'product' => 'cube',
        'marketing' => 'megaphone',
        'sales' => 'arrow-trending-up',
        'customer-support' => 'chat-bubble-left-right',
        'finance' => 'banknotes',
        'operations' => 'adjustments-horizontal',
        'human-resources' => 'user-group',
        'writing-content' => 'pencil-square',
    ];

    public static function for(Category $category): string
    {
        return self::ICONS[$category->slug] ?? 'briefcase';
    }
}
