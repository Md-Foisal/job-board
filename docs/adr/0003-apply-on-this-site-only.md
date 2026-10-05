# 3. Applications happen on this site, and are the only way a company sees a candidate

Date: September 2026 (before layer 1)
Status: Accepted

## Context

Many job boards let an employer send people to an outside site to apply. The board then never learns
who applied, so it cannot show a status or expect an answer. Those applications stay unknown forever,
which is the same silence this board is built against.

Some boards also keep a searchable database of candidates for employers to browse. That is a second
product (candidate search, outreach, messaging), and it puts people's profiles in front of companies
they never chose.

## Decision

- Every job is applied to on this site. There is no outside apply link.
- There is no candidate database. A company sees a candidate's profile and CV only through an
  application that candidate sent to that company.
- Employers never rate candidates. Reviews go one way: candidates review the hiring process.

## Consequences

- Some employers will not post here, so there will be fewer jobs at the start.
- In return every application has a timeline, the "Responsive employer" mark is built on real data,
  and review eligibility (an interview, a decision, or 30 days with no reply) can be counted.
- This can be revisited when the board is large: an outside apply button that reports the result back.
