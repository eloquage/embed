<?php

use Eloquage\Embed\Embed;

it('bootstraps the package entrypoint', function () {
    $instance = new Embed;

    expect($instance->name())->toBe('embed');
});
