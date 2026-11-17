<?php

// Runs inside integration.php's transaction with the real two-extension API stack.
$publicFeedback = new HuseyinFiliz\TraderFeedback\Models\Feedback;
$publicFeedback->fill(['from_user_id' => $users['author']->id, 'to_user_id' => $users['member']->id,
    'type' => 'positive', 'role' => 'buyer', 'comment' => 'Public feedback integration fixture.', 'is_approved' => true]);
$publicFeedback->save();

$feedbackVisible = function (array $document, bool $expected, string $label) use ($feedback): void {
    $resources = array_merge(array_is_list($document['data']) ? $document['data'] : [$document['data']], $document['included'] ?? []);
    $found = false;
    foreach ($resources as $resource) {
        if (($resource['type'] ?? '') === 'trader-feedbacks' && ($resource['id'] ?? '') === (string) $feedback->id) {
            $found = true;
        }
        if (! $expected) {
            foreach ($resource['relationships'] ?? [] as $relationship) {
                $data = $relationship['data'] ?? [];
                foreach (array_is_list($data) ? $data : [$data] as $linkage) {
                    check(($linkage['type'] ?? '') !== 'trader-feedbacks' || ($linkage['id'] ?? '') !== (string) $feedback->id, "$label removes restricted linkage");
                }
            }
        }
    }
    check($found === $expected, "$label feedback visibility");
    if (! $expected) {
        check(! str_contains(json_encode($document), $feedback->comment), "$label does not disclose the comment");
    }
};

$paths = [
    ['/users/'.$owner->id, ['include' => 'feedbacksReceived']],
    ['/users/'.$users['author']->id, ['include' => 'feedbacksGiven']],
    ['/posts/'.$post->id, ['include' => 'user.feedbacksReceived.fromUser']],
    ['/users/'.$owner->id, ['include' => 'feedbacksReceived', 'fields' => ['trader-feedbacks' => 'comment']]],
    ['/users/'.$users['author']->id, ['include' => 'feedbacksGiven', 'fields' => ['trader-feedbacks' => 'comment']]],
];
foreach ($paths as [$path, $query]) {
    foreach (['guest', 'member', 'owner', 'admin', 'bypass', 'legacy-bypass'] as $role) {
        $allowed = in_array($role, ['owner', 'admin', 'bypass', 'legacy-bypass'], true);
        $document = apiGet($actors[$role], $path, $query);
        $feedbackVisible($document, $allowed, "$role/$path/".json_encode($query));
    }
}

$document = apiGet($users['member'], '/users/'.$users['author']->id, ['include' => 'feedbacksGiven']);
check(in_array((string) $publicFeedback->id, array_column($document['included'] ?? [], 'id'), true), 'Mixed relationship retains public-recipient feedback');
$linkages = $document['data']['relationships']['feedbacksGiven']['data'];
check($linkages === [['type' => 'trader-feedbacks', 'id' => (string) $publicFeedback->id]], 'Mixed relationship retains only the public feedback linkage');

$document = apiGet($users['member'], '/users/'.$owner->id, ['include' => 'groups', 'fields' => ['users' => 'feedbacksReceived']]);
check(Illuminate\Support\Arr::get($document, 'data.relationships.feedbacksReceived.data', []) === [], 'Relationship-only reads do not expose private feedback IDs without an include');
$document = apiGet($users['member'], '/users/'.$owner->id, ['include' => 'feedbacksReceived', 'fields' => ['users' => 'username', 'trader-feedbacks' => 'comment']]);
$feedbackVisible($document, false, 'Sparse primary user without relationship linkage');
$response = $api->withActor($users['member'])->withQueryParams(['include' => 'feedbacksReceived'])->get('/users/'.$owner->id);
check($response->getHeaderLine('Cache-Control') === 'private, no-store', 'Filtered feedback includes are never publicly cached');

$response = $api->withActor($users['member'])->withQueryParams(['fields' => ['trader-feedbacks' => 'comment']])->get('/trader/feedback/'.$feedback->id);
check($response->getStatusCode() === 403, 'Sparse direct feedback cannot omit the privacy owner to evade protection');
$document = apiGet($users['member'], '/trader/feedback', ['fields' => ['trader-feedbacks' => 'comment']]);
$feedbackVisible($document, false, 'Sparse feedback list');

$owner->setPreference('privateProfileEnabled', false);
$owner->save();
$document = apiGet($users['member'], '/users/'.$owner->id, ['include' => 'feedbacksReceived']);
$feedbackVisible($document, true, 'Public recipient control');
$owner->setPreference('privateProfileEnabled', true);
$owner->save();
$document = apiGet($users['member'], '/users/'.$owner->id, ['include' => 'feedbacksReceived']);
$feedbackVisible($document, false, 'Fresh request after revocation');

$settings->set('huseyinfiliz-private-profile.hide_feedbacks', '0');
foreach ($paths as [$path, $query]) {
    $feedbackVisible(apiGet($users['member'], $path, $query), true, 'Unselected tab/'.$path);
}
$settings->set('huseyinfiliz-private-profile.hide_feedbacks', '1');
