<?php

namespace Omnidns;

/** Builds a provider from its options (a key, a token...). */
interface ProviderFactoryInterface
{
    /** The name providers are configured with: "ovh", "cloudflare"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
