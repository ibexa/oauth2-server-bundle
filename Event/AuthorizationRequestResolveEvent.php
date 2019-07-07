<?php

declare(strict_types=1);

namespace Trikoder\Bundle\OAuth2Bundle\Event;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\User\UserInterface;
use Trikoder\Bundle\OAuth2Bundle\Converter\ScopeConverter;
use Trikoder\Bundle\OAuth2Bundle\Converter\ScopeConverterInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\ClientManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;
use Trikoder\Bundle\OAuth2Bundle\Model\Scope;

final class AuthorizationRequestResolveEvent extends Event
{
    public const AUTHORIZATION_APPROVED = true;
    public const AUTHORIZATION_DENIED = false;

    /**
     * @var AuthorizationRequest
     */
    private $authorizationRequest;

    /**
     * @var ScopeConverterInterface
     */
    private $scopeConverter;

    /**
     * @var ClientManagerInterface
     */
    private $clientManager;

    /**
     * @var bool
     */
    private $authorizationResolution = self::AUTHORIZATION_DENIED;

    /**
     * @var ResponseInterface|null
     */
    private $response;

    /**
     * @var UserInterface|null
     */
    private $user;

    public function __construct(AuthorizationRequest $authorizationRequest, ScopeConverterInterface $scopeConverter, ClientManagerInterface $clientManager)
    {
        $this->authorizationRequest = $authorizationRequest;
        $this->scopeConverter = $scopeConverter;
        $this->clientManager = $clientManager;
    }

    public function getAuthorizationResolution(): bool
    {
        return $this->authorizationResolution;
    }

    public function resolveAuthorization(bool $authorizationResolution): self
    {
        $this->authorizationResolution = $authorizationResolution;
        $this->response = null;
        $this->stopPropagation();

        return $this;
    }

    public function hasResponse(): bool
    {
        return $this->response instanceof ResponseInterface;
    }

    public function getResponse(): ResponseInterface
    {
        if (!$this->hasResponse()) {
            throw new LogicException('There is no response. You should call "hasResponse" to check if the response exists.');
        }

        return $this->response;
    }

    public function setResponse(ResponseInterface $response): self
    {
        $this->response = $response;
        $this->stopPropagation();

        return $this;
    }

    public function getGrantTypeId(): string
    {
        return $this->authorizationRequest->getGrantTypeId();
    }

    public function getClient(): Client
    {
        $identifier = $this->authorizationRequest->getClient()->getIdentifier();
        $client = $this->clientManager->find($identifier);

        if (null === $client) {
            throw new RuntimeException(sprintf('No client found for the given identifier "%s".', $identifier));
        }

        return $client;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Scope[]
     */
    public function getScopes(): array
    {
        return $this->scopeConverter->toDomainArray(
            $this->authorizationRequest->getScopes()
        );
    }

    public function isAuthorizationApproved(): bool
    {
        return $this->authorizationRequest->isAuthorizationApproved();
    }

    public function getRedirectUri(): ?string
    {
        return $this->authorizationRequest->getRedirectUri();
    }

    public function getState(): ?string
    {
        return $this->authorizationRequest->getState();
    }

    public function getCodeChallenge(): string
    {
        return $this->authorizationRequest->getCodeChallenge();
    }

    public function getCodeChallengeMethod(): string
    {
        return $this->authorizationRequest->getCodeChallengeMethod();
    }
}
