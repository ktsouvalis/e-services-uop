<?php

use App\Services\NetworkLookup\Parsers\CiscoMacTableParser;

// Fixture text below is synthetic, built from the documented IOS
// `show mac address-table` column shape (Vlan / Mac Address / Type / Ports) -
// per the implementation plan, this MUST be replaced/re-verified against
// real captured device output before this parser is trusted in production.
test('parses Cisco show mac address-table output into mac/port/vlan rows', function () {
    $output = <<<'OUT'
          Mac Address Table
-------------------------------------------

Vlan    Mac Address       Type        Ports
----    -----------       --------    -----
2327    0018.0a1b.2c3d    DYNAMIC     Fa0/1
2327    0022.33aa.bb44    DYNAMIC     Fa0/2
Total Mac Addresses for this criterion: 2
OUT;

    $rows = (new CiscoMacTableParser)->parse($output);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['mac' => '00:18:0a:1b:2c:3d', 'port' => 'Fa0/1', 'vlan' => '2327']);
    expect($rows[1])->toBe(['mac' => '00:22:33:aa:bb:44', 'port' => 'Fa0/2', 'vlan' => '2327']);
});

test('ignores header/footer lines that do not start with a vlan+mac pair', function () {
    $output = "          Mac Address Table\nVlan    Mac Address       Type        Ports\nTotal Mac Addresses for this criterion: 0";

    expect((new CiscoMacTableParser)->parse($output))->toBe([]);
});
