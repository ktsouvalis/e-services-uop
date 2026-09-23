<?php

use App\Services\Pangolin\NewtAccessLogParser;

beforeEach(function () {
    $this->parser = new NewtAccessLogParser();
});

test('pairs a START/END line by session id into one complete session', function () {
    $log = 'ACCESS START session=abc123 resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z'."\n".
        'ACCESS END session=abc123 resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 started=2026-09-22T10:00:00Z ended=2026-09-22T10:05:00Z duration=5m';

    $sessions = $this->parser->parse($log);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0])->toBe([
        'session' => 'abc123', 'resource_id' => 35, 'proto' => 'tcp',
        'src_ip' => '10.1.2.3', 'src_port' => '5000', 'dst_ip' => '10.23.2.50', 'dst_port' => '22',
        'started' => '2026-09-22T10:00:00Z', 'ended' => '2026-09-22T10:05:00Z', 'duration' => '5m',
    ]);
});

test('an unmatched START (still-open session) is included with a null ended/duration', function () {
    $log = 'ACCESS START session=xyz789 resource=35 proto=tcp src=10.1.2.3:5000 dst=10.23.2.50:22 time=2026-09-22T10:00:00Z';

    $sessions = $this->parser->parse($log);

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]['session'])->toBe('xyz789');
    expect($sessions[0]['started'])->toBe('2026-09-22T10:00:00Z');
    expect($sessions[0]['ended'])->toBeNull();
    expect($sessions[0]['duration'])->toBeNull();
});

test('lines that are not ACCESS START/END are ignored', function () {
    $log = "some other log line\nanother line with no match";

    expect($this->parser->parse($log))->toBe([]);
});

test('multiple independent sessions are all paired correctly', function () {
    $log = implode("\n", [
        'ACCESS START session=s1 resource=1 proto=tcp src=10.0.0.1:1 dst=10.0.0.2:22 time=t1',
        'ACCESS START session=s2 resource=1 proto=udp src=10.0.0.3:2 dst=10.0.0.4:53 time=t2',
        'ACCESS END session=s1 resource=1 proto=tcp src=10.0.0.1:1 dst=10.0.0.2:22 started=t1 ended=t1e duration=1m',
        'ACCESS END session=s2 resource=1 proto=udp src=10.0.0.3:2 dst=10.0.0.4:53 started=t2 ended=t2e duration=2m',
    ]);

    $sessions = $this->parser->parse($log);

    expect($sessions)->toHaveCount(2);
    expect(collect($sessions)->pluck('session')->sort()->values()->all())->toBe(['s1', 's2']);
});
