<?php

use App\Services\NetworkLookup\Parsers\HuaweiMacTableParser;

// Fixture text below is synthetic, built from the documented VRP
// `display mac-address` column shape (MAC / VLAN(/VSI/BD) / Learned-From /
// Type) - per the implementation plan, this MUST be replaced/re-verified
// against real captured device output before this parser is trusted in
// production.
test('parses Huawei display mac-address output into mac/port/vlan rows', function () {
    $output = <<<'OUT'
MAC Address    VLAN/VSI/BD      Learned-From        Type
-------------------------------------------------------------------------------
203a-4316-6c90 2313/-/-         GE0/0/3             dynamic
5c26-0a12-3456 2301/-/-         GE0/0/7             dynamic
-------------------------------------------------------------------------------
Total matching items on slot 0 displayed = 2
OUT;

    $rows = (new HuaweiMacTableParser)->parse($output);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['mac' => '20:3a:43:16:6c:90', 'port' => 'GE0/0/3', 'vlan' => '2313']);
    expect($rows[1])->toBe(['mac' => '5c:26:0a:12:34:56', 'port' => 'GE0/0/7', 'vlan' => '2301']);
});

test('ignores header/separator lines that do not start with a MAC address', function () {
    $output = "MAC Address    VLAN/VSI/BD      Learned-From        Type\n-----\nTotal matching items = 0";

    expect((new HuaweiMacTableParser)->parse($output))->toBe([]);
});
