<?php

namespace Omnidns\Testing;

use Omnidns\Config;
use Omnidns\Model\Money;
use Omnidns\ProviderFactory;
use Omnidns\ProviderInterface;

/**
 * MemoryProvider, from its options:
 *
 *   domains   the domains held, and when each ends: ["example.org" => "2027-03-01"]
 *   taken     the domains held by someone else
 *   price     a year's price, as a Model\Money
 */
final class MemoryFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidns.factory_name' => 'memory',
            'omnidns.factory_title' => 'In memory',
            'omnidns.required_options' => [],
            'domains' => [],
            'taken' => [],
            'price' => null,
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $domains = $c->get('domains');
        $taken = $c->get('taken');
        $price = $c->get('price');

        $held = [];
        foreach (\is_array($domains) ? $domains : [] as $name => $expires) {
            if (\is_string($name) && \is_string($expires)) {
                $held[$name] = $expires;
            }
        }

        return new MemoryProvider(
            $held,
            \is_array($taken) ? array_values(array_filter($taken, 'is_string')) : [],
            $price instanceof Money ? $price : null,
        );
    }
}
