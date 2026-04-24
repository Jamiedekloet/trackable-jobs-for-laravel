<?php declare(strict_types=1);

namespace Junges\TrackableJobs\Jobs\Middleware;

use Junges\TrackableJobs\TrackableJob;

/** @property-read \Illuminate\Contracts\Queue\Job $job */
class TrackedJobMiddleware
{
    public function handle(mixed $job, callable $next): void
    {
        if (! $job instanceof TrackableJob) {
            $next($job);

            return;
        }

        $trackedJob = $job->trackedJob();

        if ($job->job->attempts() > 1) {
            $trackedJob->markAsRetrying($job->job->attempts());
        } else {
            $trackedJob->markAsStarted();
        }

        $response = $next($job);

        if ($job->job->isReleased()) {
            $trackedJob->markAsRetrying($job->job->attempts());
        } else {
            $trackedJob->markAsFinished($response);
        }
    }
}
