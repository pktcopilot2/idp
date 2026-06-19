<?php

namespace App\Helpers;

use App\Http\Requests\DxDataGridRequest;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DxDatagridHelper
{
    /**
     * Build a DevExtreme DataGrid-compatible payload from HTTP load options.
     *
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, string> $allowedFields
     * @return array{data: array<int, mixed>, totalCount?: int}
     */
    public static function fromRequest(
        DxDataGridRequest $request,
        EloquentBuilder|QueryBuilder|HasMany $query,
        array $allowedFields = [],
        array $relations = [],
    ): array
    {
        $relationConfigs = self::applyRelations($query, $relations);

        $sort = $request->loadOption('sort');
        $group = $request->loadOption('group');
        $filter = $request->loadOption('filter');
        $searchExpr = $request->loadOption('searchExpr');
        $searchOperation = $request->loadOption('searchOperation');
        $searchValue = $request->loadOption('searchValue');

        self::applySearch($query, $searchExpr, $searchOperation, $searchValue, $allowedFields, $relationConfigs);
        self::applyFilter($query, $filter, $allowedFields, $relationConfigs);

        $groupPayload = self::buildGroupPayload($request, $query, $group, $allowedFields, $relationConfigs);
        if ($groupPayload !== null) {
            return $groupPayload;
        }

        $totalCount = (clone $query)->count();

        self::applySort($query, $sort, $allowedFields);

        $skip = $request->skip();
        $take = $request->take();
        if ($take !== null) {
            $query->skip($skip)->take($take);
        }

        $rows = $query->get();

        $payload = [
            'data' => self::transformRows($rows, $relationConfigs),
        ];

        if ($request->requiresTotalCount()) {
            $payload['totalCount'] = $totalCount;
        }

        return $payload;
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, string|array<string, mixed>> $relations
     * @return array<int, array<string, mixed>>
     */
    private static function applyRelations(EloquentBuilder|QueryBuilder|HasMany $query, array $relations): array
    {
        if ($relations === [] || ! $query instanceof EloquentBuilder) {
            return [];
        }

        $with = [];
        $configs = [];

        foreach ($relations as $key => $relationValue) {
            $relationName = null;
            $config = [];

            if (is_string($relationValue)) {
                $relationName = $relationValue;
            }

            if (is_string($key) && is_array($relationValue)) {
                $relationName = $key;
                $config = $relationValue;
            }

            if (! is_string($relationName) || $relationName === '') {
                continue;
            }

            $select = self::normalizeSelectFields($config['select'] ?? null);
            if ($select !== []) {
                $with[] = $relationName.':'.implode(',', $select);
            } else {
                $with[] = $relationName;
            }

            $configs[] = [
                'relation' => $relationName,
                'mode' => in_array(($config['mode'] ?? 'entity'), ['entity', 'implode'], true)
                    ? $config['mode']
                    : 'entity',
                'valueField' => is_string($config['valueField'] ?? null) ? $config['valueField'] : 'name',
                'as' => is_string($config['as'] ?? null) ? $config['as'] : $relationName,
                'separator' => is_string($config['separator'] ?? null) ? $config['separator'] : ', ',
                'default' => is_string($config['default'] ?? null) ? $config['default'] : '-',
                'removeRelation' => array_key_exists('removeRelation', $config) && (bool) $config['removeRelation'],
            ];
        }

        if ($with !== []) {
            $query->with($with);
        }

        return $configs;
    }

    /**
     * @param mixed $select
     * @return array<int, string>
     */
    private static function normalizeSelectFields(mixed $select): array
    {
        if (! is_array($select)) {
            return [];
        }

        return array_values(array_filter($select, 'is_string'));
    }

    /**
     * @param mixed $rows
     * @param array<int, array<string, mixed>> $relationConfigs
     * @return array<int, mixed>
     */
    private static function transformRows(mixed $rows, array $relationConfigs): array
    {
        if (! method_exists($rows, 'map')) {
            return is_array($rows) ? $rows : [];
        }

        if ($relationConfigs === []) {
            return $rows->toArray();
        }

        return $rows->map(function ($row) use ($relationConfigs) {
            if (! method_exists($row, 'toArray')) {
                return $row;
            }

            $record = $row->toArray();

            foreach ($relationConfigs as $config) {
                $relationName = $config['relation'];
                $mode = $config['mode'];
                $valueField = $config['valueField'];
                $alias = $config['as'];
                $separator = $config['separator'];
                $defaultValue = $config['default'];

                if ($mode === 'entity') {
                    if ($alias !== $relationName) {
                        $record[$alias] = $record[$relationName] ?? [];
                    }

                    if ($config['removeRelation']) {
                        unset($record[$relationName]);
                    }

                    continue;
                }

                $values = collect($record[$relationName] ?? [])
                    ->pluck($valueField)
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->values();

                $record[$alias] = $values->isNotEmpty()
                    ? $values->implode($separator)
                    : $defaultValue;

                if ($config['removeRelation']) {
                    unset($record[$relationName]);
                }
            }

            return $record;
        })->all();
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, mixed>|null $sort
     * @param array<int, string> $allowedFields
     */
    private static function applySort(EloquentBuilder|QueryBuilder|HasMany $query, mixed $sort, array $allowedFields): void
    {
        if (! is_array($sort)) {
            return;
        }

        foreach ($sort as $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }

            $selector = $descriptor['selector'] ?? null;
            if (! is_string($selector) || ! self::isAllowedField($selector, $allowedFields)) {
                continue;
            }

            $direction = ($descriptor['desc'] ?? false) ? 'desc' : 'asc';
            $query->orderBy($selector, $direction);
        }
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, string>|string|null $searchExpr
     * @param array<int, string> $allowedFields
        * @param array<int, array<string, mixed>> $relationConfigs
     */
        private static function applySearch(EloquentBuilder|QueryBuilder|HasMany $query, mixed $searchExpr, mixed $searchOperation, mixed $searchValue, array $allowedFields, array $relationConfigs): void
    {
        if ($searchValue === null || $searchValue === '') {
            return;
        }

        $fields = [];
        if (is_string($searchExpr)) {
            $fields = [$searchExpr];
        }

        if (is_array($searchExpr)) {
            $fields = array_values(array_filter($searchExpr, 'is_string'));
        }

        $fields = array_values(array_unique(array_merge(
            $fields,
            self::defaultSearchSelectors($allowedFields, $relationConfigs),
        )));

        if ($fields === []) {
            return;
        }

        $operation = is_string($searchOperation) ? strtolower($searchOperation) : 'contains';

        $query->where(function ($subQuery) use ($fields, $allowedFields, $operation, $searchValue, $relationConfigs): void {
            foreach ($fields as $field) {
                $fieldInfo = self::resolveFieldInfo($field, $allowedFields, $relationConfigs);
                if ($fieldInfo === null) {
                    continue;
                }

                self::applyFieldCondition($subQuery, $fieldInfo, $operation, $searchValue, 'or');
            }
        });
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, mixed>|null $filter
     * @param array<int, string> $allowedFields
     */
    private static function applyFilter(EloquentBuilder|QueryBuilder|HasMany $query, mixed $filter, array $allowedFields, array $relationConfigs): void
    {
        if (! is_array($filter) || $filter === []) {
            return;
        }

        self::applyFilterNode($query, $filter, $allowedFields, $relationConfigs, 'and');
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<int, mixed> $node
     * @param array<int, string> $allowedFields
     */
    private static function applyFilterNode(EloquentBuilder|QueryBuilder|HasMany $query, array $node, array $allowedFields, array $relationConfigs, string $boolean): void
    {
        // Unary negation: ["!", [ ... ]]
        if (($node[0] ?? null) === '!' && isset($node[1]) && is_array($node[1])) {
            $method = $boolean === 'or' ? 'orWhereNot' : 'whereNot';
            $query->{$method}(function ($subQuery) use ($node, $allowedFields, $relationConfigs): void {
                self::applyFilterNode($subQuery, $node[1], $allowedFields, $relationConfigs, 'and');
            });

            return;
        }

        // Simple condition: ["field", "=", value]
        if (self::isSimpleCondition($node)) {
            /** @var string $field */
            $field = $node[0];
            /** @var string $operator */
            $operator = $node[1];
            $value = $node[2];

            $fieldInfo = self::resolveFieldInfo($field, $allowedFields, $relationConfigs);
            if ($fieldInfo === null) {
                return;
            }

            self::applyFieldCondition($query, $fieldInfo, $operator, $value, $boolean);

            return;
        }

        // Grouped conditions: [cond1, "and", cond2, "or", cond3]
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}(function ($subQuery) use ($node, $allowedFields, $relationConfigs): void {
            $nextBoolean = 'and';

            foreach ($node as $part) {
                if (is_string($part) && in_array(strtolower($part), ['and', 'or'], true)) {
                    $nextBoolean = strtolower($part);
                    continue;
                }

                if (is_array($part)) {
                    self::applyFilterNode($subQuery, $part, $allowedFields, $relationConfigs, $nextBoolean);
                }
            }
        });
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     */
    private static function applyFieldCondition(EloquentBuilder|QueryBuilder|HasMany $query, array $fieldInfo, string $operator, mixed $value, string $boolean): void
    {
        $operator = strtolower($operator);
        if ($fieldInfo['type'] === 'relation') {
            self::applyRelationCondition($query, $fieldInfo, $operator, $value, $boolean);

            return;
        }

        self::applyDirectFieldCondition($query, $fieldInfo['field'], $operator, $value, $boolean);
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     */
    private static function applyDirectFieldCondition(EloquentBuilder|QueryBuilder|HasMany $query, string $field, string $operator, mixed $value, string $boolean): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if (in_array($operator, ['anyof', 'noneof'], true)) {
            if (! is_array($value) || $value === []) {
                return;
            }

            $inMethod = $operator === 'noneof'
                ? ($boolean === 'or' ? 'orWhereNotIn' : 'whereNotIn')
                : ($boolean === 'or' ? 'orWhereIn' : 'whereIn');

            $query->{$inMethod}($field, $value);

            return;
        }

        if (in_array($operator, ['contains', 'notcontains', 'startswith', 'endswith'], true)) {
            $likeValue = match ($operator) {
                'startswith' => $value.'%',
                'endswith' => '%'.$value,
                default => '%'.$value.'%',
            };

            if ($operator === 'notcontains') {
                $notMethod = $boolean === 'or' ? 'orWhere' : 'where';
                $query->{$notMethod}($field, 'not like', $likeValue);
            } else {
                $query->{$method}($field, 'like', $likeValue);
            }

            return;
        }

        if ($operator === '<>') {
            $query->{$method}($field, '!=', $value);

            return;
        }

        $allowedOperators = ['=', '!=', '>', '<', '>=', '<='];
        $sqlOperator = in_array($operator, $allowedOperators, true) ? $operator : '=';
        $query->{$method}($field, $sqlOperator, $value);
    }

    /**
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param array<string, string> $fieldInfo
     */
    private static function applyRelationCondition(EloquentBuilder|QueryBuilder|HasMany $query, array $fieldInfo, string $operator, mixed $value, string $boolean): void
    {
        if (! $query instanceof EloquentBuilder) {
            return;
        }

        $relation = $fieldInfo['relation'];
        $relationField = $fieldInfo['field'];

        // Convert dotted relation fields (e.g. permissions.name) into
        // nested relation path + terminal field for whereHas.
        if (is_string($relationField) && str_contains($relationField, '.')) {
            $parts = array_values(array_filter(explode('.', $relationField), fn ($part) => $part !== ''));

            if (count($parts) > 1) {
                $relation .= '.'.implode('.', array_slice($parts, 0, -1));
                $relationField = (string) end($parts);
            }
        }

        $relationOperator = $operator;
        $isNegative = in_array($operator, ['notcontains', '!=', '<>', 'noneof'], true);

        if ($relationOperator === '=' && is_string($value)) {
            $relationOperator = 'contains';
        }

        if ($isNegative) {
            $method = $boolean === 'or' ? 'orWhereDoesntHave' : 'whereDoesntHave';
            $query->{$method}($relation, function ($relationQuery) use ($relationField, $operator, $value): void {
                $effectiveOperator = $operator === 'notcontains' ? 'contains' : $operator;
                self::applyDirectFieldCondition($relationQuery, $relationField, $effectiveOperator, $value, 'and');
            });

            return;
        }

        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';
        $query->{$method}($relation, function ($relationQuery) use ($relationField, $relationOperator, $value): void {
            self::applyDirectFieldCondition($relationQuery, $relationField, $relationOperator, $value, 'and');
        });
    }

    /**
     * @param array<int, string> $allowedFields
     * @param array<int, array<string, mixed>> $relationConfigs
     * @return array<string, string>|null
     */
    private static function resolveFieldInfo(string $selector, array $allowedFields, array $relationConfigs): ?array
    {
        if (self::isAllowedField($selector, $allowedFields)) {
            return [
                'type' => 'direct',
                'field' => $selector,
            ];
        }

        foreach ($relationConfigs as $relationConfig) {
            $relationName = $relationConfig['relation'];
            $alias = $relationConfig['as'];
            $defaultRelationField = $relationConfig['valueField'];

            if ($selector === $relationName || $selector === $alias) {
                return [
                    'type' => 'relation',
                    'relation' => $relationName,
                    'field' => $defaultRelationField,
                ];
            }

            $relationPrefix = $relationName.'.';
            if (str_starts_with($selector, $relationPrefix)) {
                return [
                    'type' => 'relation',
                    'relation' => $relationName,
                    'field' => substr($selector, strlen($relationPrefix)),
                ];
            }

            $aliasPrefix = $alias.'.';
            if (str_starts_with($selector, $aliasPrefix)) {
                return [
                    'type' => 'relation',
                    'relation' => $relationName,
                    'field' => substr($selector, strlen($aliasPrefix)),
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $allowedFields
     * @param array<int, array<string, mixed>> $relationConfigs
     * @return array<int, string>
     */
    private static function defaultSearchSelectors(array $allowedFields, array $relationConfigs): array
    {
        $selectors = $allowedFields;

        foreach ($relationConfigs as $relationConfig) {
            $relation = $relationConfig['relation'];
            $alias = $relationConfig['as'];
            $valueField = $relationConfig['valueField'];

            $selectors[] = $relation;
            $selectors[] = $relation.'.'.$valueField;

            if ($alias !== $relation) {
                $selectors[] = $alias;
                $selectors[] = $alias.'.'.$valueField;
            }
        }

        return array_values(array_filter($selectors, 'is_string'));
    }

    /**
     * Handle grouped responses used by DevExtreme HeaderFilter.
     *
     * @param EloquentBuilder|QueryBuilder|HasMany $query
     * @param mixed $group
     * @param array<int, string> $allowedFields
     * @param array<int, array<string, mixed>> $relationConfigs
     * @return array{data: array<int, array{key: mixed, items: null, count: int}>, totalCount?: int}|null
     */
    private static function buildGroupPayload(DxDataGridRequest $request, EloquentBuilder|QueryBuilder|HasMany $query, mixed $group, array $allowedFields, array $relationConfigs): ?array
    {
        if (! is_array($group) || $group === []) {
            return null;
        }

        $firstGroup = $group[0] ?? null;
        if (! is_array($firstGroup)) {
            return null;
        }

        $selector = $firstGroup['selector'] ?? null;
        if (! is_string($selector) || $selector === '') {
            return null;
        }

        $fieldInfo = self::resolveFieldInfo($selector, $allowedFields, $relationConfigs);
        if ($fieldInfo === null || ($fieldInfo['type'] ?? null) !== 'direct') {
            // Relation arrays cannot be represented as stable distinct scalar list here.
            return [
                'data' => [],
                'totalCount' => 0,
            ];
        }

        $field = $fieldInfo['field'];
        $groupedRows = (clone $query)
            ->select($field.' as key')
            ->selectRaw('count(*) as aggregate')
            ->groupBy($field)
            ->orderBy($field)
            ->get();

        $data = $groupedRows
            ->map(static fn ($row) => [
                'key' => $row->key,
                'items' => null,
                'count' => (int) $row->aggregate,
            ])
            ->all();

        $payload = ['data' => $data];

        if ($request->requiresTotalCount()) {
            $payload['totalCount'] = count($data);
        }

        return $payload;
    }

    /**
     * @param array<int, mixed> $node
     */
    private static function isSimpleCondition(array $node): bool
    {
        return count($node) >= 3 && is_string($node[0] ?? null) && is_string($node[1] ?? null);
    }

    /**
     * @param array<int, string> $allowedFields
     */
    private static function isAllowedField(string $field, array $allowedFields): bool
    {
        if ($allowedFields === []) {
            return true;
        }

        return in_array($field, $allowedFields, true);
    }
}
