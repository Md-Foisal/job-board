<?php

namespace App\Support\Navigation;

/**
 * One entry of a workspace's navigation: what the sidebar draws as a row
 * and the command palette lists as a page to jump to.
 */
final readonly class NavItem
{
    /**
     * @param  list<string>  $activeRoutes  Route name patterns under which this entry is the current one.
     *                                      Empty leaves the decision to the link's own address.
     */
    public function __construct(
        public string $label,
        public string $icon,
        public string $url,
        public array $activeRoutes = [],
        public ?int $badge = null,
    ) {}

    /**
     * Null rather than false when no patterns are given, so the sidebar
     * falls back to matching the address instead of marking it inactive.
     */
    public function isCurrent(): ?bool
    {
        return $this->activeRoutes === [] ? null : request()->routeIs(...$this->activeRoutes);
    }
}
