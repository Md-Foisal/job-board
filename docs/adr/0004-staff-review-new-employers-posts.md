# 4. Staff review a new employer's job posts first

Date: September 2026 (layer 4)
Status: Accepted

## Context

Fake and scam job posts are the biggest risk on an open job board. They hurt the people who apply,
and they hurt trust in every other post. But a small staff cannot read every post forever.

## Decision

- A company's posts wait in a staff queue before the public sees them, until the company has three
  approved posts and none rejected. After that its posts go straight through.
- That record is read from the moderation log, not from the posts as they are now. Editing or deleting
  posts cannot clean it, and a banned company is never trusted.
- Until a company is trusted, every save of a published post goes back to the queue, edits to an
  approved post included. Otherwise a company could pass review with an honest post and then rewrite it.
- Staff get an email for each post waiting, except staff who work at that company.
- A job post or a company reported by three different people is hidden until staff look. One account
  cannot take something down alone.
- Staff must use two-factor sign-in. Staff do not moderate their own company or their own review.
  Every decision is written to a moderation log with who, what and why, and the log is not edited.

## Consequences

- A new employer waits for a person before the first posts go live. The staff dashboard shows how long
  each queue has been waiting.
- Rules for trust live in one place (`Company::isTrustedPoster()`), and reviewers see the same record
  that decides it.
