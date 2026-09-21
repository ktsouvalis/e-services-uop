<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for the native Authentik monitor poller. See
 * AuthentikController::monitorSettingsEdit()/monitorSettingsUpdate() —
 * api_token is encrypted at rest via Crypt::encryptString() there (same
 * convention as Chatbot::api_key), so this model never decrypts it itself;
 * callers that need the plaintext token do that explicitly where it's used.
 */
class AuthentikMonitorSettings extends Model
{
    protected $guarded = ['id'];
}
