<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Boot the trait
     */
    public static function bootAuditable()
    {
        static::created(function ($model) {
            self::createAuditLog('create', $model, null, $model->toArray());
        });

        static::updated(function ($model) {
            $oldValues = $model->getOriginal();
            $newValues = $model->getDirty();
            
            if (!empty($newValues)) {
                self::createAuditLog('update', $model, $oldValues, $newValues);
            }
        });

        static::deleted(function ($model) {
            self::createAuditLog('delete', $model, $model->toArray(), null);
        });
    }

    /**
     * Create an audit log entry
     */
    protected static function createAuditLog($action, $model, $oldValues, $newValues)
    {
        try {
            AuditLog::create([
                'action' => $action,
                'model' => get_class($model),
                'model_id' => $model->id,
                'user_id' => Auth::id(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // Log silently to avoid breaking the main operation
            \Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'model_id' => $model->id
            ]);
        }
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'model_id')
                    ->where('model', get_class($this))
                    ->orderBy('created_at', 'desc');
    }
}