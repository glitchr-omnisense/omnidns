<?php

namespace Omnidns\Exception;

/** A value no provider can take: a malformed domain name, a record type unknown, a duration of zero years. */
final class InvalidArgumentException extends \InvalidArgumentException implements OmnidnsException
{
}
