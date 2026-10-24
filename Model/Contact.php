<?php

namespace Omnidns\Model;

/**
 * The domain's owner (registrant), as a registry needs them - or a contact
 * the provider already holds, named by its handle (an OVH /me/contact id, a
 * Scaleway contact id): then the rest may stay empty.
 */
final readonly class Contact
{
    public const INDIVIDUAL = 'individual';
    public const COMPANY = 'company';
    public const ASSOCIATION = 'association';

    /**
     * @param string $phone   in the registries' form: +33.123456789
     * @param string $country ISO 3166-1 alpha-2: FR
     */
    public function __construct(
        public string $firstName = '',
        public string $lastName = '',
        public string $email = '',
        public string $phone = '',
        public string $street = '',
        public string $city = '',
        public string $zip = '',
        public string $country = '',
        public string $type = self::INDIVIDUAL,
        public ?string $organization = null,
        public ?string $handle = null,
    ) {
    }

    public static function handle(string $handle): self
    {
        return new self(handle: $handle);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['firstName' => $this->firstName, 'lastName' => $this->lastName, 'email' => $this->email, 'phone' => $this->phone, 'street' => $this->street, 'city' => $this->city, 'zip' => $this->zip, 'country' => $this->country, 'type' => $this->type, 'organization' => $this->organization, 'handle' => $this->handle];
    }
}
