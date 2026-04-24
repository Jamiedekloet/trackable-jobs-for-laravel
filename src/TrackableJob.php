<?php declare(strict_types=1);

namespace Junges\TrackableJobs;

use Junges\TrackableJobs\Contracts\TrackableContract;
use Junges\TrackableJobs\Contracts\TrackableJobContract;
use Junges\TrackableJobs\Enums\TrackedJobStatus;
use Junges\TrackableJobs\Jobs\Middleware\TrackedJobMiddleware;
use Junges\TrackableJobs\Models\TrackedJob;
use Throwable;

abstract class TrackableJob implements TrackableContract
{
    public string|int|null $trackedJobId = null;

    public function __construct()
    {
        $this->ensureTrackedJobExists();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'trackedJob') {
            return $this->trackedJob();
        }

        trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name), E_USER_NOTICE);

        return null;
    }

    public function __isset(string $name): bool
    {
        if ($name === 'trackedJob') {
            return $this->trackedJobId !== null;
        }

        return false;
    }

    public function trackableKey(): ?string
    {
        return null;
    }

    public function trackableType(): ?string
    {
        return null;
    }

    /** Get the middleware the job should pass through. */
    public function middleware(): array
    {
        return [new TrackedJobMiddleware()];
    }

    public function trackedJob(): TrackableJobContract
    {
        return $this->ensureTrackedJobExists();
    }

    public function failed(Throwable $exception): void
    {
        $message = $exception->getMessage();

        $this->trackedJob()->markAsFailed($message);
    }

    /** @return class-string<TrackableJobContract> */
    protected function trackedJobModel(): string
    {
        /** @var class-string<TrackableJobContract> $trackedJobModel */
        $trackedJobModel = config('trackable-jobs.model', TrackedJob::class);

        return $trackedJobModel;
    }

    protected function withTrackingContext(callable $callback): mixed
    {
        return $callback();
    }

    protected function ensureTrackedJobExists(): TrackableJobContract
    {
        if ($this->trackedJobId !== null) {
            $trackedJob = $this->withTrackingContext(
                fn () => $this->trackedJobModel()::query()->findOrFail($this->trackedJobId)
            );
            assert($trackedJob instanceof TrackableJobContract);

            return $trackedJob;
        }

        $trackedJob = $this->withTrackingContext(fn () => $this->trackedJobModel()::create([
            'trackable_id' => $this->trackableKey(),
            'status' => TrackedJobStatus::Created,
            'attempts' => 0,
            'trackable_type' => $this->trackableType(),
            'name' => static::class,
        ]));
        assert($trackedJob instanceof TrackableJobContract);

        $this->trackedJobId = $trackedJob->getKey();

        return $trackedJob;
    }
}
