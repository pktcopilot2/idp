<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DxDataGridRequest extends FormRequest
{
    private const LOAD_OPTION_KEYS = [
        'skip',
        'take',
        'sort',
        'group',
        'requireGroupCount',
        'filter',
        'requireTotalCount',
        'searchExpr',
        'searchOperation',
        'searchValue',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'skip' => ['sometimes', 'integer', 'min:0'],
            'take' => ['sometimes'],
            'sort' => ['sometimes'],
            'group' => ['sometimes'],
            'requireGroupCount' => ['sometimes'],
            'filter' => ['sometimes'],
            'requireTotalCount' => ['sometimes'],
            'searchExpr' => ['sometimes'],
            'searchOperation' => ['sometimes', 'string'],
            'searchValue' => ['sometimes'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadOptions(): array
    {
        $options = [];

        foreach (self::LOAD_OPTION_KEYS as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->query($key);
            if ($value === '' || $value === null) {
                continue;
            }

            $options[$key] = $this->normalizeLoadOption($value);
        }

        return $options;
    }

    public function loadOption(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }

        $value = $this->query($key, $default);

        return $value === '' ? $default : $this->normalizeLoadOption($value);
    }

    public function skip(): int
    {
        return (int) $this->query('skip', 0);
    }

    public function take(): ?int
    {
        $take = $this->query('take');

        return is_numeric($take) ? (int) $take : null;
    }

    public function requiresTotalCount(): bool
    {
        return filter_var($this->query('requireTotalCount'), FILTER_VALIDATE_BOOL);
    }

    public function requiresGroupCount(): bool
    {
        return filter_var($this->query('requireGroupCount'), FILTER_VALIDATE_BOOL);
    }

    private function normalizeLoadOption(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
