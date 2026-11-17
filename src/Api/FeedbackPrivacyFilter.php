<?php

namespace huseyinfiliz\PrivateProfile\Api;

use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use huseyinfiliz\PrivateProfile\VisibilityResolver;
use HuseyinFiliz\TraderFeedback\Models\Feedback;

class FeedbackPrivacyFilter
{
    public function __construct(protected VisibilityResolver $visibility)
    {
    }

    public function filter(array $body, User $actor): array
    {
        if (! $this->visibility->hidesTab('feedbacks') || ! class_exists(Feedback::class)) {
            return $body;
        }

        $collection = array_is_list($body['data']);
        $primary = $collection ? $body['data'] : [$body['data']];
        $resources = array_merge($primary, $body['included'] ?? []);
        $ids = [];
        foreach ($resources as $resource) {
            $this->collectId($resource, $ids);
            foreach ($resource['relationships'] ?? [] as $relationship) {
                $data = $relationship['data'] ?? [];
                foreach (array_is_list($data) ? $data : [$data] as $linkage) {
                    $this->collectId($linkage, $ids);
                }
            }
        }
        if (! $ids) {
            return $body;
        }

        // Ownership must not depend on attributes selected by the API caller.
        $feedbacks = Feedback::whereIn('id', array_keys($ids))->get(['id', 'to_user_id']);
        $owners = User::whereIn('id', $feedbacks->pluck('to_user_id')->unique())->get()->keyBy('id');
        $denied = $ids;
        foreach ($feedbacks as $feedback) {
            $owner = $owners->get($feedback->to_user_id);
            if ($owner && $this->visibility->canView($owner, $actor)) {
                unset($denied[$feedback->id]);
            }
        }
        if (! $denied) {
            return $body;
        }

        if (! $collection && $this->isDenied($body['data'], $denied)) {
            throw new PermissionDeniedException;
        }

        $filterResources = function (array $resources) use ($denied): array {
            $result = [];
            foreach ($resources as $resource) {
                if ($this->isDenied($resource, $denied)) {
                    continue;
                }
                foreach ($resource['relationships'] ?? [] as $name => $relationship) {
                    if (! isset($relationship['data'])) {
                        continue;
                    }
                    $data = $relationship['data'];
                    $resource['relationships'][$name]['data'] = array_is_list($data)
                        ? array_values(array_filter($data, fn ($linkage) => ! $this->isDenied($linkage, $denied)))
                        : ($this->isDenied($data, $denied) ? null : $data);
                }
                $result[] = $resource;
            }

            return $result;
        };

        $primary = $filterResources($primary);
        $body['data'] = $collection ? $primary : $primary[0];
        if (isset($body['included'])) {
            $body['included'] = $filterResources($body['included']);
        }

        return $body;
    }

    private function collectId(array $resource, array &$ids): void
    {
        if (($resource['type'] ?? '') === 'trader-feedbacks' && isset($resource['id'])) {
            $ids[$resource['id']] = true;
        }
    }

    private function isDenied(array $resource, array $denied): bool
    {
        return ($resource['type'] ?? '') === 'trader-feedbacks' && isset($denied[$resource['id'] ?? '']);
    }
}
