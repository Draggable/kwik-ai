# Security Policy

## Supported versions

The latest released version of Kwik AI receives security updates. Please make sure
you are running the most recent release before reporting an issue.

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues,
discussions, or pull requests.**

Instead, report them privately through GitHub's
[private vulnerability reporting](https://github.com/Draggable/kwik-ai/security/advisories/new):

1. Go to the **Security** tab of the repository.
2. Click **Report a vulnerability**.
3. Provide a description of the issue, the affected version(s), and steps to
   reproduce.

We will acknowledge your report as quickly as we can and keep you informed as we
work on a fix. Please give us a reasonable amount of time to address the issue
before any public disclosure.

## Scope

Kwik AI handles user-supplied API keys (stored encrypted) and performs outbound
requests to AI providers and, for the description-from-URL feature, to user-supplied
URLs. Reports involving credential handling, SSRF, request forgery, injection, or
privilege escalation are especially welcome.

Thank you for helping keep Kwik AI and its users safe.
