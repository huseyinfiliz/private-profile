<?php

// CI uses --isolated and Flarum's test installation. For a development forum:
// FLARUM_ROOT=/path/to/flarum php tests/integration.php
// Every scenario fixture and setting change is rolled back.

use Flarum\Api\Client;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Group\Permission;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;
use huseyinfiliz\PrivateProfile\ProfileTabRegistry;
use huseyinfiliz\PrivateProfile\VisibilityResolver;

if (in_array('--isolated', $argv ?? [], true)) {
    require __DIR__.'/bootstrap.php';
    $bootstrapper = new Flarum\Testing\integration\Setup\Bootstrapper(
        ['env' => 'testing'],
        ['huseyinfiliz-private-profile']
    );
    $container = $bootstrapper->run()->bootApp()->getContainer();
} else {
    $root = getenv('FLARUM_ROOT') ?: dirname(__DIR__, 3);
    $container = (require $root.'/site.php')->bootApp()->getContainer();
}
$db = $container->make('db')->connection();
$api = $container->make(Client::class);
$settings = $container->make(SettingsRepositoryInterface::class);
$resolver = $container->make(VisibilityResolver::class);
$checks = 0;

function check(bool $condition, string $label): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($label);
    }
    $checks++;
}

function apiGet(User $actor, string $path, array $query = []): array
{
    global $api;
    $response = $api->withActor($actor)->withQueryParams($query)->get($path);
    check($response->getStatusCode() === 200, "$path HTTP ".$response->getStatusCode());

    return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
}

function apiPreferences(User $user, array $preferences): Psr\Http\Message\ResponseInterface
{
    global $api;

    return $api->withActor($user)->withQueryParams([])->withBody(['data' => [
        'type' => 'users', 'id' => (string) $user->id, 'attributes' => ['preferences' => $preferences],
    ]])->patch('/users/'.$user->id);
}

$db->beginTransaction();

