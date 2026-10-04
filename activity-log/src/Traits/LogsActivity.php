<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog\Traits;

use Bongo\Packages\ActivityLog\ActivityLogger;
use Bongo\Packages\ActivityLog\Models\Activity;

trait LogsActivity
{
    /**
     * Attributes that should never be logged in activity diffs.
     *
     * @var array<string>
     */
    protected array $activityHidden = [
        'password',
        'remember_token',
    ];

    /**
     * Manually log an activity for this model.
     */
    public function logActivity(string $description, ?array $properties = null): Activity
    {
        $logger = (new ActivityLogger())
            ->performedOn($this);

        if ($properties !== null) {
            $logger->withProperties($properties);
        }

        return $logger->log($description);
    }

    /**
     * Record an automated lifecycle event (created, updated, deleted).
     */
    public function recordActivity(string $event, ?array $extraProperties = null): Activity
    {
        $shortName = substr(strrchr('\\' . static::class, '\\'), 1);
        $desc = "{$shortName} {$event}";

        $properties = [];
        $hidden = array_merge($this->activityHidden, $this->hidden ?? []);

        if ($event === 'updated' && !empty($this->original)) {
            $old = [];
            $attributes = [];

            foreach ($this->attributes as $key => $val) {
                if (in_array($key, $hidden, true) || $key === 'updated_at') {
                    continue;
                }
                $prev = $this->original[$key] ?? null;
                if ($prev !== $val) {
                    $old[$key] = $prev;
                    $attributes[$key] = $val;
                }
            }

            $properties = [
                'attributes' => $attributes,
                'old' => $old,
            ];
        } elseif ($event === 'created') {
            $attrs = $this->attributes;
            foreach ($hidden as $h) {
                unset($attrs[$h]);
            }
            $properties = ['attributes' => $attrs];
        }

        if ($extraProperties !== null) {
            $properties = array_merge($properties, $extraProperties);
        }

        return (new ActivityLogger())
            ->performedOn($this)
            ->withProperties($properties)
            ->log($desc);
    }
}
