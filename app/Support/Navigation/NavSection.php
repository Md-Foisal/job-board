<?php

namespace App\Support\Navigation;

/**
 * A run of navigation entries under one label. The first section of a
 * workspace has no label: it holds the dashboard.
 */
final readonly class NavSection
{
    /**
     * @param  list<NavItem>  $items
     */
    public function __construct(
        public ?string $heading,
        public array $items,
    ) {}
}
