<?php

use App\Services\Pangolin\SiteRouting;
use Tests\TestCase;

uses(TestCase::class);

$sites = [['siteId' => 35, 'name' => 'Patras'], ['siteId' => 36, 'name' => 'Tripoli'], ['siteId' => 69, 'name' => 'Kalamata']];

test('routes by the destination prefix first', function (string $destination, string $city, string $expected) {
    expect(SiteRouting::siteNameFor($destination, $city))->toBe($expected);
})->with([
    ['10.23.2.50', 'kalamata', 'Patras'],
    ['10.15.29.201', 'patra', 'Tripoli'],
    ['10.16.1.1', '', 'Tripoli'],
    ['10.22.3.0/24', '', 'Tripoli'],
    ['10.58.0.9', '', 'Tripoli'],
    ['10.11.4.4', 'patra', 'Kalamata'],
    ['10.13.4.4', '', 'Kalamata'],
]);

test('falls back to the city when the prefix is unmapped', function (string $city, string $expected) {
    expect(SiteRouting::siteNameFor('10.99.1.1', $city))->toBe($expected);
})->with([
    ['Sparti', 'Kalamata'],
    ['kalamata', 'Kalamata'],
    ['korinthos', 'Tripoli'],
    [' Nafplio ', 'Tripoli'],
    ['patra', 'Patras'],
]);

test('picks exactly one site by case-insensitive name', function () use ($sites) {
    [$site, $error] = SiteRouting::pick($sites, '10.99.1.1', 'sparti');

    expect($site['siteId'])->toBe(69)->and($error)->toBeNull();
});

test('reports an error when neither prefix nor city maps', function () use ($sites) {
    [$site, $error] = SiteRouting::pick($sites, '10.99.1.1', 'athens');

    expect($site)->toBeNull()->and($error)->toContain('no site for');
});

test('reports an error when the mapped site does not exist in the org', function () {
    [$site, $error] = SiteRouting::pick([['siteId' => 35, 'name' => 'Patras']], '10.13.1.1', '');

    expect($site)->toBeNull()->and($error)->toContain("'Kalamata' not found");
});
