<?php

namespace App\Services;

use App\Models\Projects;
use App\Models\ProjectPlans;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;

class BulkProjectUnitImportService
{
    private ProjectUnitSyncService $syncService;

    private static array $fixedColumns = [
        'unit_code', 'title', 'price', 'currency', 'total_units',
        'available_units', 'bedrooms', 'bathrooms', 'build_area',
    ];

    public function __construct(ProjectUnitSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    public function parse(UploadedFile $file): array
    {
        $rows = [];
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            $rows = $this->parseCsv($file);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            $rows = $this->parseXlsx($file);
        }

        return $rows;
    }

    public function validateRows(array $rows, Projects $project): array
    {
        $errors = [];
        $seenCodes = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $rowErrors = [];

            if (empty($row['title'])) {
                $rowErrors[] = 'title is required';
            }

            $unitCode = ! empty($row['unit_code'])
                ? Str::upper(Str::slug(trim($row['unit_code']), '_'))
                : null;

            if ($unitCode) {
                if (isset($seenCodes[$unitCode])) {
                    $rowErrors[] = "duplicate unit_code '{$unitCode}' (also in row {$seenCodes[$unitCode]})";
                }
                $seenCodes[$unitCode] = $rowNum;
            }

            if (! empty($row['price']) && ! is_numeric($row['price'])) {
                $rowErrors[] = 'price must be numeric';
            }

            if (! empty($row['currency']) && strlen(trim($row['currency'])) !== 3) {
                $rowErrors[] = 'currency must be 3 letters (e.g. USD, DOP)';
            }

            if (! empty($row['total_units']) && (! is_numeric($row['total_units']) || (int) $row['total_units'] < 0)) {
                $rowErrors[] = 'total_units must be a non-negative integer';
            }

            if (! empty($row['available_units']) && (! is_numeric($row['available_units']) || (int) $row['available_units'] < 0)) {
                $rowErrors[] = 'available_units must be a non-negative integer';
            }

            if (
                ! empty($row['total_units']) && ! empty($row['available_units'])
                && (int) $row['available_units'] > (int) $row['total_units']
            ) {
                $rowErrors[] = 'available_units cannot exceed total_units';
            }

            if (! empty($row['bedrooms']) && (! is_numeric($row['bedrooms']) || (int) $row['bedrooms'] < 0)) {
                $rowErrors[] = 'bedrooms must be a non-negative integer';
            }

            if (! empty($row['bathrooms']) && (! is_numeric($row['bathrooms']) || (int) $row['bathrooms'] < 0)) {
                $rowErrors[] = 'bathrooms must be a non-negative integer';
            }

            if (! empty($row['build_area']) && ! is_numeric($row['build_area'])) {
                $rowErrors[] = 'build_area must be numeric';
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNum,
                    'title' => $row['title'] ?? '(no title)',
                    'errors' => $rowErrors,
                ];
            }
        }

        return $errors;
    }

    public function preview(array $validRows, Projects $project): array
    {
        $preview = [];
        foreach ($validRows as $i => $row) {
            $unitCode = ! empty($row['unit_code'])
                ? Str::upper(Str::slug(trim($row['unit_code']), '_'))
                : null;

            $features = $this->extractFeatures($row);

            $existing = ProjectPlans::where('project_id', $project->id)
                ->where(function ($q) use ($unitCode, $row) {
                    if ($unitCode) {
                        $q->where('title', trim($row['title']));
                    } else {
                        $q->where('title', trim($row['title']));
                    }
                })
                ->first();

            $preview[] = [
                'row' => $i + 2,
                'title' => $row['title'] ?? '',
                'unit_code' => $unitCode ?: '',
                'price' => $row['price'] ?? null,
                'currency' => ! empty($row['currency']) ? strtoupper(trim($row['currency'])) : 'USD',
                'total_units' => $row['total_units'] ?? null,
                'available_units' => $row['available_units'] ?? null,
                'bedrooms' => $row['bedrooms'] ?? null,
                'bathrooms' => $row['bathrooms'] ?? null,
                'build_area' => $row['build_area'] ?? null,
                'features' => $features,
                'exists' => $existing !== null,
            ];
        }

        return $preview;
    }

    public function import(array $validRows, Projects $project): array
    {
        $created = 0;
        $updated = 0;
        $normalizedPlans = [];

        foreach ($validRows as $row) {
            $unitCode = ! empty($row['unit_code'])
                ? Str::upper(Str::slug(trim($row['unit_code']), '_'))
                : null;

            $features = $this->extractFeatures($row);

            $plan = ProjectPlans::where('project_id', $project->id)
                ->where('title', trim($row['title']))
                ->first();

            if (! $plan) {
                $plan = new ProjectPlans;
                $plan->project_id = $project->id;
                $created++;
            } else {
                $updated++;
            }

            $plan->fill([
                'title' => trim($row['title']),
                'bedrooms' => ! empty($row['bedrooms']) ? (int) $row['bedrooms'] : null,
                'bathrooms' => ! empty($row['bathrooms']) ? (int) $row['bathrooms'] : null,
                'build_area' => ! empty($row['build_area']) ? (float) $row['build_area'] : null,
                'features' => ! empty($features) ? $features : null,
            ]);

            $plan->save();

            $normalizedPlans[] = [
                'id' => $plan->id,
                'title' => $plan->title,
                'unit_code' => $unitCode,
                'price' => ! empty($row['price']) ? $row['price'] : null,
                'currency' => ! empty($row['currency']) ? strtoupper(trim($row['currency'])) : 'USD',
                'total_units' => ! empty($row['total_units']) ? (int) $row['total_units'] : null,
                'available_units' => ! empty($row['available_units']) ? (int) $row['available_units'] : null,
                'unit_status' => null,
                'category_id' => null,
                'country' => null,
                'state' => null,
                'city' => null,
                'location' => null,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        try {
            $this->syncService->syncProjectUnitsFromPlans($project, $normalizedPlans);
        } catch (\Exception $e) {
            Log::error('Bulk import unit sync failed: '.$e->getMessage(), [
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($validRows),
        ];
    }

    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return $rows;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return $rows;
        }

        $headers = array_map(fn ($h) => trim(strtolower(str_replace(' ', '_', $h))), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = isset($data[$idx]) ? trim($data[$idx]) : '';
            }
            if (! empty(array_filter($row, fn ($v) => $v !== ''))) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parseXlsx(UploadedFile $file): array
    {
        $rows = [];

        try {
            $reader = ReaderEntityFactory::createReaderFromFile($file->getRealPath());
            $reader->open($file->getRealPath());

            $headers = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $idx => $row) {
                    $cells = [];
                    foreach ($row->getCells() as $cell) {
                        $cells[] = trim((string) $cell->getValue());
                    }

                    if ($idx === 1) {
                        $headers = array_map(fn ($h) => trim(strtolower(str_replace(' ', '_', $h))), $cells);
                        continue;
                    }

                    $parsedRow = [];
                    foreach ($headers as $hIdx => $header) {
                        $parsedRow[$header] = $cells[$hIdx] ?? '';
                    }

                    if (! empty(array_filter($parsedRow, fn ($v) => $v !== ''))) {
                        $rows[] = $parsedRow;
                    }
                }
                break;
            }

            $reader->close();
        } catch (\Exception $e) {
            Log::error('XLSX parse failed: '.$e->getMessage());
        }

        return $rows;
    }

    private function extractFeatures(array $row): array
    {
        $features = [];
        foreach ($row as $key => $value) {
            if (in_array($key, self::$fixedColumns, true)) {
                continue;
            }
            if ($value !== '' && $value !== null) {
                $features[$key] = $value;
            }
        }

        return $features;
    }
}
