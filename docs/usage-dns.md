---
title: DNS records
order: 32
---

# DNS records

`DnsInterface`: `records($zone, $type, $name)`, `add($zone, $record)`,
`update($zone, $current, $new)`, `delete($zone, $record)`. The zone is a domain name, normalized;
a record's name is relative to it ([Record](concepts.md#record)).

```php
use Omnidns\Model\Record;

$dns = $registry->dns('cloudflare');

$dns->records('example.org');                    // every record of the eight types
$dns->records('example.org', 'MX');              // the MX records
$dns->records('example.org', 'TXT', '_dmarc');   // the TXT records of _dmarc.example.org

$www = $dns->add('example.org', new Record('A', 'www', '192.0.2.10', 300));
$dns->add('example.org', Record::fromValue('MX', '', '10 mx.example.net.'));
$dns->update('example.org', $www, new Record('A', 'www', '192.0.2.11', 300));
$dns->delete('example.org', new Record('A', 'www', '192.0.2.11'));   // found by name, type and content
```

## Finding a record

A provider that names its records (OVH, Cloudflare, Scaleway) gives each one an id, kept in
`Record::$id`: `update()` and `delete()` given a record with an id use it directly. Given one
without an id, they read the zone's records of that name and type and take the one that is
`sameAs()` it - the same content, a host's case and trailing dot and a text's quotes aside. None
found is a `ProviderException` (`... answered no such record for a record of example.org.`).

Gandi gives no id: a record there is always found by its name, type and content.

## What each provider does

| | Records | Add | Update | Delete | Kept besides |
|---|---|---|---|---|---|
| OVHcloud | `GET /domain/zone/{zone}/record` (ids, filtered by `fieldType` and `subDomain`), then each id; the apex filtered locally (OVH reads an empty `subDomain` as no filter) | `POST .../record`, then the zone refreshed | `PUT .../record/{id}` (name, value, TTL; a TTL left null is 0, the zone's default), refreshed; a change of type is a deletion and an addition | `DELETE .../record/{id}`, refreshed | - |
| Gandi (LiveDNS) | `GET /livedns/domains/{zone}/records`, each rrset's values as records, under the rrset's TTL | the rrset created (`POST`), or extended with the value (`PUT`, its TTL kept unless the record gives one) | the value replaced in its rrset (`PUT`); a change of name or type is a deletion and an addition | the value removed (`PUT`), or the rrset deleted with its last value | - |
| Cloudflare | `dns_records`, 100 a page, filtered by type and full name; TTL 1 ("auto") read as `null` | `addRecord()`, not proxied unless `options['proxied']`; SRV and CAA as Cloudflare's structured data | `updateRecordDetails()`; TTL `null` sent as 1; `proxied` as the new record says, else as it was | `deleteRecord()` by id | `options['proxied']` |
| Scaleway | `GET /dns-zones/{zone}/records`, 100 a page, filtered by type and name | the zone's PATCH of changes, `add` | `set` by id | `delete` by id | - |

The zone's id is asked once per provider object at Cloudflare (`Zones::getZoneID()`). OVH writes
an MX's priority in its value, Gandi too: `Record::fromValue()` reads them, `value()` writes them.
Scaleway takes a TTL of 3600 when the record gives none, and a priority apart.

SRV and CAA at Cloudflare are written from their zone-file value - `10 5 5060 sip.example.org`
(priority, weight, port, target), `0 issue "letsencrypt.org"` (flags, tag, value) - and an SRV is
read back in that form.

## A zone served elsewhere

```php
// The domain at OVH, its zone at Cloudflare
$registry->nameservers('registrar')->setNameservers('example.org', ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com']);
$registry->dns('dns')->add('example.org', new Record('A', '', '192.0.2.10'));
```

The Cloudflare provider does not read the name servers Cloudflare assigns to a zone: take them
from Cloudflare.
