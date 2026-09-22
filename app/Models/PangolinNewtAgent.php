<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed Newt agent (name + IP), replacing the old static
 * PANGOLIN_NEWT_HOSTS_JSON env list — the number of agents isn't fixed, so
 * this is a plain CRUD table instead. Single source of truth for both
 * App\Services\Pangolin\ClusterMonitor (SSH reachability checks) and
 * App\Services\Pangolin\ConfigYamlWriter (the Logs tab's config.yml).
 */
class PangolinNewtAgent extends Model
{
    protected $guarded = ['id'];
}
