<?php

namespace App\Services\NetworkLookup;

/**
 * Shared contract for SshCommandRunner and TelnetCommandRunner - confirmed
 * live that this network's Cisco devices only accept telnet (no working SSH
 * auth) while the Huawei devices are SSH-only, so both transports are real
 * requirements, not a hypothetical. Jobs type-hint the concrete classes
 * directly (not this interface) and pick one per device's `protocol` column,
 * since both need to be simultaneously available - this interface exists for
 * a shared type, not for container-level swapping.
 */
interface DeviceCommandRunnerContract
{
    /**
     * Run a sequence of read-only commands against a device over one
     * session and return the concatenated raw output. Network CLI (Huawei
     * VRP / Cisco IOS) generally doesn't support the SSH "exec" channel type
     * for arbitrary commands the way a Unix host does, so this drives an
     * interactive shell/terminal instead - callers pass the vendor's
     * pagination-disable command (e.g. "screen-length 0 temporary" /
     * "terminal length 0") as the first entry, followed by the actual data
     * command, so a long table doesn't get cut off at a "--More--" prompt.
     *
     * @throws \RuntimeException with a specific, diagnosable message on
     *     connect/auth/read failure - callers should catch this and surface
     *     $e->getMessage() rather than a generic string.
     */
    public function run(string $host, array $commands): string;
}
