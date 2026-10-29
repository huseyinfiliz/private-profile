<?php

namespace huseyinfiliz\PrivateProfile\Middleware;

use Flarum\Http\SlugManager;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckUserPrivateStatus implements MiddlewareInterface
{
    public function __construct(protected SlugManager $slugManager)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || !in_array($request->getAttribute('routeName'), ['posts.index', 'discussions.index'], true)) {
            return $handler->handle($request);
        }

        $authors = $request->getQueryParams()['filter']['author'] ?? null;
        if (!is_string($authors) && !is_array($authors)) {
            return $handler->handle($request);
        }

        $viewer = RequestUtil::getActor($request);
        if ($viewer->isAdmin()) {
            return $handler->handle($request);
        }

        // Match core's author filter, including comma-separated and array values.
        foreach (is_array($authors) ? $authors : explode(',', $authors) as $slug) {
            if (!is_string($slug)) {
                continue;
            }

            try {
                $profileOwner = $this->slugManager->forResource(User::class)->fromSlug(trim($slug, '"'), $viewer);
            } catch (ModelNotFoundException) {
                continue;
            }

            if ($profileOwner->getPreference('PrivateProfile', false) && $viewer->id !== $profileOwner->id) {
                return new JsonResponse(['data' => []], 200);
            }
        }

        return $handler->handle($request);
    }
}
