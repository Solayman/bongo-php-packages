<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog\Models;

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
        if (is_object($subject)) {
            $type = get_class($subject);
            $id = method_exists($subject, 'getKey') ? (string) $subject->getKey() : (string) ($subject->id ?? '');
            return $query->where('subject_type', $type)->where('subject_id', $id);
        }

        return $query;
    }

    /**
     * Scope query to activities caused by a specific user/actor.
     */
    public function scopeCausedBy($query, mixed $causer)
    {
        if (is_object($causer)) {
            $type = get_class($causer);
            $id = method_exists($causer, 'getKey') ? (string) $causer->getKey() : (string) ($causer->id ?? '');
            return $query->where('causer_type', $type)->where('causer_id', $id);
        }

        return $query;
    }
}
