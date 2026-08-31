<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StaffScheduleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blocks' => ['present', 'array', 'max:40'],
            'blocks.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'blocks.*.starts_at' => ['required', 'date_format:H:i'],
            'blocks.*.ends_at' => ['required', 'date_format:H:i'],
            'blocks.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $byDay = [];

            foreach ($this->input('blocks', []) as $index => $block) {
                $start = $block['starts_at'] ?? null;
                $end = $block['ends_at'] ?? null;

                if (! $start || ! $end) {
                    continue;
                }

                if ($end <= $start) {
                    $validator->errors()->add(
                        "blocks.{$index}.ends_at",
                        'A shift must end after it starts.',
                    );

                    continue;
                }

                $byDay[(int) ($block['day_of_week'] ?? -1)][] = [$index, $start, $end];
            }

            // Overlapping shifts on the same day would make the same hour appear
            // twice in availability, so they are rejected rather than merged.
            foreach ($byDay as $blocks) {
                foreach ($blocks as $i => [$index, $start, $end]) {
                    foreach (array_slice($blocks, $i + 1) as [, $otherStart, $otherEnd]) {
                        if ($start < $otherEnd && $end > $otherStart) {
                            $validator->errors()->add(
                                "blocks.{$index}.starts_at",
                                'This shift overlaps another shift on the same day.',
                            );

                            break;
                        }
                    }
                }
            }
        });
    }
}
