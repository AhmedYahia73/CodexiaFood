<?php

namespace App\Services;

use App\Models\BusinessSetup;
use Carbon\Carbon;

class RestaurantWorkingHoursService
{
    protected ?BusinessSetup $businessSetup = null;

    public function __construct(?BusinessSetup $businessSetup = null)
    {
        $this->businessSetup = $businessSetup;
    }

    /**
     * Get or lazy-load the current business setup record.
     */
    public function getBusinessSetup(): ?BusinessSetup
    {
        if ($this->businessSetup === null) {
            $this->businessSetup = BusinessSetup::first();
        }

        return $this->businessSetup;
    }

    /**
     * Set explicit business setup instance (useful for testing or specific scopes).
     */
    public function setBusinessSetup(?BusinessSetup $businessSetup): self
    {
        $this->businessSetup = $businessSetup;

        return $this;
    }

    /**
     * Get formatted start time (e.g., '09:00:00').
     */
    public function getStartTime(): string
    {
        $setup = $this->getBusinessSetup();
        $start = $setup?->start_day ?? '09:00:00';

        if (strlen((string) $start) === 5) {
            return $start.':00';
        }

        return (string) $start;
    }

    /**
     * Get formatted end time (e.g., '03:00:00').
     */
    public function getEndTime(): string
    {
        $setup = $this->getBusinessSetup();
        $end = $setup?->end_day ?? '03:00:00';

        if (strlen((string) $end) === 5) {
            return $end.':00';
        }

        return (string) $end;
    }

    /**
     * Determine if working hours cross midnight into next calendar day.
     */
    public function isOvernight(): bool
    {
        return $this->getStartTime() > $this->getEndTime();
    }

    /**
     * Check if restaurant is currently open at given timestamp (defaults to now).
     */
    public function isOpen(?Carbon $now = null): bool
    {
        $now = $now ? $now->copy() : Carbon::now();
        $currentTime = $now->format('H:i:s');
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

        if ($this->isOvernight()) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Get localized closed message.
     */
    public function getClosedMessage(): string
    {
        $startDisplay = substr($this->getStartTime(), 0, 5);
        $endDisplay = substr($this->getEndTime(), 0, 5);

        return "المطعم مغلق الان مواعيد العمل من {$startDisplay} الى {$endDisplay}";
    }

    /**
     * Determine the base business date corresponding to the given timestamp.
     * If overnight and current time is before start_time (e.g. 02:00 AM when start is 09:00 AM),
     * it belongs to the previous calendar day's business cycle.
     */
    public function getCurrentBusinessDate(?Carbon $now = null): Carbon
    {
        $now = $now ? $now->copy() : Carbon::now();
        $currentTime = $now->format('H:i:s');
        $startTime = $this->getStartTime();

        if ($currentTime < $startTime) {
            return $now->subDay()->startOfDay();
        }

        return $now->startOfDay();
    }

    /**
     * Get datetime range for "Today and Yesterday" (النهاردة و امبارح) business days.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getTodayAndYesterdayRange(?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : Carbon::now();
        $currentBusinessDate = $this->getCurrentBusinessDate($now);
        $yesterdayBusinessDate = $currentBusinessDate->copy()->subDay();

        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

        $rangeStart = Carbon::parse($yesterdayBusinessDate->toDateString().' '.$startTime);

        if ($this->isOvernight()) {
            $rangeEnd = Carbon::parse($currentBusinessDate->copy()->addDay()->toDateString().' '.$endTime);
        } else {
            $rangeEnd = Carbon::parse($currentBusinessDate->toDateString().' '.$endTime);
        }

        return [
            'start' => $rangeStart,
            'end' => $rangeEnd,
        ];
    }

    /**
     * Get datetime range for a single business date.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getRangeForDate(string $date): array
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

        $rangeStart = Carbon::parse($date.' '.$startTime);

        if ($this->isOvernight()) {
            $rangeEnd = Carbon::parse($date.' '.$endTime)->addDay();
        } else {
            $rangeEnd = Carbon::parse($date.' '.$endTime);
        }

        return [
            'start' => $rangeStart,
            'end' => $rangeEnd,
        ];
    }

    /**
     * Get datetime range between two dates considering business shift hours.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getRangeBetweenDates(string $fromDate, string $toDate): array
    {
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();

        $rangeStart = Carbon::parse($fromDate.' '.$startTime);

        if ($this->isOvernight()) {
            $rangeEnd = Carbon::parse($toDate.' '.$endTime)->addDay();
        } else {
            $rangeEnd = Carbon::parse($toDate.' '.$endTime);
        }

        return [
            'start' => $rangeStart,
            'end' => $rangeEnd,
        ];
    }
}
