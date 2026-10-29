<?php

namespace huseyinfiliz\PrivateProfile;

use Flarum\Extend;
use Flarum\User\User;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Api\Middleware\ThrottleApi;
use huseyinfiliz\PrivateProfile\Middleware\CheckUserPrivateStatus;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Middleware('api'))
        ->insertBefore(ThrottleApi::class, CheckUserPrivateStatus::class),

    (new Extend\User())
        ->registerPreference('PrivateProfile', 'boolval', false),

    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('privateProfile')
                ->get(fn (User $user) => $user->getPreference('PrivateProfile', false)),
        ]),
];
