<?php

namespace App\Http\Requests\Admin;

use App\Services\Media\ImageStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('service_categories', 'name')
                    ->ignore($category?->id)
                    ->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],

            // Type is checked by content, not by the filename, and an explicit
            // extension list stops a renamed file slipping through.
            'image' => [
                'nullable', 'image',
                'mimes:'.implode(',', ImageStorage::MIME_TYPES),
                'max:'.ImageStorage::MAX_KILOBYTES,
                'dimensions:max_width=5000,max_height=5000',
            ],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'The image must be a JPEG, PNG, or WebP file.',
            'image.max' => 'The image may not be larger than 4 MB.',
            'image.dimensions' => 'The image may not be wider or taller than 5000 pixels.',
        ];
    }
}