try {
    $settings->set(VisibilityResolver::SETTING, 'everyone');
    $settings->set(VisibilityResolver::MODE_SETTING, 'member');
    $settings->set(VisibilityResolver::AUDIENCE_SETTING, 'private');
    $settings->delete(ProfileTabRegistry::SETTING);
    foreach (array_keys(VisibilityResolver::TABS) as $tab) {
        $settings->delete('huseyinfiliz-private-profile.hide_'.$tab);
    }
    check($resolver->hiddenTabs() === ['posts', 'discussions'], 'Only posts and discussions protected by default');
    foreach (array_keys(VisibilityResolver::TABS) as $tab) {
        $settings->set('huseyinfiliz-private-profile.hide_'.$tab, '1');
    }
    $users = [];
    foreach (['owner', 'member', 'admin', 'bypass', 'legacy-bypass'] as $role) {
        $user = new User;
        $user->username = 'pp-test-'.bin2hex(random_bytes(6));
        $user->email = $user->username.'@example.invalid';
        $user->password = bin2hex(random_bytes(20));
        $user->is_email_confirmed = true;
        $user->save();
        if ($role === 'admin') {
            $user->groups()->attach(Group::ADMINISTRATOR_ID);
        } elseif (in_array($role, ['bypass', 'legacy-bypass'], true)) {
            $group = new Group;
            $group->name_singular = $group->name_plural = $user->username;
            $group->save();
            Permission::insert(['group_id' => $group->id, 'permission' => $role === 'bypass'
                ? VisibilityResolver::PERMISSION : 'huseyinfiliz-private-profile-plus.bypassProfileVisibility']);
            $user->groups()->attach($group);
        }
        $users[$role] = $user;
    }
    $actors = ['guest' => new Guest] + $users;
    $owner = $users['owner'];
    $discoveryPath = '/private-profile/tabs/discover';
    $discovery = ['tabs' => [
        ['path' => '', 'label' => 'Posts', 'id' => 'forged', 'apiProtected' => false],
        ['path' => '/third-party-test', 'label' => 'Third-party test', 'apiProtected' => true],
    ]];
    $response = $api->withActor($users['member'])->withBody($discovery)->post($discoveryPath);
    check($response->getStatusCode() === 403, 'Only administrators can persist discovered tabs');
    $response = $api->withActor($users['admin'])->withBody($discovery)->post($discoveryPath);
    check($response->getStatusCode() === 200, 'Administrator can persist discovered tabs');
    $tabs = json_decode((string) $response->getBody(), true)['tabs'];
    $unknownId = ProfileTabRegistry::automaticId('/third-party-test');
    $unknown = array_values(array_filter($tabs, fn ($tab) => $tab['id'] === $unknownId));
    check(count($unknown) === 1 && $unknown[0]['apiProtected'] === false, 'Browser cannot claim API support for an unknown tab');
    $posts = array_values(array_filter($tabs, fn ($tab) => $tab['path'] === ''));
    check(count($posts) === 1 && $posts[0]['id'] === 'posts' && $posts[0]['apiProtected'] === true, 'Known tab IDs and API support remain server-controlled');
    check(! $resolver->hidesTab($unknownId), 'Newly discovered tabs are not selected');
    $settings->set('huseyinfiliz-private-profile.hide_'.$unknownId, '1');
    check($resolver->hidesTab($unknownId), 'Discovered tabs can be selected');
    $api->withActor($users['admin'])->withBody(['tabs' => [['path' => '/third-party-test', 'label' => 'Renamed tab']]])->post($discoveryPath);
    check($resolver->hidesTab($unknownId), 'Discovery refresh and label changes preserve selections');
    foreach ([['path' => '/security', 'label' => 'Account security'], ['path' => 'https://example.invalid', 'label' => 'External'], ['path' => '/bad', 'label' => str_repeat('x', 121)]] as $invalid) {
        $response = $api->withActor($users['admin'])->withBody(['tabs' => [$invalid]])->post($discoveryPath);
        check($response->getStatusCode() === 422, 'Invalid discovery payload rejected');
    }
    $settings->delete(ProfileTabRegistry::SETTING);
    $settings->delete('huseyinfiliz-private-profile.hide_'.$unknownId);
    // Rewind moderation alone must not grant a profile-privacy bypass.
    $rewindGroup = new Group;
    $rewindGroup->name_singular = $rewindGroup->name_plural = 'Private Profile integration moderator';
    $rewindGroup->save();
    Permission::insert(['group_id' => $rewindGroup->id, 'permission' => 'huseyinfiliz-rewind.moderate']);
    $users['member']->groups()->attach($rewindGroup);
    $discussion = Discussion::start('Private Profile integration test', $owner);
    $discussion->save();
    $post = new CommentPost;
    $post->discussion_id = $discussion->id;
    $post->user_id = $owner->id;
    $post->created_at = Carbon\Carbon::now();
    $post->number = 1;
    $post->setContentAttribute('Profile visibility integration fixture.', $owner);
    $post->save();
    $discussion->first_post_id = $discussion->last_post_id = $post->id;
    $discussion->last_posted_user_id = $owner->id;
    $discussion->last_posted_at = $post->created_at;
    $discussion->comment_count = 1;
    $discussion->save();
    $slug = $container->make(Flarum\Http\SlugManager::class)->forResource(User::class)->toSlug($owner);

    foreach (VisibilityResolver::LEVELS as $level) {
        $settings->set(VisibilityResolver::AUDIENCE_SETTING, $level === 'members' ? 'members' : 'private');
        $owner->setPreference('privateProfileEnabled', $level !== 'everyone');
        $owner->save();
        foreach ($actors as $role => $actor) {
            $allowed = $level === 'everyone' || ($level === 'members' && $role !== 'guest')
                || in_array($role, ['owner', 'admin', 'bypass', 'legacy-bypass'], true);
            check($resolver->canView($owner, $actor) === $allowed, "$level/$role resolver");
            $profile = apiGet($actor, '/users/'.$owner->id, ['include' => 'groups']);
            check($profile['data']['attributes']['canViewProfile'] === $allowed, "$level/$role API decision");
            check(isset($profile['data']['relationships']['groups']), "$level/$role profile groups preserved");
            check(array_key_exists('avatarUrl', $profile['data']['attributes']), "$level/$role avatar preserved");
            check(array_key_exists('commentCount', $profile['data']['attributes']), "$level/$role normal profile metadata preserved");
            foreach (['posts', 'discussions'] as $resource) {
                $results = apiGet($actor, '/'.$resource, ['filter' => ['author' => $slug]]);
                check($allowed ? count($results['data']) > 0 : $results['data'] === [], "$level/$role/$resource");
            }
        }
    }

    $owner->setPreference('profileVisibility', 'default');
    $owner->setPreference('privateProfileEnabled', null);
    $owner->setPreference('PrivateProfile', true);
    $owner->save();
    check($resolver->effective($owner) === 'private', 'Legacy boolean preference remains private');
    $owner->setPreference('PrivateProfile', false);
    $owner->save();
    $settings->set(VisibilityResolver::SETTING, 'private');
    check($resolver->effective($owner) === 'everyone', 'Member mode does not apply administrator visibility as a default');
    foreach (['members', 'private'] as $legacyLevel) {
        $owner->setPreference('profileVisibility', $legacyLevel);
        $owner->save();
        check($resolver->memberChoice($owner), "Legacy Plus $legacyLevel preference enables privacy");
        check($resolver->effective($owner) === 'private', 'Administrator chooses the scope of legacy privacy opt-ins');
    }
    $owner->setPreference('profileVisibility', 'everyone');
    $owner->save();
    check(! $resolver->memberChoice($owner), 'Legacy public preference keeps privacy off');
    $owner->setPreference('profileVisibility', 'default');
    $owner->setPreference('PrivateProfile', true);
    $owner->save();
    $response = apiPreferences($owner, ['privateProfileEnabled' => null, 'locale' => 'en']);
    check($response->getStatusCode() === 200, 'Unchanged nullable preference can be echoed by a normal save');
    check($resolver->memberChoice(User::findOrFail($owner->id)), 'Echoing null does not disable a legacy private profile');

    $owner->setPreference('privateProfileEnabled', true);
    $owner->setPreference('profileVisibility', 'private');
    $owner->save();
    // Read the stored representation: MySQL normalizes JSON when persisting it.
    $originalPrivacy = $owner->fresh()->getRawOriginal('preferences');
    $settings->set(VisibilityResolver::MODE_SETTING, 'admin');
    foreach (VisibilityResolver::LEVELS as $level) {
        $settings->set(VisibilityResolver::SETTING, $level);
        foreach ($actors as $role => $actor) {
            $allowed = $level === 'everyone' || ($level === 'members' && $role !== 'guest')
                || in_array($role, ['owner', 'admin', 'bypass', 'legacy-bypass'], true);
            check($resolver->effective($owner) === $level, "Admin mode enforces $level regardless of the member choice");
            $profile = apiGet($actor, '/users/'.$owner->id);
            check($profile['data']['attributes']['effectiveProfileVisibility'] === $level, "Admin $level/$role effective API visibility");
            check($profile['data']['attributes']['canViewProfile'] === $allowed, "Admin $level/$role access");
            check($profile['data']['attributes']['privateProfileEnabled'] === true, 'Forced visibility preserves the saved member choice');
            foreach (['posts', 'discussions'] as $resource) {
                $document = apiGet($actor, '/'.$resource, ['filter' => ['author' => $slug]]);
                check($allowed ? count($document['data']) > 0 : $document['data'] === [], "Admin $level/$role/$resource");
            }
        }
        check(User::findOrFail($owner->id)->getRawOriginal('preferences') === $originalPrivacy, 'Administrator changes do not rewrite member preferences');
    }
    foreach ([['privateProfileEnabled' => false], ['PrivateProfile' => false], ['profileVisibility' => 'everyone']] as $change) {
        $response = apiPreferences($owner, $change);
        check($response->getStatusCode() === 403, 'Personal privacy changes are rejected through API in administrator mode');
        check(User::findOrFail($owner->id)->getRawOriginal('preferences') === $originalPrivacy, 'Rejected privacy changes leave stored choices untouched');
    }
    $response = apiPreferences($owner, array_merge($owner->preferences, ['discloseOnline' => false]));
    check($response->getStatusCode() === 200, 'Other account settings still save when unchanged privacy preferences are echoed');
    check(User::findOrFail($owner->id)->getPreference('privateProfileEnabled') === true, 'Normal account saves preserve the member privacy switch');
    $owner->refresh();
    $owner->setPreference('privateProfileEnabled', false);
    $owner->save();
    check($resolver->effective($owner) === 'private', 'A saved public choice cannot override enforced private visibility');
    check(apiGet($users['member'], '/posts', ['filter' => ['author' => $slug]])['data'] === [], 'Enforced private history stays hidden for a member with privacy switched off');
    $settings->delete(VisibilityResolver::SETTING);
    $settings->set('huseyinfiliz-private-profile-plus.default_visibility', 'members');
    check($resolver->effective($owner) === 'members', 'Administrator mode reuses the Plus global visibility setting');
    $settings->set(VisibilityResolver::SETTING, 'everyone');
    check($resolver->effective($owner) === 'everyone', 'Current administrator visibility takes precedence over the Plus setting');
    $settings->set(VisibilityResolver::MODE_SETTING, 'member');
    check($resolver->effective($owner) === 'everyone', 'Returning to member mode restores the saved public choice');
    $owner->setPreference('privateProfileEnabled', true);
    $owner->save();
    foreach (['members', 'private'] as $audience) {
        $settings->set(VisibilityResolver::AUDIENCE_SETTING, $audience);
        check($resolver->effective($owner) === $audience, "Member privacy follows the administrator's $audience scope");
    }
    $beforeModes = $owner->fresh()->getRawOriginal('preferences');
    $settings->set(VisibilityResolver::MODE_SETTING, 'admin');
    $settings->set(VisibilityResolver::SETTING, 'everyone');
    check($resolver->effective($owner) === 'everyone', 'Administrator can make an opted-in member public');
    $settings->set(VisibilityResolver::MODE_SETTING, 'member');
    check($resolver->effective($owner) === 'private', 'Returning to member mode restores the saved private choice');
    check(User::findOrFail($owner->id)->getRawOriginal('preferences') === $beforeModes, 'Round-trip mode changes preserve member preferences byte-for-byte');

    $owner->setPreference('profileVisibility', 'private');
    $owner->save();
    $settings->set('huseyinfiliz-private-profile.hide_posts', '0');
    check(count(apiGet($users['member'], '/posts', ['filter' => ['author' => $slug]])['data']) > 0, 'Unselected posts tab remains public');
    check(apiGet($users['member'], '/discussions', ['filter' => ['author' => $slug]])['data'] === [], 'Selected discussions tab remains protected');
    $settings->set('huseyinfiliz-private-profile.hide_posts', '1');
    foreach ([$slug, '"'.$slug.'"', [$slug], $slug.','.$users['member']->username] as $value) {
        check(apiGet($users['member'], '/posts', ['filter' => ['author' => $value]])['data'] === [], 'Author formats enforce privacy');
    }
    foreach (['likedBy', 'mentioned', 'voted'] as $key) {
        check(apiGet($users['member'], '/posts', ['filter' => [$key => (string) $owner->id]])['data'] === [], "$key profile history protected");
    }
    foreach (['/user-badges', '/rw-snaps', '/trader/feedback', '/fof/uploads'] as $path) {
        // These optional integrations are exercised when installed in the test forum.
        $response = $api->withActor($users['member'])->withQueryParams(['filter' => ['user' => (string) $owner->id]])->get($path);
        if ($response->getStatusCode() !== 404) {
            check($response->getStatusCode() === 200, "$path protected list status");
            check(json_decode((string) $response->getBody(), true)['data'] === [], "$path protected list data");
        }
    }
    $response = $api->withActor($users['member'])->withQueryParams([])->get('/trader/stats/'.$owner->id);
    if ($response->getStatusCode() !== 404) {
        check($response->getStatusCode() === 403, 'Direct Trader Feedback stats URL protected');
    }
    if (class_exists(HuseyinFiliz\Rewind\Model\RewindSnapshot::class)) {
        $snapshot = new HuseyinFiliz\Rewind\Model\RewindSnapshot;
        $snapshot->fill(['user_id' => $owner->id, 'year' => 2025, 'data' => ['privateProfileFixture' => true],
            'generated_at' => Carbon\Carbon::now(), 'is_public' => true]);
        $snapshot->save();
        $document = apiGet($users['admin'], '/rw-snaps/'.$snapshot->id);
        check($document['data']['id'] === (string) $snapshot->id, 'Rewind fixture exists and is readable by administrator');
        $response = $api->withActor($users['member'])->withQueryParams([])->get('/rw-snaps/'.$snapshot->id);
        check($response->getStatusCode() === 403, 'Direct Rewind snapshot URL protected despite Rewind moderation permission');
        $document = apiGet($users['member'], '/rw-snaps');
        check(! in_array((string) $snapshot->id, array_column($document['data'], 'id'), true), 'Unfiltered Rewind list removes restricted snapshots');
        $settings->set('huseyinfiliz-private-profile.hide_rewind', '0');
        $document = apiGet($users['member'], '/rw-snaps/'.$snapshot->id);
        check($document['data']['id'] === (string) $snapshot->id, 'Unselected Rewind tab retains its own access rules');
        $settings->set('huseyinfiliz-private-profile.hide_rewind', '1');
    }
    if (class_exists(HuseyinFiliz\TraderFeedback\Models\Feedback::class)) {
        $feedback = new HuseyinFiliz\TraderFeedback\Models\Feedback;
        $feedback->fill(['from_user_id' => $users['member']->id, 'to_user_id' => $owner->id,
            'type' => 'positive', 'role' => 'buyer', 'comment' => 'Profile visibility integration fixture.', 'is_approved' => true]);
        $feedback->save();
        $document = apiGet($users['admin'], '/trader/feedback/'.$feedback->id);
        check($document['data']['id'] === (string) $feedback->id, 'Feedback fixture exists and is readable by administrator');
        $response = $api->withActor($users['member'])->withQueryParams([])->get('/trader/feedback/'.$feedback->id);
        check($response->getStatusCode() === 403, 'Direct feedback URL protected');
        $document = apiGet($users['member'], '/trader/feedback');
        check(! in_array((string) $feedback->id, array_column($document['data'], 'id'), true), 'Unfiltered feedback list removes restricted profile feedback');
        $settings->set('huseyinfiliz-private-profile.hide_feedbacks', '0');
        $document = apiGet($users['member'], '/trader/feedback/'.$feedback->id);
        check($document['data']['id'] === (string) $feedback->id, 'Unselected feedback tab remains accessible');
        $settings->set('huseyinfiliz-private-profile.hide_feedbacks', '1');
    }
    apiGet(new Guest, '/discussions/'.$discussion->id);
    apiGet(new Guest, '/posts/'.$post->id);
    $postDocument = apiGet(new Guest, '/posts/'.$post->id, ['include' => 'user']);
    $includedOwner = array_values(array_filter($postDocument['included'] ?? [], fn ($resource) => $resource['type'] === 'users' && $resource['id'] === (string) $owner->id));
    check(count($includedOwner) === 1 && $includedOwner[0]['attributes']['canViewProfile'] === false, 'Included users retain privacy decision');
    check(isset($includedOwner[0]['attributes']['commentCount']), 'Included users retain normal profile metadata');

    $response = $api->withActor($owner)->withBody(['data' => ['type' => 'users', 'id' => (string) $owner->id,
        'attributes' => ['preferences' => ['privateProfileEnabled' => false]]]])->patch('/users/'.$owner->id);
    check($response->getStatusCode() === 200, 'Owner can update visibility through API');
    check(User::findOrFail($owner->id)->getPreference('privateProfileEnabled') === false, 'Member switch persisted');
    check($resolver->effective(User::findOrFail($owner->id)) === 'everyone', 'Switching privacy off makes selected pages public despite legacy private preferences');
    $response = $api->withActor($owner)->withBody(['data' => ['type' => 'users', 'id' => (string) $owner->id,
        'attributes' => ['preferences' => ['profileVisibility' => 'invalid']]]])->patch('/users/'.$owner->id);
    check($response->getStatusCode() === 422, 'Invalid preference rejected');
    echo "PASS: $checks integration assertions on Flarum ".Flarum\Foundation\Application::VERSION."\n";
} finally {
    $db->rollBack();
    echo "Fixtures and setting changes rolled back.\n";
}
