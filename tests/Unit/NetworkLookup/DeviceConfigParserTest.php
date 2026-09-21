<?php

use App\Services\NetworkLookup\DeviceConfigParser;

// Fixture text is trimmed-down real excerpts from the actual switch config
// repo (~/Desktop/Dev/network), not synthetic - confirmed against the real
// files during planning.
test('detects Huawei vendor and name from sysname, and per-port descriptions', function () {
    $config = <<<'CFG'
!Software Version V200R024C00SPC500
#
sysname Antox_SW_1
#
vlan batch 18 36 105
#
interface GE0/0/1
 description esda_lab_link
 port link-type access
#
CFG;

    $parser = new DeviceConfigParser;

    expect($parser->detectVendor($config))->toBe('huawei');
    expect($parser->extractName($config))->toBe('Antox_SW_1');
    expect($parser->extractPortDescriptions($config))->toBe(['GE0/0/1' => 'esda_lab_link']);
    expect($parser->extractPortLinkTypes($config))->toBe(['GE0/0/1' => 'access']);
});

test('extracts trunk link-type for both vendors, from real switch-interconnect config excerpts', function () {
    $huawei = <<<'CFG'
sysname Karam_SW_1
#
interface GigabitEthernet0/0/1
 description GE Connection to Karam_SW_5 Gi0/1
 port link-type trunk
#
CFG;

    $cisco = <<<'CFG'
hostname CNCLAB_SW_1
!
interface GigabitEthernet0/1
 description Connection to SteMhx_SW GE0/0/42
 switchport trunk allowed vlan 1,107,999,2300,2327,2398,2399
 switchport mode trunk
!
CFG;

    $parser = new DeviceConfigParser;

    expect($parser->extractPortLinkTypes($huawei))->toBe(['GigabitEthernet0/0/1' => 'trunk']);
    expect($parser->extractPortLinkTypes($cisco))->toBe(['GigabitEthernet0/1' => 'trunk']);
});

test('extractPorts normalizes Huawei full interface names to the abbreviated form live poll output uses', function () {
    // Confirmed live: without this, a trunk port imported under its full
    // config name ("XGigabitEthernet0/0/1") never matches the abbreviated
    // name ("XGE0/0/1") display mac-address actually reports, so the trunk
    // exclusion silently never fires for it.
    $config = <<<'CFG'
sysname Hliak2_SW_1
#
interface XGigabitEthernet0/0/1
 description SMF Connection to Hliaka1_SW XGE0/0/2
 port link-type trunk
#
interface GigabitEthernet0/0/14
 port link-type hybrid
#
CFG;

    $ports = (new DeviceConfigParser)->extractPorts($config, 'huawei');

    expect($ports)->toHaveKey('XGE0/0/1');
    expect($ports)->not->toHaveKey('XGigabitEthernet0/0/1');
    expect($ports['XGE0/0/1']['link_type'])->toBe('trunk');
    expect($ports['GE0/0/14']['link_type'])->toBe('hybrid');
});

test('extractPorts normalizes Cisco full interface names too, and includes ports with no description/link-type at all', function () {
    $config = <<<'CFG'
hostname CNCLAB_SW_1
!
interface GigabitEthernet0/1
 switchport mode trunk
!
interface FastEthernet0/2
!
CFG;

    $ports = (new DeviceConfigParser)->extractPorts($config, 'cisco');

    expect($ports)->toHaveKey('Gi0/1');
    expect($ports['Gi0/1']['link_type'])->toBe('trunk');
    // No description/link-type line at all - still gets an entry (both
    // extractPortDescriptions/extractPortLinkTypes would drop this port
    // entirely, which was the bug: ImportPortDetails used to union those
    // two lossy views instead of using every port extractPorts finds).
    expect($ports)->toHaveKey('Fa0/2');
    expect($ports['Fa0/2'])->toBe(['description' => null, 'link_type' => null]);
});

test('detects Cisco vendor and name from hostname', function () {
    $config = <<<'CFG'
!
version 15.0
hostname CNCLAB_SW_1
!
interface FastEthernet0/1
 switchport access vlan 2327
 switchport mode access
!
CFG;

    $parser = new DeviceConfigParser;

    expect($parser->detectVendor($config))->toBe('cisco');
    expect($parser->extractName($config))->toBe('CNCLAB_SW_1');
});

test('returns null for vendor/name when a config has none of the expected markers', function () {
    $parser = new DeviceConfigParser;

    expect($parser->detectVendor('garbage'))->toBeNull();
    expect($parser->extractName('garbage'))->toBeNull();
});
