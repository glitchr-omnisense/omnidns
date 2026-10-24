<?php

/*
 * The omnidns/* providers the harness installs beside the core: their tests'
 * namespace, and what their tests need besides (require-dev).
 */
return [
    'ovh' => ['Omnidns\\Ovh\\Tests\\'],
    'gandi' => ['Omnidns\\Gandi\\Tests\\'],
    'cloudflare' => ['Omnidns\\Cloudflare\\Tests\\'],
    'scaleway' => ['Omnidns\\Scaleway\\Tests\\'],
];
