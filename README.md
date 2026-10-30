# Private Profile

A Flarum extension with tiered visibility controls for administrator-selected profile tabs. Includes the features previously provided by Private Profile Plus.

## Installation

```sh
composer require huseyinfiliz/private-profile:"*"
php flarum cache:clear
```

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


## Updating

```sh
composer update huseyinfiliz/private-profile
php flarum cache:clear
```

## Links

- [Discuss](https://discuss.flarum.org/d/36967)
- [Packagist](https://packagist.org/packages/huseyinfiliz/private-profile)
- [GitHub](https://github.com/huseyinfiliz/private-profile)

Released under the [MIT license](LICENSE).
