<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog\Models;

use Bongo\Packages\ActivityLog\ActivityLogger;
use System\Database\Model;

class Activity extends Model
{
    protected static $table = 'activity_log';

    protected array $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get parsed properties array.
     */
    public function getProperties(): array
    {
        $raw = $this->properties;
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Get a specific property by key.
     */
    public function getExtraProperty(string $key, mixed $default = null): mixed
    {
        $props = $this->getProperties();
        return $props[$key] ?? $default;
    }

    /**
     * Scope query to a specific log name.
     */
    public function scopeInLog($query, string $logName)
    {
        return $query->where('log_name', $logName);
    }

    /**
     * Scope query to activities performed on a specific subject model.
     */
    public function scopeForSubject($query, mixed $subject)
    {
        $morph = ActivityLogger::resolveMorph($subject);
        if ($morph !== null) {
            return $query->where('subject_type', $morph[0])->where('subject_id', $morph[1]);
        }

        return $query;
    }

    /**
     * Scope query to activities caused by a specific user/actor.
     */
    public function scopeCausedBy($query, mixed $causer)
    {
        $morph = ActivityLogger::resolveMorph($causer);
        if ($morph !== null) {
            return $query->where('causer_type', $morph[0])->where('causer_id', $morph[1]);
        }

        return $query;
    }
}
