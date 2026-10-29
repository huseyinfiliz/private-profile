<?php

namespace huseyinfiliz\PrivateProfile;

use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class VisibilityResolver
{
    public const SETTING = 'huseyinfiliz-private-profile.default_visibility';
    public const MODE_SETTING = 'huseyinfiliz-private-profile.visibility_mode';
    public const AUDIENCE_SETTING = 'huseyinfiliz-private-profile.private_visibility';
    public const PERMISSION = 'huseyinfiliz-private-profile.bypassProfileVisibility';
    public const LEVELS = ['everyone', 'members', 'private'];
    public const TABS = [
        'posts' => ['', null],
        'discussions' => ['/discussions', null],
        'likes' => ['/likes', 'flarum-likes'],
        'mentions' => ['/mentions', 'flarum-mentions'],
        'votes' => ['/votes', 'fof-gamification'],
        'badges' => ['/badges', 'fof-badges'],
        'rewind' => ['/rewind', 'huseyinfiliz-rewind'],
        'feedbacks' => ['/feedbacks', 'huseyinfiliz-traderfeedback'],
        'uploads' => ['/uploads', 'fof-upload'],
        'privateDiscussions' => ['/private', 'fof-byobu'],
    ];

    public function __construct(protected SettingsRepositoryInterface $settings, protected ProfileTabRegistry $tabs)
    {
    }

    public function hiddenTabs(): array
    {
        $ids = array_unique([...array_keys(self::TABS), ...array_column($this->tabs->tabs(), 'id')]);

        return array_values(array_filter($ids, fn ($tab) => (bool) $this->settings->get(
            'huseyinfiliz-private-profile.hide_'.$tab,
            in_array($tab, ['posts', 'discussions'], true) ? '1' : '0'
        )));
    }

    public function hidesTab(string $tab): bool
    {
        return in_array($tab, $this->hiddenTabs(), true);
    }

    public function availableTabs(): array
    {
        return $this->tabs->tabs();
    }

    public static function validatePreference(mixed $value): string
    {
        if (! in_array($value, ['default', ...self::LEVELS], true)) {
            throw new ValidationException(['profileVisibility' => 'Invalid profile visibility level.']);
        }

        return $value;
    }

    public function globalDefault(): string
    {
        $value = $this->settings->get(self::SETTING)
            ?? $this->settings->get('huseyinfiliz-private-profile-plus.default_visibility', 'everyone');

        return in_array($value, self::LEVELS, true) ? $value : 'everyone';
    }

    public function mode(): string
    {
        return $this->settings->get(self::MODE_SETTING) === 'admin' ? 'admin' : 'member';
    }

    public function privateAudience(): string
    {
        return $this->settings->get(self::AUDIENCE_SETTING) === 'members' ? 'members' : 'private';
    }

    public function memberChoice(User $user): bool
    {
        $enabled = $user->getPreference('privateProfileEnabled');
        if ($enabled !== null) {
            return (bool) $enabled;
        }

        // Reuse existing opt-in preferences without rewriting them on a mode change.
        return in_array($this->preference($user), ['members', 'private'], true);
    }

    public function preference(User $user): string
    {
        $value = $user->getPreference('profileVisibility', 'default');

        if (in_array($value, self::LEVELS, true)) {
            return $value;
        }

        // Keep existing Private Profile users private until they change their selection.
        return $user->getPreference('PrivateProfile', false) ? 'private' : 'default';
    }

    public function effective(User $user): string
    {
        if ($this->mode() === 'admin') {
            return $this->globalDefault();
        }

        return $this->memberChoice($user) ? $this->privateAudience() : 'everyone';
    }

    public function canBypass(User $actor): bool
    {
        return ! $actor->isGuest() && ($actor->isAdmin()
            || $actor->hasPermission(self::PERMISSION)
            || $actor->hasPermission('huseyinfiliz-private-profile-plus.bypassProfileVisibility'));
    }

    public function canView(User $owner, User $actor): bool
    {
        if ((! $actor->isGuest() && $owner->id === $actor->id) || $this->canBypass($actor)) {
            return true;
        }

        return match ($this->effective($owner)) {
            'everyone' => true,
            'members' => ! $actor->isGuest(),
            default => false,
        };
    }
}
