<?php

namespace huseyinfiliz\PrivateProfile;

use Flarum\Api\Context;
use Flarum\Api\Middleware\ThrottleApi;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;
use huseyinfiliz\PrivateProfile\Middleware\CheckUserPrivateStatus;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/private-profile/tabs/discover', 'private-profile.tabs.discover', Api\DiscoverProfileTabsController::class),

    (new Extend\Middleware('api'))
        ->insertBefore(ThrottleApi::class, CheckUserPrivateStatus::class),

    (new Extend\Middleware('forum'))
        ->add(CheckUserPrivateStatus::class),

    (new Extend\User())
        ->registerPreference('PrivateProfile', 'boolval', false)
        ->registerPreference('privateProfileEnabled', fn ($value) => $value === null ? null : (bool) $value, null)
        ->registerPreference('profileVisibility', [VisibilityResolver::class, 'validatePreference'], 'default'),

    (new Extend\Event())
        ->listen(\Flarum\User\Event\Saving::class, Listener\ProtectMemberPrivacy::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('privateProfile')
                ->get(fn (User $user) => resolve(VisibilityResolver::class)->effective($user) === 'private'),
            Schema\Boolean::make('privateProfileEnabled')
                ->get(fn (User $user) => resolve(VisibilityResolver::class)->memberChoice($user)),
            Schema\Str::make('profileVisibility')
                ->get(fn (User $user) => resolve(VisibilityResolver::class)->preference($user)),
            Schema\Str::make('effectiveProfileVisibility')
                ->get(fn (User $user) => resolve(VisibilityResolver::class)->effective($user)),
            Schema\Boolean::make('canViewProfile')
                ->get(fn (User $user, Context $context) => resolve(VisibilityResolver::class)->canView($user, $context->getActor())),
        ]),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Str::make('profileVisibilityDefault')
                ->get(fn () => resolve(VisibilityResolver::class)->globalDefault()),
            Schema\Str::make('profileVisibilityMode')
                ->get(fn () => resolve(VisibilityResolver::class)->mode()),
            Schema\Str::make('profilePrivacyAudience')
                ->get(fn () => resolve(VisibilityResolver::class)->privateAudience()),
            Schema\Boolean::make('canBypassProfileVisibility')
                ->get(fn ($forum, Context $context) => resolve(VisibilityResolver::class)->canBypass($context->getActor())),
            Schema\Arr::make('profilePrivacyTabs')
                ->get(fn () => resolve(VisibilityResolver::class)->availableTabs()),
            Schema\Arr::make('profileHiddenTabs')
                ->get(fn () => resolve(VisibilityResolver::class)->hiddenTabs()),
        ]),
];
