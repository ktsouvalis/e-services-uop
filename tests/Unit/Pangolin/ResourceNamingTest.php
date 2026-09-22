<?php

use App\Services\Pangolin\ResourceNaming;

test('vlanZ computes vlan/tail for a plain 10.x.y.z host', function () {
    expect(ResourceNaming::vlanZ('10.23.2.50'))->toBe(['2302', '50']);
    expect(ResourceNaming::vlanZ('10.1.5.7'))->toBe(['105', '7']);
});

test('vlanZ rejects non-10.x.y.z or malformed addresses', function () {
    expect(ResourceNaming::vlanZ('192.168.1.1'))->toBe([null, null]);
    expect(ResourceNaming::vlanZ('10.23.2'))->toBe([null, null]);
    expect(ResourceNaming::vlanZ('not-an-ip'))->toBe([null, null]);
});

test('cidrVlanTail handles /24 as "all", /16 as the literal prefix, and refuses anything broader than /16', function () {
    expect(ResourceNaming::cidrVlanTail('10.15.25.0/24'))->toBe(['1525', 'all']);
    expect(ResourceNaming::cidrVlanTail('10.15.0.0/16'))->toBe(['15', '16']);
    expect(ResourceNaming::cidrVlanTail('10.0.0.0/8'))->toBe([null, null]);
});

test('cidrVlanTail returns a literal, non-"all" tail for an unusual mask like /20', function () {
    // /20: 20 // 8 - 1 = 1 -> fixed_octets=1 -> vlan is just "x", tail is "20".
    expect(ResourceNaming::cidrVlanTail('10.23.0.0/20'))->toBe(['23', '20']);
});

test('parseTcpPorts accepts comma-separated ports and ranges, preserving input order', function () {
    expect(ResourceNaming::parseTcpPorts('22,3389,32555-32590'))->toBe('22,3389,32555-32590');
    expect(ResourceNaming::parseTcpPorts(' 3389 , 22 '))->toBe('3389,22');
});

test('parseTcpPorts rejects invalid, out-of-range, or backwards-range input', function () {
    expect(ResourceNaming::parseTcpPorts(''))->toBeNull();
    expect(ResourceNaming::parseTcpPorts(null))->toBeNull();
    expect(ResourceNaming::parseTcpPorts('0'))->toBeNull();
    expect(ResourceNaming::parseTcpPorts('65536'))->toBeNull();
    expect(ResourceNaming::parseTcpPorts('abc'))->toBeNull();
    expect(ResourceNaming::parseTcpPorts('100-50'))->toBeNull();
});

test('expectedNiceId sorts ports numerically and keeps a range as a single p-prefixed token', function () {
    expect(ResourceNaming::expectedNiceId('ktsouvalis', '2302', '50', ['3389', '22']))
        ->toBe('ktsouvalis-2302-50-p22-p3389');

    // A range keeps ONE "p" prefix over the whole range ("p32555-32590"),
    // not "p32555-p32590" — it's never split apart, just prefixed as-is.
    expect(ResourceNaming::expectedNiceId('ktsouvalis', '2302', '50', ['22', '32555-32590']))
        ->toBe('ktsouvalis-2302-50-p22-p32555-32590');
});

test('expectedNiceIdWithUdp appends sorted u-prefixed UDP ports after the p-prefixed TCP ones', function () {
    expect(ResourceNaming::expectedNiceIdWithUdp('ktsouvalis', '2302', '50', ['22', '3389'], ['53']))
        ->toBe('ktsouvalis-2302-50-p22-p3389-u53');
});

test('expectedNiceIdWithUdp with no UDP ports is identical to the TCP-only expectedNiceId', function () {
    expect(ResourceNaming::expectedNiceIdWithUdp('ktsouvalis', '2302', '50', ['22', '3389'], []))
        ->toBe(ResourceNaming::expectedNiceId('ktsouvalis', '2302', '50', ['22', '3389']));
});

test('expectedNiceIdWithUdp sorts UDP ports independently of TCP ports', function () {
    expect(ResourceNaming::expectedNiceIdWithUdp('ktsouvalis', '2302', '50', ['3389', '22'], ['514', '53']))
        ->toBe('ktsouvalis-2302-50-p22-p3389-u53-u514');
});

test('expectedPorts parses a resource own tcpPortRangeString, sorted, or null for a non-port wildcard', function () {
    expect(ResourceNaming::expectedPorts('3389,22'))->toBe(['22', '3389']);
    expect(ResourceNaming::expectedPorts('32555-32590,22'))->toBe(['22', '32555-32590']);
    expect(ResourceNaming::expectedPorts('*'))->toBeNull();
    expect(ResourceNaming::expectedPorts(''))->toBeNull();
    expect(ResourceNaming::expectedPorts(null))->toBeNull();
});
