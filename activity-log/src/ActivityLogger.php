<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog;

use Bongo\Packages\ActivityLog\Models\Activity;
use System\Auth\Auth;

class ActivityLogger
{
    protected ?string $logName = null;
    protected mixed $subject = null;
    protected mixed $causer = null;
    protected array $properties = [];

    public function __construct(?string $logName = null)
    {
        $this->logName = $logName;
    }

    public function useLog(string $name): self
    {
        $this->logName = $name;
        return $this;
    }

    public function performedOn(mixed $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function on(mixed $subject): self
    {
        return $this->performedOn($subject);
    }

    public function causedBy(mixed $causer): self
    {
        $this->causer = $causer;
        return $this;
    }

    public function by(mixed $causer): self
    {
        return $this->causedBy($causer);
    }

    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);
        return $this;
    }

    public function withProperty(string $key, mixed $value): self
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function log(string $description): Activity
    {
        $defaultLog = function_exists('config') ? (string) config('activitylog.default_log_name', 'default') : 'default';
        $logName = $this->logName ?? $defaultLog;

        $subjectType = null;
        $subjectId = null;
        if (is_object($this->subject)) {
            $subjectType = get_class($this->subject);
            $subjectId = method_exists($this->subject, 'getKey') ? (string) $this->subject->getKey() : (string) ($this->subject->id ?? '');
        }

        $causer = $this->causer;
        if ($causer === null && class_exists(Auth::class) && method_exists(Auth::class, 'user')) {
            $causer = Auth::user();
        }

        $causerType = null;
        $causerId = null;
        if (is_object($causer)) {
            $causerType = get_class($causer);
            $causerId = method_exists($causer, 'getKey') ? (string) $causer->getKey() : (string) ($causer->id ?? '');
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip !== null && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if ($ip !== null) {
            $ip = substr($ip, 0, 45);
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        $props = !empty($this->properties) ? json_encode($this->properties, JSON_UNESCAPED_UNICODE) : null;

        $activity = new Activity([
            'log_name' => $logName,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'properties' => $props,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        $activity->save();

        return $activity;
    }
}
