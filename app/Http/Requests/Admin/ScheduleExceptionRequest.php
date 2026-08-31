<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScheduleExceptionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleExceptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Null means the whole salon, which is how holidays and closures
            // are expressed.
            'staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],

            // Salon wall-clock, converted to UTC before storing.
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],

            'override_opens_at' => ['nullable', 'date_format:H:i'],
            'override_closes_at' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function type(): ScheduleExceptionType
    {
        return ScheduleExceptionType::from($this->string('type')->toString());
    }

    /**
     * The submitted local wall-clock instant, as UTC.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->toUtc($this->string('starts_at')->toString());
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->toUtc($this->string('ends_at')->toString());
    }

    private function toUtc(string $value): CarbonImmutable
    {
        // The admin types a salon wall-clock time. Parsing it in the salon's
        // timezone is what stops a holiday landing eight hours out.
        return CarbonImmutable::parse($value, config('salon.timezone'))->utc();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->endsAt() <= $this->startsAt()) {
                $validator->errors()->add('ends_at', 'The exception must end after it starts.');
            }

            $type = $this->type();

            if ($type === ScheduleExceptionType::SpecialHours) {
                if ($this->input('staff_id')) {
                    $validator->errors()->add(
                        'staff_id',
                        'Special hours change the salon\'s opening times, so they apply to everyone.',
                    );
                }

                $opens = $this->input('override_opens_at');
                $closes = $this->input('override_closes_at');

                if (! $opens || ! $closes) {
                    $validator->errors()->add(
                        'override_opens_at',
                        'Special hours need a replacement opening and closing time.',
                    );
                } elseif ($closes <= $opens) {
                    $validator->errors()->add(
                        'override_closes_at',
                        'The replacement closing time must be after the opening time.',
                    );
                }
            }

            if ($type->isSalonWide() && $this->input('staff_id')) {
                $validator->errors()->add(
                    'staff_id',
                    ucfirst($type->label()).' applies to the whole salon, not one person.',
                );
            }

            if (! $type->isSalonWide() && $type !== ScheduleExceptionType::SpecialHours && ! $this->input('staff_id')) {
                $validator->errors()->add(
                    'staff_id',
                    ucfirst($type->label()).' needs a staff member.',
                );
            }
        });
    }
}
