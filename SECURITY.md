# Security Policy

Thank you for helping keep Chatvia and its users safe.

## Supported versions

We fix security problems only in the latest code on the `main` branch. Please
check that the problem still exists on `main` before you report it. Older
releases and tags do not get security fixes.

| Version | Supported |
|---|---|
| Latest `main` | Yes |
| Anything older | No |

## How to report a vulnerability

**Please do not open a public issue, discussion or pull request for a security
problem.** A public report puts every Chatvia installation at risk before a fix
is ready.

Report it privately on GitHub instead:

1. Open the **Security** tab of this repository.
2. Click **Report a vulnerability**.
3. Fill in the form. Only the maintainers can see it.

This uses GitHub's private vulnerability reporting. If you cannot use it, open
a public issue that says only "I want to report a security problem, please
contact me" — with no details — and a maintainer will reach out.

## What to include

The more of this you can give, the faster we can help:

- What kind of problem it is (for example: SQL injection, broken access
  control, SSRF, cross-site scripting).
- The affected file(s), route(s) or feature, and the commit you tested.
- Clear steps to reproduce it, and a proof of concept if you have one.
- What an attacker could do with it (the impact).
- Any setup that is needed (configuration, environment variables, user roles).
- Whether you want to be credited, and under which name.

Please test only against your **own** installation. Do not test against
installations you do not own, do not access or change other people's data,
and do not run attacks that slow down or break a service (denial of service,
spam, social engineering).

## What happens next

- We aim to confirm that we received your report within **5 working days**.
- We will tell you if we can reproduce the problem, and keep you updated while
  we work on a fix.
- When the fix is ready, we publish it and a GitHub security advisory. We will
  credit you in the advisory if you want.
- Please keep the details private until the fix is public. We will agree on a
  disclosure date with you; our goal is to fix confirmed problems within 90
  days.

Chatvia is maintained by a small team, so these times are goals, not promises.
We take every report seriously.
