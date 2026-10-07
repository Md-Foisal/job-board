{{--
    Tabs that switch between views of the same thing -- a list with
    another filter, or the sections of one settings page -- drawn as a
    row of links under the page header (Shopify Polaris Tabs: the same
    kind of content, one view current at a time, short labels). Each tab
    is a real address, so a view can be bookmarked and the back button
    works.

    The current tab's label is ink like the others; its underline
    carries the colour, in the Sunset fill (app.css, [data-tab-nav]).

    @param string $label  What the row of tabs is, for screen readers.
--}}
@props(['label'])

<flux:navbar scrollable data-tab-nav :attributes="$attributes->class('-mt-2 border-b border-line')" :aria-label="$label">
    {{ $slot }}
</flux:navbar>
