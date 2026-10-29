<?php

namespace huseyinfiliz\PrivateProfile\Listener;

use Flarum\User\Event\Saving;
use Flarum\User\Exception\PermissionDeniedException;
use huseyinfiliz\PrivateProfile\VisibilityResolver;

class ProtectMemberPrivacy
{
    public function __construct(protected VisibilityResolver $visibility)
    {
    }

    public function __invoke(Saving $event): void
    {
        if ($this->visibility->mode() !== 'admin') {
            return;
        }

        $original = json_decode($event->user->getRawOriginal('preferences') ?? 'null', true) ?: [];
        foreach (['privateProfileEnabled' => null, 'PrivateProfile' => false, 'profileVisibility' => 'default'] as $key => $default) {
            $before = $original[$key] ?? $default;
            $after = $event->user->getPreference($key, $default);
            if ($key !== 'profileVisibility') {
                $before = $before === null ? null : (bool) $before;
                $after = $after === null ? null : (bool) $after;
            }
            // Normal preference saves echo unchanged values; only privacy changes are rejected.
            if ($before !== $after) {
                throw new PermissionDeniedException;
            }
        }
    }
}
