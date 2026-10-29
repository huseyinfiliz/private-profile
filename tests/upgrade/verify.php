<?php

use Flarum\Api\Client;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Application;
use Flarum\Http\SlugManager;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;

$phase = $argv[1] ?? '';
$root = getenv('PRIVATE_PROFILE_UPGRADE_ROOT');
if (! in_array($phase, ['legacy', 'current'], true) || ! $root || ! is_file($root.'/.private-profile-upgrade')) {
    throw new RuntimeException('Use a disposable fixture created by upgrade/prepare.php.');
}
$container = (require $root.'/site.php')->bootApp()->getContainer();
$api = $container->make(Client::class);
$checks = 0;

function upgradeCheck(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function upgradeGet(User $actor, string $path, array $query = []): array
{
    global $api;
    $response = $api->withActor($actor)->withQueryParams($query)->get($path);
    upgradeCheck($response->getStatusCode() === 200, "$path returned HTTP ".$response->getStatusCode());

    return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
}

function storedUpgradePreferences(User $user): array
{
    $preferences = json_decode($user->getRawOriginal('preferences') ?? 'null', true) ?: [];
    ksort($preferences);

    return $preferences;
}

upgradeCheck($container->make(ExtensionManager::class)->isEnabled('huseyinfiliz-private-profile'), 'Extension remains enabled');

if ($phase === 'legacy') {
    upgradeCheck(str_starts_with(Application::VERSION, '1.8.'), 'Seed data must be created on Flarum 1.8');
    $state = ['version' => Application::VERSION, 'users' => [], 'content' => []];
    foreach (['private', 'public', 'unset', 'viewer'] as $role) {
        $user = new User;
        $user->username = 'upgrade-'.$role;
        $user->email = $user->username.'@example.invalid';
        $user->password = 'upgrade-test-password';
        $user->is_email_confirmed = true;
        if ($role !== 'unset') {
            $user->setPreference('PrivateProfile', $role === 'private');
            $user->setPreference('discloseOnline', false);
        }
        $user->save();
        $state['users'][$role] = ['id' => $user->id, 'username' => $user->username, 'preferences' => storedUpgradePreferences($user)];
        if ($role === 'viewer') {
            continue;
        }
        $discussion = Discussion::start('Upgrade fixture '.$role, $user);
        $discussion->save();
        $post = new CommentPost;
        $post->discussion_id = $discussion->id;
        $post->user_id = $user->id;
        $post->created_at = Carbon\Carbon::now();
        $post->number = 1;
        $post->setContentAttribute('Existing Flarum 1.x content.', $user);
        $post->save();
        $discussion->first_post_id = $discussion->last_post_id = $post->id;
        $discussion->last_posted_user_id = $user->id;
        $discussion->last_posted_at = $post->created_at;
        $discussion->comment_count = 1;
        $discussion->save();
        $state['content'][$role] = ['discussion' => $discussion->id, 'post' => $post->id];
    }
} else {
    upgradeCheck(Application::VERSION === '2.0.2', 'Upgrade must target Flarum 2.0.2');
    $state = json_decode(file_get_contents($root.'/legacy-state.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($state['users'] as $entry) {
        $user = User::findOrFail($entry['id']);
        upgradeCheck($user->username === $entry['username'], 'Existing user IDs and names are preserved');
        upgradeCheck(storedUpgradePreferences($user) === $entry['preferences'], 'All stored preference values survive core migrations');
    }
    upgradeCheck(
        $container->make(SettingsRepositoryInterface::class)->get('huseyinfiliz-private-profile.visibility_mode') === null,
        'No administrator override is introduced by the upgrade'
    );
}

$owner = User::findOrFail($state['users']['private']['id']);
$viewer = User::findOrFail($state['users']['viewer']['id']);
$admin = User::findOrFail(1);
foreach (['private', 'public', 'unset'] as $role) {
    $user = User::findOrFail($state['users'][$role]['id']);
    $slug = $container->make(SlugManager::class)->forResource(User::class)->toSlug($user);
    $profile = upgradeGet(new Guest, '/users/'.$user->id);
    upgradeCheck($profile['data']['attributes']['privateProfile'] === ($role === 'private'), "$phase/$role privacy flag");
    foreach (['posts', 'discussions'] as $resource) {
        $document = upgradeGet(new Guest, '/'.$resource, ['filter' => ['author' => $slug]]);
        upgradeCheck(
            $role === 'private' ? $document['data'] === [] : count($document['data']) > 0,
            "$phase/$role/$resource guest access"
        );
    }
}
$slug = $container->make(SlugManager::class)->forResource(User::class)->toSlug($owner);
foreach (['owner' => $owner, 'admin' => $admin, 'member' => $viewer] as $role => $actor) {
    foreach (['posts', 'discussions'] as $resource) {
        $document = upgradeGet($actor, '/'.$resource, ['filter' => ['author' => $slug]]);
        upgradeCheck(
            $role === 'member' ? $document['data'] === [] : count($document['data']) > 0,
            "$phase/private/$resource/$role access"
        );
    }
}

if ($phase === 'legacy') {
    file_put_contents($root.'/legacy-state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} else {
    upgradeGet(new Guest, '/posts/'.$state['content']['private']['post']);
    upgradeGet(new Guest, '/discussions/'.$state['content']['private']['discussion']);
    foreach ([false, true] as $enabled) {
        $response = $api->withActor($owner)->withBody(['data' => ['type' => 'users', 'id' => (string) $owner->id,
            'attributes' => ['preferences' => ['privateProfileEnabled' => $enabled]]]])->patch('/users/'.$owner->id);
        upgradeCheck($response->getStatusCode() === 200, 'Upgraded member can use the new privacy switch');
        $fresh = User::findOrFail($owner->id);
        upgradeCheck($fresh->getPreference('PrivateProfile') === true, 'Legacy privacy choice remains stored');
        upgradeCheck($fresh->getPreference('privateProfileEnabled') === $enabled, 'New member preference saves correctly');
        $document = upgradeGet(new Guest, '/posts', ['filter' => ['author' => $slug]]);
        upgradeCheck($enabled ? $document['data'] === [] : count($document['data']) > 0, 'New switch controls the existing content after upgrade');
    }
}

echo "PASS: $checks $phase upgrade assertions on Flarum ".Application::VERSION."\n";
