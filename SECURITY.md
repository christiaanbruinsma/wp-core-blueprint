# Security Policy

Core Blueprint takes security reports seriously, especially issues that could affect authentication, authorization, privileged operations, data integrity, filesystem safety, or privacy.

## Supported versions

Core Blueprint Base is currently in its pre-v1 release-candidate phase.

Security fixes are provided for the latest published or otherwise designated current release candidate. Older internal or superseded release candidates are not maintained as supported security branches.

## Reporting a vulnerability

Please **do not disclose suspected vulnerabilities in a public GitHub issue, discussion, pull request, or other public channel**.

Preferred reporting route:

1. Use GitHub's **private vulnerability reporting** feature for this repository when it is available.
2. Include enough information to reproduce and assess the issue, such as affected version, prerequisites, impact, reproduction steps, and relevant logs or screenshots with secrets removed.
3. Do not include passwords, API keys, application passwords, private keys, session cookies, access tokens, personal data, or other secrets in the report.

If GitHub private vulnerability reporting is not available, open a public issue **without vulnerability details** asking the maintainer for a private reporting channel. Do not include exploit steps, proof-of-concept payloads, sensitive logs, or other security-sensitive details in that public issue.

## What to expect

Security reports will be reviewed and triaged based on practical impact, affected trust boundaries, exploitability, and supported configurations.

When a report is confirmed, the project will aim to coordinate remediation and disclosure proportionately to the severity and risk. A public advisory or release note may follow after an appropriate fix is available.

Please allow a reasonable opportunity to investigate and remediate a reported issue before public disclosure.

## Scope

Examples of security-relevant areas include:

- authentication and authorization bypasses;
- privilege escalation or capability-boundary failures;
- CSRF or permission failures in privileged actions;
- arbitrary code execution or unsafe snippet execution outside the intended trust model;
- path traversal, unsafe filesystem access, or quarantine/restore boundary failures;
- sensitive-data exposure or insufficient redaction;
- security logging or governance failures that materially weaken detection or accountability;
- data corruption or destructive operations outside their intended authorization boundary.

General bugs, feature requests, documentation corrections, and non-security support questions should use the normal project issue workflow.

## Good-faith research

Good-faith security research is welcome when it avoids privacy violations, data destruction, service disruption, social engineering, credential theft, or access to systems or data beyond what is necessary to demonstrate the issue.
