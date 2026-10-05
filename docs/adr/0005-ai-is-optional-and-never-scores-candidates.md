# 5. AI is optional and never scores candidates

Date: September 2026 (layer 6)
Status: Accepted

## Context

AI helps with slow, boring steps: reading a CV into fields, writing a CV, finding what a job post is
missing. But every call costs money, sends data to a provider, and can be wrong.

In hiring, AI that judges people is treated as high-risk. The EU AI Act lists AI used "to analyse and
filter job applications, and to evaluate candidates" as high-risk
([Annex III, point 4(a)](https://artificialintelligenceact.eu/annex/3/)), and New York City's
[Local Law 144](https://www.nyc.gov/site/dca/about/automated-employment-decision-tools.page) requires a
bias audit for automated employment decision tools.

## Decision

- AI is a helper on top of the product's own rules, never the only path. It is off unless
  `AI_ENABLED=true`, and the product launches with it off.
- The free plan runs no AI. Every plan has a monthly limit per feature in `config/plans.php`, and there is
  no unlimited. Use is counted in the `ai_usages` table.
- AI never scores, ranks or filters candidates for employers. The match score is the product's own
  calculation from skills, and the employer sees the same number the candidate sees. AI only explains
  a match to the candidate.
- Screening company reviews is the platform checking itself, so the platform pays for it. It only
  flags concerns for staff. A person decides, and the queue order does not change.
- Name, email, phone, location, links and photo are not sent to the AI when the task does not need them.
  What the AI writes reaches a profile only when the candidate ticks it.
- The provider is Anthropic (Claude Haiku 4.5) through `laravel/ai`, and can be changed in
  `config/ai.php`. AI calls run on the queue.

## Consequences

- The product is complete with AI off. AI features need a paid plan, which comes with billing after launch.
- No part of the employer side makes an automated decision about a person.
