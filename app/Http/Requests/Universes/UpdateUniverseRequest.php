<?php

namespace App\Http\Requests\Universes;

use App\Enums\DecorationColor;
use App\Enums\DecorationFont;
use App\Enums\DecorationType;
use App\Enums\UniverseLayout;
use App\Rules\SingleEmoji;
use App\Services\SpotifyTrackResolver;
use App\Services\StyleNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUniverseRequest extends FormRequest
{
    private const MAX_DECORATIONS = 50;

    private const MAX_TEXT_LENGTH = 100;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('universe'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->style)) {
            $this->merge(['style' => app(StyleNormalizer::class)->normalize($this->style)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'style' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'layout' => ['sometimes', Rule::enum(UniverseLayout::class)],
            'spotify_url' => ['sometimes', 'nullable', 'string', 'regex:'.SpotifyTrackResolver::TRACK_LINK_PATTERN],
            'decorations' => ['sometimes', 'array', 'max:'.self::MAX_DECORATIONS],
            'decorations.*.type' => ['required', Rule::enum(DecorationType::class)],
            'decorations.*.emoji' => ['exclude_unless:decorations.*.type,EMOJI', 'required', new SingleEmoji],
            'decorations.*.text' => ['exclude_unless:decorations.*.type,TEXT', 'required', 'string', 'max:'.self::MAX_TEXT_LENGTH],
            'decorations.*.font' => ['exclude_unless:decorations.*.type,TEXT', 'required', Rule::enum(DecorationFont::class)],
            'decorations.*.color' => ['exclude_unless:decorations.*.type,TEXT', 'required', Rule::enum(DecorationColor::class)],
            'decorations.*.x' => ['required', 'numeric', 'between:0,100'],
            'decorations.*.y' => ['required', 'numeric', 'min:0'],
            'decorations.*.size' => ['required', 'numeric', 'between:1,100'],
        ];
    }

    public function decorations(): array
    {
        return collect($this->validated('decorations'))
            ->map(fn (array $decoration) => [
                ...$decoration,
                'x' => $decoration['x'] + 0,
                'y' => $decoration['y'] + 0,
                'size' => $decoration['size'] + 0,
            ])
            ->all();
    }
}
