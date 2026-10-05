# 1. Companies and memberships, not an employer role on the user

Date: September 2026 (layer 1)
Status: Accepted

## Context

The first version gave each employer account one employer profile, one to one, and a role on the user.
That does not match how hiring works. A company has several people who hire. An agency recruiter posts
for several clients. One person can hire for a company and look for a job at the same time.
With one profile per user, none of this could be described.

## Decision

- A company is its own record. People join it through a membership with a role: owner, manager or member.
- One person can have memberships in several companies.
- Being a candidate is a separate profile on the same account.
- Staff roles (moderator, super admin) sit on the user and never come from a company.
- People join a company through an invitation that is stored, so the team can see pending invitations
  and cancel them.
- The company is part of the workspace URL (`/companies/{slug}/...`). Two companies can be open side by
  side, and a middleware checks for a live membership on every request.

## Consequences

- Teams and agencies work without special cases.
- Every company page needs the membership check, and policies rank the roles so a member cannot act
  above their role.
- The old employer profile and role tables had to be replaced before layer 2
  (commit [946a514](https://github.com/Md-Foisal/job-board/commit/946a514)).
