<?php

namespace huseyinfiliz\PrivateProfile\Middleware;

use Flarum\User\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

class CheckUserPrivateStatus implements MiddlewareInterface
{
    protected UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['filter']['author'])) {
            return $handler->handle($request);
        }

        $authorUsername = $queryParams['filter']['author'];
        $profileOwner = $this->users->query()->where('username', $authorUsername)->first();
        if (!$profileOwner) {
            return $handler->handle($request);
        }

        $viewer = $request->getAttribute('actor');
        if (
            $profileOwner->getPreference('PrivateProfile', false) !== true ||
            ($viewer && ($viewer->id === $profileOwner->id || $viewer->isAdmin()))
        ) {
            return $handler->handle($request);
        }

        return $this->blockUserApiResponse();
    }

    protected function blockUserApiResponse(): JsonResponse
    {
        return new JsonResponse(['data' => []], 200);
    }
}
