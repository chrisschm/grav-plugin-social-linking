<?php

namespace Grav\Plugin\SocialLinking\Provider;

class ProviderRegistry
{
    /** @var ProviderInterface[] */
    private array $providers = [];

    public function add(ProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    public function get(string $key): ?ProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    /** @return ProviderInterface[] */
    public function all(): array
    {
        return $this->providers;
    }
}
