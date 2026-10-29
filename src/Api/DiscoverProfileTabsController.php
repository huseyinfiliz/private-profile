<?php

namespace huseyinfiliz\PrivateProfile\Api;

use Flarum\Http\RequestUtil;
use huseyinfiliz\PrivateProfile\ProfileTabRegistry;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DiscoverProfileTabsController implements RequestHandlerInterface
{
    public function __construct(protected ProfileTabRegistry $tabs)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse(['tabs' => $this->tabs->discover($request->getParsedBody()['tabs'] ?? null)]);
    }
}
