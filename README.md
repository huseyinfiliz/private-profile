# Private Profile

A Flarum extension with tiered visibility controls for administrator-selected profile tabs. Includes the features previously provided by Private Profile Plus.

Sponsored by @rapty88.

Private Profile Plus was developed with sponsorship from [LibreTexts](https://libretexts.org).

## Compatibility

| Branch | Flarum | PHP |
| --- | --- | --- |
| `1.x` | 1.x (see that branch's Composer constraint) | See Flarum's requirements |
| `2.x` | ^2.0 (tested on 2.0.2) | ^8.3 |

## Installation

```sh
composer require huseyinfiliz/private-profile:^2.0
php flarum cache:clear
```

For the development branch, use `composer require huseyinfiliz/private-profile:2.x-dev`.

Enable **Private Profile** in the admin panel. The extension page has **Fully supported**, **Profile page only**, and **Settings** tabs. Select the profile pages to protect in the two support tabs, then choose who controls visibility in **Settings**. Posts and discussions are selected by default; other available tabs are opt-in.

### Members decide

Members have a **Make my profile private** switch. The administrator sets who can view the selected pages when that switch is on:

- **Signed-in members** — hide those pages from guests only.
- **Profile owner and authorized staff only** — hide those pages from guests and other members. Owners, administrators and users with bypass permission retain access.

When the switch is off, the selected pages are public. This audience setting controls what opting into privacy means; it is not a default visibility for members who have not made a choice. The member's settings explain the currently configured audience.

### Administrator decides

The administrator enforces one visibility level for everyone:

- **Everyone** — selected pages are public, regardless of personal choices.
- **Members only** — guests see a login prompt; signed-in members can view the selected pages.
- **Private** — only the owner, administrators and users with bypass permission can view the selected pages.

Member settings do not show the privacy switch in this mode. API attempts to change personal privacy preferences are rejected, while unrelated account settings continue to work. Switching modes never rewrites personal privacy choices; returning to **Members decide** restores their effect.

The **Bypass profile visibility restrictions** permission can be assigned to groups on the admin permissions page. It does not grant permissions provided by other extensions.

### Automatic tab discovery and API coverage

Profile tabs are discovered from the forum's actual `/u/:username` JavaScript routes. Navigation labels are used when available; routes without a visible menu entry are also discovered. The account security page is excluded.

Opening the extension's admin page automatically refreshes the catalog through a hidden same-origin forum frame. The **Refresh profile tabs** button at the right of the tab bar repeats discovery after enabling or disabling another extension. New tabs start unselected, and refreshing discovery preserves existing choices. All three tabs share the save/reset controls, so switching tabs preserves unsaved changes.

The settings use two support groups, with a shared explanation at the top of each group:

- **Fully supported** — a server-side adapter protects the supported profile data queries as well as the page.
- **Profile page only** — the page can be hidden, but the extension's data may remain accessible through its API. This applies to automatically discovered integrations without an API adapter and to Byobu's profile tab.

The settings include a link to [request API support in the extension discussion](https://discuss.flarum.org/d/36967-private-profile-extension).

## Behaviour

- Checks access before initializing the selected profile tabs under `/u/:username`. Restricted viewers see the normal profile header (including avatar), navigation and a privacy message in the tab content area.
- A base `UserPage` guard also covers extensions using custom route resolvers.
- Avatars, profile header information and unselected tabs keep their normal visibility. Only navigation counters belonging to protected tabs are hidden from restricted viewers.
- Protects profile-history queries for selected tabs: author-filtered posts/discussions, liked posts, mentions and FoF Gamification votes.
- Server-side adapters for FoF Badges, FoF Upload profile libraries, Rewind snapshots (including standalone HTML views), and Trader Feedback apply only when the corresponding tab is selected.
- Supports Flarum's configured user slug driver, including ID-based slugs.
- Public discussion pages and individual posts retain their usual visibility.

Extensions with separate endpoints can combine `VisibilityResolver::hidesTab($tab)` with `VisibilityResolver::canView($owner, $actor)`. The `canViewProfile` user attribute expresses the viewer's visibility level; clients apply it only to tabs listed in the forum's `profileHiddenTabs` attribute. The `profilePrivacyTabs` attribute lists tab IDs, route suffixes, discovered labels and `apiProtected` coverage. Discovered IDs derive from the route suffix, so label and language changes do not reset selections. Only administrators can persist discovery results; API coverage is assigned on the server.

## Existing installations

- Existing `PrivateProfile = true` and Plus `members`/`private` preferences are treated as privacy enabled, using the administrator's configured private audience.
- Existing public/default preferences keep the switch off. The new `privateProfileEnabled` preference takes precedence once a member uses the switch.
- The previous global visibility setting (or Plus global default if absent) supplies the enforced value in **Administrator decides** mode, not a member-mode default.
- Existing Plus bypass permissions are recognized alongside the new permission.
- Legacy preferences remain stored, and changing modes never alters them.

No data migration is required. Use this extension on Flarum 2 instead of enabling Private Profile Plus alongside it.

## Updating

```sh
composer update huseyinfiliz/private-profile
php flarum cache:clear
```

## Development

```sh
cd js
yarn install
yarn build
yarn check-typings
yarn format-check
```

TypeScript resolves core typings from either an extension-local Composer installation or the surrounding Flarum workbench installation.

### Automated checks and builds

GitHub Actions runs the official Flarum reusable workflows for pushes and pull requests targeting `2.x`:

- **Private Profile JS** checks Prettier and TypeScript, then builds the frontend with Yarn and Node 20. On a `2.x` push, the official build action commits changed distribution files as **flarum-bot**. Pull requests only check/build the code.
- **Private Profile PHP** runs the integration scenarios in an isolated Flarum installation across PHP 8.3–8.5, MySQL, MariaDB, PostgreSQL and SQLite, including prefixed tables.
- **Private Profile 1.x upgrade** installs Flarum 1.8.13 with the published Private Profile v1.1 source, creates public/private/unconfigured profiles and existing content, then upgrades the same database to Flarum 2.0.2 with the current extension. Both plain and prefixed tables are covered. The checks verify that the extension stays enabled, stored preferences and content survive, access remains correct, and the new privacy switch works with migrated users.
- `.styleci.yml` uses the same PHP style configuration as the other Flarum 2 extensions.

For a local isolated SQLite run:

```sh
composer install
DB_DRIVER=sqlite composer test:setup
composer test
```

`test:setup` installs into `tests/integration/tmp` and resets the configured test database. Use a dedicated test database when selecting another driver.

The same integration scenarios can also run against a development forum with this extension enabled:

```sh
FLARUM_ROOT=/path/to/flarum php tests/integration.php
```

The suite covers both control modes, privacy audiences, forced access and API write restrictions, preference preservation across mode changes, selected/unselected tabs, discovery validation, server-controlled API coverage, profile information, roles, bypass groups and legacy preferences/settings. Database fixtures and settings are rolled back at the end.

### Upgrading a Flarum 1.x installation

Update the extension constraint together with the Flarum core constraint so Composer can select the 2.x-compatible extension. While 2.x is a development branch:

```sh
composer require flarum/core:^2.0 huseyinfiliz/private-profile:2.x-dev --with-all-dependencies
php flarum migrate
php flarum cache:clear
```

## Links

- [Discuss](https://discuss.flarum.org/d/36967)
- [Packagist](https://packagist.org/packages/huseyinfiliz/private-profile)
- [GitHub](https://github.com/huseyinfiliz/private-profile)

Released under the [MIT license](LICENSE).
