<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Point storage_path() and the local disk at a throwaway directory. Tests
     * run as root inside the shared dev container, so writing (or wiping) the
     * real storage/ tree clobbered live dev files and left root-owned 0700
     * directories that Apache (www-data) then couldn't write uploads into.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $storage = sys_get_temp_dir().'/e-services-test-storage-'.getmypid();
        foreach (['app/private', 'app/public', 'logs', 'framework/cache'] as $dir) {
            if (! is_dir("{$storage}/{$dir}")) {
                mkdir("{$storage}/{$dir}", 0777, true);
            }
        }
        $this->app->useStoragePath($storage);
        config([
            'filesystems.disks.local.root' => "{$storage}/app/private",
            'filesystems.disks.public.root' => "{$storage}/app/public",
        ]);
        \Illuminate\Support\Facades\Storage::forgetDisk(['local', 'public']);
    }
}
