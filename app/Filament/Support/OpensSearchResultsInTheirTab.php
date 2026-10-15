<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * For a resource whose list is split into tabs. A global search result
 * opens the record's dialog on the list page, and that page opens on its
 * first tab, so a result from any other tab (an approved posting, a
 * verified company) landed on a list that did not hold it and nothing
 * opened. The link now names the tab the record is in.
 *
 * Each result asks its tabs in turn, so the number of results per
 * resource is kept small, as in the search menus of Linear or GitHub.
 */
trait OpensSearchResultsInTheirTab
{
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        $url = parent::getGlobalSearchResultUrl($record);
        $tab = static::tabHolding($record);

        if ($url === null || $tab === null) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['tab' => $tab]);
    }

    public static function getGlobalSearchResultsLimit(): int
    {
        return 5;
    }

    private static function tabHolding(Model $record): ?string
    {
        $page = static::getPages()['index']->getPage();

        foreach ((new $page)->getTabs() as $key => $tab) {
            if ($tab->modifyQuery(static::getEloquentQuery())->whereKey($record->getKey())->exists()) {
                return (string) $key;
            }
        }

        return null;
    }
}
