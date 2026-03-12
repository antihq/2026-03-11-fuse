<?php

use App\ShellResponse;

it('creates response with all parameters', function () {
    $response = new ShellResponse(0, 'Success output', false);

    expect($response->exitCode)->toBe(0)
        ->and($response->output)->toBe('Success output')
        ->and($response->timedOut)->toBeFalse();
});

it('defaults timed out to false', function () {
    $response = new ShellResponse(0, 'Success');

    expect($response->timedOut)->toBeFalse();
});

it('accepts zero exit code', function () {
    $response = new ShellResponse(0, 'Success');

    expect($response->exitCode)->toBe(0);
});

it('accepts non-zero exit code', function () {
    $response = new ShellResponse(1, 'Error');

    expect($response->exitCode)->toBe(1);
});

it('can represent a timed out response', function () {
    $response = new ShellResponse(124, 'Partial output', true);

    expect($response->timedOut)->toBeTrue()
        ->and($response->exitCode)->toBe(124);
});
