# Private Profile

A Flarum extension that lets members hide their profile's post and discussion history from guests and other members. The profile owner and forum administrators retain access.

Sponsored by @rapty88.

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

Enable **Private Profile** in the admin panel. Members can then enable **Make my profile private** on their account settings page.

## Behaviour

- Hides the profile's posts and discussions tabs' content and navigation counters from unauthorized viewers.
- Returns an empty result for author-filtered post and discussion API requests for private profiles.
- Supports Flarum's configured user slug driver, including ID-based slugs.
- Profile owners and administrators can still view the history.
- Public discussion pages, individual posts, and other extensions' profile tabs keep their usual visibility. This extension hides profile history; it does not make published discussions or posts private.

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

## Links

- [Discuss](https://discuss.flarum.org/d/36967)
- [Packagist](https://packagist.org/packages/huseyinfiliz/private-profile)
- [GitHub](https://github.com/huseyinfiliz/private-profile)

Released under the [MIT license](LICENSE).
