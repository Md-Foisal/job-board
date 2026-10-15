# 9. Staff sign in through the one login, with two-factor required

Date: October 2026 (layer 9)
Status: Accepted

## Context

Staff run the admin panel at `/admin`: they hide postings, ban companies and suspend people, so a
stolen staff session is worth far more than an ordinary one. Security guidance asks that the
management interface be separated from the user interface and use its own authentication. The
DISA application STIG rule V-222574 says the user interface "must be either physically or logically
separated from data storage and management interfaces", and NIST SP 800-53 control SC-2 asks for the
same, with web admin panels on their own domain or subdomain.

Three ways to meet that were open:

1. One sign-in for everyone. The panel sits behind a staff role and requires two-factor.
2. A separate admin sign-in on its own subdomain, with its own session.
3. The panel reachable only over a VPN or a private network.

## Decision

Option 1. Staff sign in through the application's own login, the same screen as everyone else.

- The panel has no sign-in screen of its own. A second one would sit beside Fortify and skip its
  two-factor challenge (`AdminPanelProvider`).
- Only active staff may enter the panel (`User::canAccessPanel()`). A staff member who has not
  finished setting up two-factor is sent to the security page instead (`EnsureStaffHasTwoFactor`,
  which runs after the panel's own authentication).
- There is no way to become staff from the website. The first super admin is promoted from the
  command line on the server (`php artisan staff:grant`), and a super admin makes moderators from the
  panel. Whoever holds the server is the owner.

## Consequences

- The standards are not met to the letter: the user interface and the management interface share a
  domain, a login screen and a session cookie. What stands in for separation is a staff role checked
  on every panel request, mandatory two-factor, and no self-service route to a staff role.
- It fits what we run: one service on free hosting with one domain, no second environment and no
  private network. A subdomain would be a second deployment to keep in step, and a VPN needs a server
  we do not have yet.
- Revisit when the product moves to its own server, or when the staff grows beyond a handful of
  people: put the panel on a private network or its own subdomain, with its own session.
