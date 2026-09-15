<?php

namespace Eloquage\Embed;

/**
 * Primary entrypoint for eloquage/embed.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Embed
{
    public function name(): string
    {
        return 'embed';
    }
}
