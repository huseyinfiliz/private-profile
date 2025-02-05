<?php

namespace huseyinfiliz\PrivateProfile;

use Flarum\Extend;
use Flarum\User\User;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Api\Middleware\ThrottleApi;
use huseyinfiliz\PrivateProfile\Middleware\CheckUserPrivateStatus;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Middleware('api'))
        ->insertBefore(ThrottleApi::class, CheckUserPrivateStatus::class),

    (new Extend\User())
        ->registerPreference('PrivateProfile', 'boolval', false),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attribute('privateProfile', function (UserSerializer $serializer, User $user) {
            return $user->getPreference('PrivateProfile', false);
        }),
];
