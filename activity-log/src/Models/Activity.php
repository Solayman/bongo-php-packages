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

    /**
     * Get the associated subject model.
     */
    public function subject(): ?object
    {
        if (array_key_exists('subject', $this->relations)) {
            return $this->relations['subject'];
        }

        $type = $this->subject_type;
        $id = $this->subject_id;
        if ($type && $id && class_exists($type) && is_subclass_of($type, Model::class)) {
            $model = $type::find($id);
            $this->relations['subject'] = $model;
            return $model;
        }

        return null;
    }

    /**
     * Get the associated causer model.
     */
    public function causer(): ?object
    {
        if (array_key_exists('causer', $this->relations)) {
            return $this->relations['causer'];
        }

        $type = $this->causer_type;
        $id = $this->causer_id;
        if ($type && $id && class_exists($type) && is_subclass_of($type, Model::class)) {
            $model = $type::find($id);
            $this->relations['causer'] = $model;
            return $model;
        }

        return null;
    }

    /**
     * Batch load polymorphic subjects and causers across an array of Activity records.
     * Completely eliminates N+1 queries when rendering activity logs or audit feeds.
     *
     * @param array<Activity> $activities
     * @return array<Activity>
     */
    public static function loadMorphs(array $activities): array
    {
        $groupedSubjects = [];
        $groupedCausers = [];

        foreach ($activities as $idx => $act) {
            if (!empty($act->subject_type) && !empty($act->subject_id) && class_exists($act->subject_type)) {
                $groupedSubjects[$act->subject_type][$act->subject_id][] = $idx;
            }
            if (!empty($act->causer_type) && !empty($act->causer_id) && class_exists($act->causer_type)) {
                $groupedCausers[$act->causer_type][$act->causer_id][] = $idx;
            }
        }

        foreach ($groupedSubjects as $class => $idMap) {
            if (is_subclass_of($class, Model::class)) {
                $ids = array_keys($idMap);
                $models = $class::whereIn('id', $ids)->get();
                if (is_array($models)) {
                    foreach ($models as $m) {
                        $mId = (string) $m->id;
                        if (isset($idMap[$mId])) {
                            foreach ($idMap[$mId] as $actIdx) {
                                $activities[$actIdx]->setRelation('subject', $m);
                            }
                        }
                    }
                }
            }
        }

        foreach ($groupedCausers as $class => $idMap) {
            if (is_subclass_of($class, Model::class)) {
                $ids = array_keys($idMap);
                $models = $class::whereIn('id', $ids)->get();
                if (is_array($models)) {
                    foreach ($models as $m) {
                        $mId = (string) $m->id;
                        if (isset($idMap[$mId])) {
                            foreach ($idMap[$mId] as $actIdx) {
                                $activities[$actIdx]->setRelation('causer', $m);
                            }
                        }
                    }
                }
            }
        }

        return $activities;
    }

    /**
     * Alias for loadMorphs().
     *
     * @param array<Activity> $activities
     * @return array<Activity>
     */
    public static function eagerLoadMorph(array $activities): array
    {
        return static::loadMorphs($activities);
    }
}
