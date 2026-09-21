<?php

use App\Services\NetworkLookup\Parsers\HuaweiArpParser;

// Fixture text below is synthetic, built from the documented VRP
// `display arp` column shape (IP ADDRESS / MAC ADDRESS / EXPIRE(M) /
// TYPE/VLAN / INTERFACE) - per the implementation plan, this MUST be
// replaced/re-verified against real captured output from the core switch
// (KEDD_Central_S6730) before this parser is trusted in production.
test('parses Huawei display arp output into ip/mac/vlan rows', function () {
    $output = <<<'OUT'
IP ADDRESS      MAC ADDRESS     EXPIRE(M) TYPE/VLAN      INTERFACE
------------------------------------------------------------------------------
10.23.14.156    183d-2da2-fd87  20        D-0/2313       Vlanif2313
10.23.1.1       0000-5e00-0101  -         S-0/-          Vlanif2301
OUT;

    $rows = (new HuaweiArpParser)->parse($output);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['ip' => '10.23.14.156', 'mac' => '18:3d:2d:a2:fd:87', 'vlan' => '2313']);
    expect($rows[1])->toBe(['ip' => '10.23.1.1', 'mac' => '00:00:5e:00:01:01', 'vlan' => null]);
});

test('ignores the header line', function () {
    $output = "IP ADDRESS      MAC ADDRESS     EXPIRE(M) TYPE/VLAN      INTERFACE\n------";

    expect((new HuaweiArpParser)->parse($output))->toBe([]);
});
