<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-managed Newt agent (name + IP), replacing the old static
 * PANGOLIN_NEWT_HOSTS_JSON env list — the number of agents isn't fixed, so
 * this is a plain CRUD table. Feeds App\Services\Pangolin\NewtConnectionSync
 * (SSH log pull target list).
 */
class PangolinNewtAgent extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function connections(): HasMany
    {
        return $this->hasMany(PangolinNewtConnection::class);
    }
}
