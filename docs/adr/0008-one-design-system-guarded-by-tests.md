# 8. One design system, guarded by tests

Date: October 2026 (layer 8)
Status: Accepted

## Context

Before layer 8 every page chose its own greys, widths, pills and empty boxes. The same card was written
by hand in dozens of views, so changing its look meant finding every copy, and copies were always
missed. Dark mode made it worse: a grey that reads well on white can fail contrast on black.

## Decision

- The look lives in one place: named tokens in [`resources/css/app.css`](../../resources/css/app.css)
  (`canvas`, `surface`, `line`, `ink`, `ink-muted`, the Sunset gradient, the status colours) and Blade
  components for the repeated blocks (`x-card`, `x-chip`, `x-empty-state`, `x-company-logo`,
  `x-page`, `x-page-header` and others). Views use those, never a raw palette colour.
- Colour has rules. Neutral text and buttons are ink. The Sunset gradient marks the one main action on
  a page and the coloured text and icons. Status colours (green, amber, red) keep their meaning and are
  never a gradient. Every pair is measured against WCAG contrast in light and dark.
- App pages share one frame: one header with one `<h1>`, two widths (lists and forms), a back link
  instead of a breadcrumb trail, and the same sidebar and command palette built from one list.
- Tests hold the rules. [`DesignSystemGuardTest`](../../tests/Feature/DesignSystemGuardTest.php) fails
  when a view uses a raw colour, a hand-made card or empty state, or a badge colour outside the status
  set, and names the component to use. [`PageFrameTest`](../../tests/Feature/PageFrameTest.php) checks
  the headings, widths and navigation.

## Consequences

- A change to a token or component changes every page at once.
- A page that needs something new adds it to the system first, or is listed as an exception with its
  reason in the guard test.
- Some freedom is lost on purpose: a one-off colour on one page needs a reason written down.
