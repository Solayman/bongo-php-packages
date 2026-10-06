<?php

namespace Bongo\Packages\QueueMonitor\Controllers;

use Bongo\Packages\QueueMonitor\QueueMonitor;
use System\Http\Request;
use System\Http\Response;

class QueueMonitorController
{
    public function index(Request $request)
    {
        $stats = QueueMonitor::stats();
        $failedJobs = QueueMonitor::failedJobs(50);

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json(['stats' => $stats, 'failed_jobs' => $failedJobs]);
        }

        // Render standalone dashboard HTML
        return Response::html($this->renderView($stats, $failedJobs));
    }

    public function apiStats()
    {
        return Response::json(QueueMonitor::stats());
    }

    public function retry(Request $request, $id)
    {
        $success = QueueMonitor::retry($id);
        if ($request->wantsJson()) {
            return Response::json(['success' => $success]);
        }
        return Response::redirect('/queue-monitor')->with($success ? 'success' : 'error', $success ? "Job {$id} retried successfully." : "Failed to retry job {$id}.");
    }

    public function retryAll(Request $request)
    {
        $count = QueueMonitor::retryAll();
        if ($request->wantsJson()) {
            return Response::json(['success' => true, 'retried' => $count]);
        }
        return Response::redirect('/queue-monitor')->with('success', "Retried {$count} failed jobs.");
    }

    public function delete(Request $request, $id)
    {
        $success = QueueMonitor::delete($id);
        if ($request->wantsJson()) {
            return Response::json(['success' => $success]);
        }
        return Response::redirect('/queue-monitor')->with('success', "Job {$id} deleted.");
    }

    public function flush(Request $request)
    {
        $count = QueueMonitor::flush();
        if ($request->wantsJson()) {
            return Response::json(['success' => true, 'flushed' => $count]);
        }
        return Response::redirect('/queue-monitor')->with('success', "Flushed {$count} failed jobs.");
    }

    protected function renderView(array $stats, array $failedJobs): string
    {
        $driver = htmlspecialchars($stats['driver'], ENT_QUOTES, 'UTF-8');
        $pending = (int) $stats['pending'];
        $failed = (int) $stats['failed'];
        $csrf = function_exists('csrf_field') ? csrf_field() : '';

        $rows = '';
        if (empty($failedJobs)) {
            $rows = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#94a3b8;">No failed jobs recorded. Queue is healthy!</td></tr>';
        } else {
            foreach ($failedJobs as $job) {
                $id = (int) $job->id;
                $conn = htmlspecialchars($job->connection ?? 'default', ENT_QUOTES, 'UTF-8');
                $queue = htmlspecialchars($job->queue ?? 'default', ENT_QUOTES, 'UTF-8');
                $failedAt = htmlspecialchars($job->failed_at ?? '', ENT_QUOTES, 'UTF-8');
                $exception = htmlspecialchars(substr($job->exception ?? '', 0, 120) . '...', ENT_QUOTES, 'UTF-8');

                $rows .= <<<HTML
<tr style="border-bottom:1px solid #334155;">
    <td style="padding:12px;font-family:monospace;">#{$id}</td>
    <td style="padding:12px;"><span style="background:#1e293b;padding:2px 8px;border-radius:4px;font-size:12px;">{$conn}:{$queue}</span></td>
    <td style="padding:12px;font-size:12px;color:#f87171;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{$exception}">{$exception}</td>
    <td style="padding:12px;font-size:12px;color:#94a3b8;">{$failedAt}</td>
    <td style="padding:12px;text-align:right;">
        <form method="POST" action="/queue-monitor/retry/{$id}" style="display:inline;margin:0 4px;">
            {$csrf}
            <button type="submit" style="background:#0284c7;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:11px;">Retry</button>
        </form>
        <form method="POST" action="/queue-monitor/delete/{$id}" style="display:inline;margin:0;">
            {$csrf}
            <button type="submit" style="background:#ef4444;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:11px;">Delete</button>
        </form>
    </td>
</tr>
HTML;
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Monitor — Bongo PHP</title>
    <style>
        body { margin:0; padding:24px; background:#0f172a; color:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .card { background:#1e293b; border-radius:8px; padding:20px; border:1px solid #334155; margin-bottom:20px; }
        .btn { padding:6px 14px; border-radius:6px; border:none; font-weight:bold; cursor:pointer; font-size:13px; text-decoration:none; display:inline-block; }
    </style>
</head>
<body>
    <div style="max-width:1100px;margin:0 auto;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div>
                <h1 style="margin:0 0 4px 0;font-size:24px;">⚡ Bongo Queue Monitor</h1>
                <p style="margin:0;color:#94a3b8;font-size:14px;">Real-time queue health & background worker telemetry</p>
            </div>
            <div>
                <form method="POST" action="/queue-monitor/retry-all" style="display:inline;margin-right:8px;">
                    {$csrf}
                    <button class="btn" style="background:#0284c7;color:#fff;">Retry All</button>
                </form>
                <form method="POST" action="/queue-monitor/flush" style="display:inline;">
                    {$csrf}
                    <button class="btn" style="background:#ef4444;color:#fff;">Flush Failed</button>
                </form>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">
            <div class="card" style="margin:0;">
                <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Queue Driver</div>
                <div style="font-size:24px;font-weight:bold;color:#38bdf8;">{$driver}</div>
            </div>
            <div class="card" style="margin:0;">
                <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Pending Jobs</div>
                <div style="font-size:24px;font-weight:bold;color:#4ade80;">{$pending}</div>
            </div>
            <div class="card" style="margin:0;">
                <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Failed Jobs</div>
                <div style="font-size:24px;font-weight:bold;color:#f87171;">{$failed}</div>
            </div>
        </div>

        <div class="card" style="padding:0;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #334155;font-weight:bold;">
                Recent Failed Jobs
            </div>
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr style="background:#0f172a;color:#94a3b8;font-size:12px;border-bottom:1px solid #334155;">
                        <th style="padding:12px;">ID</th>
                        <th style="padding:12px;">Queue</th>
                        <th style="padding:12px;">Exception</th>
                        <th style="padding:12px;">Failed At</th>
                        <th style="padding:12px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
