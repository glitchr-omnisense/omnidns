<?php

namespace Omnidns;

use Omnidns\Model\Contact;
use Omnidns\Model\Order;

/**
 * Registering a domain and renewing it - orders that cost money: each
 * provider says in its README whether the order is paid at once (the
 * account's means of payment) or waits for a payment at Order::$paymentUrl.
 */
interface RegistrarInterface extends ProviderInterface
{
    /** @param Contact|null $owner the registrant; null: the account's default contact, where the provider has one */
    public function register(string $domain, int $years = 1, ?Contact $owner = null): Order;

    public function renew(string $domain, int $years = 1): Order;
}
