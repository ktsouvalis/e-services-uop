<?php

use App\Services\Pangolin\NormalizeResolver;

beforeEach(function () {
    $this->resolver = new NormalizeResolver();
});

test('pickUniqueCandidate returns the single candidate as-is', function () {
    expect($this->resolver->pickUniqueCandidate([['a@uop.gr', 1]]))->toBe(['a@uop.gr', 1]);
});

test('pickUniqueCandidate returns null,null with no candidates', function () {
    expect($this->resolver->pickUniqueCandidate([]))->toBe([null, null]);
});

test('pickUniqueCandidate breaks a tie via domain priority (uop.gr over go.uop.gr)', function () {
    expect($this->resolver->pickUniqueCandidate([
        ['ktsouvalis@go.uop.gr', 2],
        ['ktsouvalis@uop.gr', 1],
    ]))->toBe(['ktsouvalis@uop.gr', 1]);
});

test('pickUniqueCandidate refuses to guess when two candidates tie on the same domain rank', function () {
    expect($this->resolver->pickUniqueCandidate([
        ['a@uop.gr', 1],
        ['b@uop.gr', 2],
    ]))->toBe([null, null]);
});

test('pickUniqueCandidate refuses to guess when neither domain is in the priority list', function () {
    expect($this->resolver->pickUniqueCandidate([
        ['a@example.com', 1],
        ['b@other.com', 2],
    ]))->toBe([null, null]);
});

test('resolveUnassignedTarget reverses the exact create_ naming formula', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    expect($this->resolver->resolveUnassignedTarget('patra-ktsouvalis-2302-50', 'patra', '2302', '50', $index))
        ->toBe(['ktsouvalis@uop.gr', 42]);
});

test('resolveUnassignedTarget refuses a name that does not exactly fit the convention', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    // Wrong vlan/tail suffix.
    expect($this->resolver->resolveUnassignedTarget('patra-ktsouvalis-9999-99', 'patra', '2302', '50', $index))
        ->toBe([null, null]);
    // No name at all.
    expect($this->resolver->resolveUnassignedTarget(null, 'patra', '2302', '50', $index))->toBe([null, null]);
});

test('resolveUnassignedTarget refuses a leftover username with a literal hyphen in it', function () {
    $index = [];
    // Leftover after peeling prefix/suffix would be "a-b", which can't be a
    // sanitized username (dots/hyphens aren't part of that alphabet) — must
    // not be treated as a match.
    expect($this->resolver->resolveUnassignedTarget('patra-a-b-2302-50', 'patra', '2302', '50', $index))
        ->toBe([null, null]);
});

test('resolveUnassignedTargetFromNiceId reverses the exact TCP-only niceId formula (no UDP ports)', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    expect($this->resolver->resolveUnassignedTargetFromNiceId('ktsouvalis-2302-50-p22-p3389', '2302', '50', ['22', '3389'], [], $index))
        ->toBe(['ktsouvalis@uop.gr', 42]);
});

test('resolveUnassignedTargetFromNiceId reverses the dual tcp+udp niceId formula', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    expect($this->resolver->resolveUnassignedTargetFromNiceId('ktsouvalis-2302-50-p22-p3389-u53', '2302', '50', ['22', '3389'], ['53'], $index))
        ->toBe(['ktsouvalis@uop.gr', 42]);
});

test('resolveUnassignedTargetFromNiceId returns null,null when either port list could not be computed', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    expect($this->resolver->resolveUnassignedTargetFromNiceId('ktsouvalis-2302-50-p22', '2302', '50', null, [], $index))
        ->toBe([null, null]);
    expect($this->resolver->resolveUnassignedTargetFromNiceId('ktsouvalis-2302-50-p22', '2302', '50', ['22'], null, $index))
        ->toBe([null, null]);
});

test('findUniqueSegmentMatch matches any standalone name segment, not just the strict city/vlan/tail position', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    // Legacy hand-created name that doesn't fit the computed shape at all.
    expect($this->resolver->findUniqueSegmentMatch('legacy-vpn-ktsouvalis-old', $index))
        ->toBe(['ktsouvalis@uop.gr', 42]);
});

test('findUniqueSegmentMatch refuses when two different segments match two different people', function () {
    $index = [
        'ktsouvalis' => [['ktsouvalis@uop.gr', 42]],
        'jdoe' => [['jdoe@uop.gr', 43]],
    ];

    expect($this->resolver->findUniqueSegmentMatch('ktsouvalis-jdoe-shared', $index))->toBe([null, null]);
});

test('resolveTarget auto-resolves the single assigned user unconditionally', function () {
    $users = [['userId' => 7, 'email' => 'a@uop.gr', 'username' => null]];

    expect($this->resolver->resolveTarget('anything', $users, []))->toBe(['a@uop.gr', 7, null, null]);
});

test('resolveTarget resolves 2+ users only when exactly one sanitized email matches a name segment', function () {
    $users = [
        ['userId' => 7, 'email' => 'ktsouvalis@uop.gr', 'username' => null],
        ['userId' => 8, 'email' => 'jdoe@uop.gr', 'username' => null],
    ];

    expect($this->resolver->resolveTarget('patra-ktsouvalis-2302-50', $users, []))
        ->toBe(['ktsouvalis@uop.gr', 7, null, null]);
});

test('resolveTarget reports ambiguous when 2+ users and no name segment uniquely matches', function () {
    $users = [
        ['userId' => 7, 'email' => 'aaa@uop.gr', 'username' => null],
        ['userId' => 8, 'email' => 'bbb@uop.gr', 'username' => null],
    ];

    [$email, $uid, $reason, $suggestion] = $this->resolver->resolveTarget('patra-nomatch-2302-50', $users, []);
    expect($email)->toBeNull();
    expect($uid)->toBeNull();
    expect($reason)->toContain('ambiguous');
    expect($suggestion)->toBeNull();
});

test('resolveTarget with 0 users returns a suggestion but no resolution', function () {
    $index = ['ktsouvalis' => [['ktsouvalis@uop.gr', 42]]];

    [$email, $uid, $reason, $suggestion] = $this->resolver->resolveTarget('patra-ktsouvalis-2302-50', [], $index);
    expect($email)->toBeNull();
    expect($uid)->toBeNull();
    expect($reason)->toBe('no users currently assigned');
    expect($suggestion)->toBe('ktsouvalis@uop.gr');
});
