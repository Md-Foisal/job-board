# 7. Pay is compared only within one currency

Date: October 2026 (layer 7)
Status: Accepted

## Context

The board is not tied to one country, so posts list pay in many currencies. A sort on the plain number
put ¥300,000 a month above $5,000 a month, although it is worth much less. Converting with exchange
rates would need a live rate source and would still drift from what the employer meant.

## Decision

- The pay currency must be an ISO 4217 code that is legal tender somewhere today, checked against CLDR
  data from Symfony Intl.
- A post that shows a pay figure must give its currency and period. A negotiable post shows no figure.
- Pay filters and pay sorting work only after a currency is chosen, and compare only posts in that
  currency. A pay figure without a currency is ignored in search and alerts.
- There is no exchange-rate conversion.
- A new post starts with the company's last used currency. There is no default currency.

## Consequences

- Someone looking at pay in two currencies searches twice.
- Pay order is never wrong because of the currency.
