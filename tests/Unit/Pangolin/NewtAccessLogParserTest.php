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
        'failed' => false, 'failure_reason' => null, 'failure_detail' => null,
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

test('a Failed to connect line marks the next ACCESS END to that destination as failed', function () {
    $log = implode("\n", [
        'INFO: 2026/09/28 08:45:58 ACCESS START session=bad resource=35 proto=tcp src=10.1.2.3:5000 dst=10.16.2.10:5000 time=2026-09-28T05:45:58Z',
        'INFO: 2026/09/28 08:46:03 TCP Forwarder: Failed to connect to 10.16.2.10:5000: dial tcp 10.16.2.10:5000: i/o timeout',
        'INFO: 2026/09/28 08:46:03 ACCESS END session=bad resource=35 proto=tcp src=10.1.2.3:5000 dst=10.16.2.10:5000 started=2026-09-28T05:45:58Z ended=2026-09-28T05:46:03Z duration=5s',
    ]);

    $session = $this->parser->parse($log)[0];

    expect($session['failed'])->toBeTrue();
    expect($session['failure_reason'])->toBe('timeout');
    expect($session['failure_detail'])->toBe('dial tcp 10.16.2.10:5000: i/o timeout');
});

test('a failure is correlated by destination even with an unrelated session to a different destination interleaved', function () {
    $log = implode("\n", [
        'ACCESS START session=ok1 resource=1 proto=tcp src=10.1.2.3:6000 dst=10.23.2.50:22 time=t0',
        'ACCESS START session=bad resource=1 proto=tcp src=10.1.2.3:5000 dst=10.16.2.10:3389 time=t1',
        'INFO: 2026/09/28 08:46:03 TCP Forwarder: Failed to connect to 10.16.2.10:3389: dial tcp 10.16.2.10:3389: connect: connection refused',
        // The other destination's END lands between the failure and its own END.
        'ACCESS END session=ok1 resource=1 proto=tcp src=10.1.2.3:6000 dst=10.23.2.50:22 started=t0 ended=t2 duration=10m',
        'ACCESS START session=ok2 resource=1 proto=tcp src=10.1.2.3:6001 dst=10.23.2.50:22 time=t3',
        'ACCESS END session=bad resource=1 proto=tcp src=10.1.2.3:5000 dst=10.16.2.10:3389 started=t1 ended=t1 duration=1ms',
        'ACCESS END session=ok2 resource=1 proto=tcp src=10.1.2.3:6001 dst=10.23.2.50:22 started=t3 ended=t4 duration=1m',
    ]);

    $sessions = collect($this->parser->parse($log))->keyBy('session');

    expect($sessions['bad']['failed'])->toBeTrue();
    expect($sessions['bad']['failure_reason'])->toBe('refused');
    expect($sessions['ok1']['failed'])->toBeFalse();
    expect($sessions['ok2']['failed'])->toBeFalse();
});

test('pending failures to one destination are consumed oldest first', function () {
    $log = implode("\n", [
        'ACCESS START session=a resource=1 proto=tcp src=10.1.2.3:1 dst=10.16.2.10:5000 time=t1',
        'ACCESS START session=b resource=1 proto=tcp src=10.1.2.3:2 dst=10.16.2.10:5000 time=t2',
        'INFO: x TCP Forwarder: Failed to connect to 10.16.2.10:5000: dial tcp: connect: no route to host',
        'INFO: x TCP Forwarder: Failed to connect to 10.16.2.10:5000: dial tcp: i/o timeout',
        'ACCESS END session=a resource=1 proto=tcp src=10.1.2.3:1 dst=10.16.2.10:5000 started=t1 ended=t1e duration=0s',
        'ACCESS END session=b resource=1 proto=tcp src=10.1.2.3:2 dst=10.16.2.10:5000 started=t2 ended=t2e duration=5s',
    ]);

    $sessions = collect($this->parser->parse($log))->keyBy('session');

    expect($sessions['a']['failure_reason'])->toBe('unreachable');
    expect($sessions['b']['failure_reason'])->toBe('timeout');
});

test('a session without a Failed line stays failed=false, even at exactly the 5s dial timeout', function () {
    $log = 'ACCESS START session=s resource=1 proto=tcp src=10.1.2.3:1 dst=10.16.2.10:5000 time=t1'."\n".
        'ACCESS END session=s resource=1 proto=tcp src=10.1.2.3:1 dst=10.16.2.10:5000 started=t1 ended=t1e duration=5s';

    $session = $this->parser->parse($log)[0];

    expect($session['failed'])->toBeFalse();
    expect($session['failure_reason'])->toBeNull();
    expect($session['failure_detail'])->toBeNull();
});

test('dial errors are classified by their text', function (string $error, string $reason) {
    expect(NewtAccessLogParser::classifyFailure($error))->toBe($reason);
})->with([
    ['dial tcp 10.16.2.10:5000: connect: connection refused', 'refused'],
    ['dial tcp 10.16.2.10:5000: i/o timeout', 'timeout'],
    ['dial tcp 10.16.2.10:5000: context deadline exceeded', 'timeout'],
    ['dial tcp 10.16.2.10:5000: connect: no route to host', 'unreachable'],
    ['dial tcp 10.16.2.10:5000: connect: network is unreachable', 'unreachable'],
    ['dial tcp 10.16.2.10:5000: connect: permission denied', 'other'],
]);
