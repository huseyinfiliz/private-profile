# Security Policy

## Supported versions

Security fixes are maintained on the `2.x` branch for Flarum 2. Use the latest
release from that branch. Older releases and the Flarum 1.x branch are not
actively maintained for security fixes.

## Reporting a vulnerability

Please report suspected vulnerabilities privately to **mysuperuser01@gmail.com**.
Use a subject such as `Private Profile security report` and include:

- The extension and Flarum versions.
- The enabled integrations, relevant privacy settings, and reader permissions.
- Minimal reproduction steps using synthetic accounts and data.
- The expected behavior and the observed security impact.

Do not include passwords, access tokens, database credentials, or other users'
personal data. Please avoid public issues or pull requests containing exploit
details until a fix is available and disclosure has been coordinated.

## Privacy scope

Selected **Fully supported** tabs include server-side protection for supported
profile data queries. **Profile page only** integrations do not promise API data
protection. Normal profile metadata and public forum content remain subject to
their own permissions; hiding a profile tab does not make all related content
confidential.
