<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Reporting\SalonMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salon analytics over a chosen period.
 *
 * The range is normalised to whole salon-local days before anything is counted.
 * Passing raw instants would silently truncate the first and last day, so a
 * report run at midday would show today as half empty.
 */
class ReportController extends Controller
{
    /** Longest period that can be asked for, to keep a report a report. */
    private const MAX_DAYS = 366;

    public function __construct(private readonly SalonMetrics $metrics) {}

    public function __invoke(Request $request): Response
    {
        // Reporting is a management view; a stylist's own numbers live on their
        // dashboard rather than in salon-wide analytics.
        $this->authorize('viewReports', Appointment::class);

        $timezone = config('salon.timezone');
        [$from, $to] = $this->range($request, $timezone);

        return Inertia::render('Manage/Reports', [
            'range' => [
                'from' => $from->setTimezone($timezone)->toDateString(),
                'to' => $to->setTimezone($timezone)->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
                'label' => $from->setTimezone($timezone)->format('j M Y')
                    .' - '.$to->setTimezone($timezone)->format('j M Y'),
            ],

            'status_counts' => $this->metrics->statusCounts($from, $to),
            'value' => $this->metrics->value($from, $to),
            'attrition' => $this->metrics->attritionRates($from, $to),
            'totals' => $this->metrics->totals(),

            'trend' => $this->metrics->dailyTrend($from, $to),
            'peaks' => $this->metrics->peakPeriods($from, $to),
            'popular_services' => $this->metrics->popularServices($from, $to),
            'categories' => $this->metrics->categoryPerformance($from, $to),
            'staff' => $this->metrics->staffPerformance($from, $to),
            'customer_growth' => $this->metrics->customerGrowth($from, $to),

            'timezone' => $timezone,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $to = $this->parse($request->string('to')->toString(), $timezone) ?? $today;
        $from = $this->parse($request->string('from')->toString(), $timezone) ?? $to->subDays(29);

        // A backwards range is a typo, not a request for nothing.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS);
        }

        return [$from->startOfDay()->utc(), $to->endOfDay()->utc()];
    }

    private function parse(string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
