<?php

namespace huseyinfiliz\PrivateProfile\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use huseyinfiliz\PrivateProfile\VisibilityResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckUserPrivateStatus implements MiddlewareInterface
{
    private const FILTERS = [
        'posts.index' => ['author' => ['slug', 'posts'], 'likedBy' => ['usernameOrId', 'likes'], 'mentioned' => ['usernameOrId', 'mentions'], 'voted' => ['id', 'votes']],
        'discussions.index' => ['author' => ['slug', 'discussions']],
        'user-badges.index' => ['user' => ['id', 'badges']],
        'rw-snaps.index' => ['user' => ['id', 'rewind']],
        'trader.feedback.index' => ['user' => ['id', 'feedbacks']],
        'fof-upload.list' => ['user' => ['id', 'uploads']],
        'files.index' => ['user' => ['id', 'uploads']],
    ];

    public function __construct(protected SlugManager $slugManager, protected VisibilityResolver $visibility)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }

        $actor = RequestUtil::getActor($request);
        if ($this->visibility->canBypass($actor)) {
            return $handler->handle($request);
        }

        $route = $request->getAttribute('routeName');
        foreach (self::FILTERS[$route] ?? [] as $key => [$driver, $tab]) {
            if (! $this->visibility->hidesTab($tab)) {
                continue;
            }
            $value = Arr::get($request->getQueryParams(), 'filter.'.$key);
            foreach (is_array($value) ? $value : (is_scalar($value) ? explode(',', (string) $value) : []) as $identifier) {
                $owner = is_string($identifier) || is_int($identifier) ? $this->findOwner(trim((string) $identifier, '"'), $driver, $actor) : null;
                if ($owner && ! $this->visibility->canView($owner, $actor)) {
                    return new JsonResponse(['data' => []], 200, ['Cache-Control' => 'private, no-store']);
                }
            }
        }

        // Rewind's standalone HTML profile is outside the /u/:username route tree.
        if ($route === 'huseyinfiliz-rewind.blade.user' && $this->visibility->hidesTab('rewind')) {
            $owner = $this->findOwner((string) $request->getAttribute('routeParameters')['id'], 'id', $actor);
            if ($owner && ! $this->visibility->canView($owner, $actor)) {
                throw new PermissionDeniedException;
            }

            return $handler->handle($request);
        }

        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 200 || ! str_contains($response->getHeaderLine('Content-Type'), 'json')) {
            return $response;
        }

        $body = json_decode((string) $response->getBody(), true);
        if (! is_array($body) || ! isset($body['data'])) {
            return $response;
        }

        // Also filter unscoped lists and direct resource URLs in supported integrations.
        $collection = array_is_list($body['data']);
        $resources = $collection ? $body['data'] : [$body['data']];
        $ownerIds = [];
        foreach ($resources as $index => $resource) {
            $tab = match ($resource['type'] ?? '') {
                'user-badges' => 'badges',
                'rw-snaps' => 'rewind',
                'trader-feedbacks', 'trader-stats' => 'feedbacks',
                default => null,
            };
            if (! $tab || ! $this->visibility->hidesTab($tab)) {
                continue;
            }
            $ownerIds[$index] = match ($resource['type'] ?? '') {
                'user-badges', 'rw-snaps', 'trader-stats' => Arr::get($resource, 'relationships.user.data.id') ?? Arr::get($resource, 'attributes.userId'),
                'trader-feedbacks' => Arr::get($resource, 'attributes.toUserId'),
                default => null,
            };
        }
        if (! array_filter($ownerIds)) {
            return $response;
        }
        $owners = User::whereIn('id', array_filter($ownerIds))->get()->keyBy('id');
        $changed = false;
        foreach ($ownerIds as $index => $id) {
            if ($id && isset($owners[$id]) && ! $this->visibility->canView($owners[$id], $actor)) {
                if (! $collection) {
                    throw new PermissionDeniedException;
                }
                unset($resources[$index]);
                $changed = true;
            }
        }
        if ($changed) {
            $body['data'] = array_values($resources);
            unset($body['included']);

            return $this->response($response, $body);
        }

        return $response;
    }

    private function findOwner(string $identifier, string $driver, User $actor): ?User
    {
        try {
            return match ($driver) {
                'slug' => $this->slugManager->forResource(User::class)->fromSlug($identifier, $actor),
                'usernameOrId' => User::where('username', $identifier)->first() ?? User::find((int) $identifier),
                default => User::find((int) $identifier),
            };
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function response(ResponseInterface $original, array $body): ResponseInterface
    {
        return (new JsonResponse($body, $original->getStatusCode(), $original->getHeaders()))
            ->withoutHeader('Content-Length')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
