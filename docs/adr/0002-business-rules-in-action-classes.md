# 2. Business rules in single-purpose action classes

Date: September 2026 (before layer 1)
Status: Accepted

## Context

Many rules touch more than one model. Moving an application to a new stage also writes a timeline
event and emails the candidate. Approving a job post also writes to the moderation log. Put in a
controller or a Livewire page, such a rule is hard to find, hard to test on its own, and easy to copy
when a second screen needs it. Put on a model, it makes the model large and hides side effects where
nobody looks for them.

## Decision

- Each business operation is one class in `app/Actions`, named for what it does (`SaveJobPosting`,
  `ChangeApplicationStage`, `VerifyCompany`). Most have a single `__invoke` method.
- Controllers, Livewire pages and the Filament panel validate input, check a policy, call the action
  and return a response. They make no business decisions themselves.
- Policies decide who may do what. Models keep relationships, simple scopes and casts.
- Search with many filters goes in a custom query builder (`JobPostingQueryBuilder`), not in model scopes.
- No model observers for workflow. A side effect is written inside the action, where it can be read and
  tested. The one exception is clearing the public cache (`app/Observers`): any change to that data must
  clear it, whichever path made the change.
- No data transfer objects for now. Actions take models and validated arrays. They can be added later
  if payloads grow, without breaking anything.

## Consequences

- A class name says what it does, and each action is tested on its own.
- Any screen that needs a rule (a page, the staff panel, a scheduled command) calls the same action,
  so the rule exists once.
- There are more files, each of them small.

Reference: Spatie, [Laravel Beyond CRUD](https://laravel-beyond-crud.com/) (actions and custom query builders).
