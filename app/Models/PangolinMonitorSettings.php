<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for the native Pangolin monitor poller. See
 * PangolinController::monitorSettingsEdit()/monitorSettingsUpdate() —
 * api_key is encrypted at rest via Crypt::encryptString() there (same
 * convention as AuthentikMonitorSettings::api_token), so this model never
 * decrypts it itself; callers that need the plaintext key do that explicitly
 * where it's used.
 */
class PangolinMonitorSettings extends Model
{
    protected $guarded = ['id'];
}
