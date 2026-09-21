<?php

use App\Services\NetworkLookup\MacAddressNormalizer;

test('normalizes every input format to the same canonical colon-separated lowercase form', function (string $input) {
    expect(MacAddressNormalizer::normalize($input))->toBe('20:3a:43:16:6c:90');
})->with([
    'Huawei dash-grouped' => '203a-4316-6c90',
    'Cisco dot-grouped' => '203a.4316.6c90',
    'colon-separated' => '20:3A:43:16:6C:90',
    'no separator' => '203A43166C90',
]);

test('returns null for input that is not 12 hex characters', function (string $input) {
    expect(MacAddressNormalizer::normalize($input))->toBeNull();
})->with([
    'too short' => '203a-4316',
    'ip address' => '10.23.14.156',
    'empty' => '',
]);
