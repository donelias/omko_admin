<?php

namespace App\Services;

use App\Models\Projects;
use App\Models\ProjectPlans;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;

class BulkProjectUnitImportService
{
    private ProjectUnitSyncService $syncService;
    private const DOCUMENT_PATH = 'project_documents/';

    private static array $fixedColumns = [
        'unit_code', 'title', 'price', 'currency', 'total_units',
        'available_units', 'sold_units', 'reserved_units',
        'bedrooms', 'bathrooms', 'build_area',
    ];

    private static array $esToEn = [
        'codigo' => 'unit_code', 'cod' => 'unit_code',
        'código' => 'unit_code', 'unidad' => 'unit_code',
        'code' => 'unit_code', 'clave' => 'unit_code',
        'titulo' => 'title', 'título' => 'title',
        'nombre' => 'title', 'name' => 'title',
        'descripcion' => 'title', 'descripción' => 'title',
        'tipo_de_unidad' => 'title', 'tipo_unidad' => 'title',
        'modelo' => 'title',
        'tipo' => 'title', 'tipologia' => 'title', 'tipología' => 'title',
        'precio' => 'price', 'precio_usd' => 'price',
        'precio_dop' => 'price', 'valor' => 'price',
        'costo' => 'price',
        'moneda' => 'currency', 'divisa' => 'currency',
        'total_unidades' => 'total_units', 'total' => 'total_units',
        'unidades_totales' => 'total_units', 'cantidad' => 'total_units',
        'unidades' => 'total_units', 'n_unidades' => 'total_units',
        'unidades_disponibles' => 'available_units', 'disponibles' => 'available_units',
        'disponibilidad' => 'available_units',
        'habitaciones' => 'bedrooms', 'cuartos' => 'bedrooms', 'dormitorios' => 'bedrooms',
        'recamaras' => 'bedrooms', 'recámaras' => 'bedrooms',
        'hab' => 'bedrooms', 'hab_' => 'bedrooms', 'hb' => 'bedrooms',
        'banos' => 'bathrooms', 'baños' => 'bathrooms', 'banos_completos' => 'bathrooms',
        'banos_completos' => 'bathrooms', 'baños_completos' => 'bathrooms',
        'wc' => 'bathrooms',
        'area_construida' => 'build_area', 'area' => 'build_area', 'área' => 'build_area',
        'metros_cuadrados' => 'build_area', 'm2' => 'build_area',
        'metros' => 'build_area', 'tamano' => 'build_area', 'tamaño' => 'build_area',
        'superficie' => 'build_area',
        'vendidas' => 'sold_units', 'vendido' => 'sold_units',
        'vendida' => 'sold_units', 'sold' => 'sold_units',
        'reservadas' => 'reserved_units', 'reservado' => 'reserved_units',
        'reservada' => 'reserved_units', 'reserved' => 'reserved_units',
    ];

    private function normalizeHeader(string $header): string
    {
        $normalized = trim(strtolower(str_replace([' ', '-', '/', '\\', '.'], '_', $header)));
        return self::$esToEn[$normalized] ?? $normalized;
    }

    private function isHeaderRow(array $cells): bool
    {
        $known = array_merge(self::$fixedColumns, array_keys(self::$esToEn));
        foreach ($cells as $cell) {
            $val = trim(strtolower((string) $cell));
            if ($val !== '' && in_array($val, $known, true)) {
                return true;
            }
        }
        return false;
    }

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

            if (! empty($row['sold_units']) && (! is_numeric($row['sold_units']) || (int) $row['sold_units'] < 0)) {
                $rowErrors[] = 'sold_units must be a non-negative integer';
            }

            if (! empty($row['reserved_units']) && (! is_numeric($row['reserved_units']) || (int) $row['reserved_units'] < 0)) {
                $rowErrors[] = 'reserved_units must be a non-negative integer';
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

    private function isSummaryRow(array $row): bool
    {
        $title = ! empty($row['title']) ? strtoupper(trim($row['title'])) : '';
        if (in_array($title, ['TOTAL', 'SUBTOTAL', 'SUB TOTAL', 'GRAN TOTAL'], true)) {
            return true;
        }
        return false;
    }

    private function deriveStatusFields(array &$row): void
    {
        $estado = ! empty($row['estado']) ? strtoupper(trim($row['estado'])) : '';
        if ($estado === '') {
            if (empty($row['unit_status'])) {
                $row['unit_status'] = 'available';
            }
            return;
        }

        if ($estado === 'VENDIDO' || $estado === 'SOLD') {
            if (! array_key_exists('sold_units', $row) || empty($row['sold_units'])) {
                $row['sold_units'] = $row['total_units'] ?? 0;
            }
            $row['unit_status'] = 'sold_out';
            $row['available_units'] = 0;
            if (empty($row['price'])) {
                $row['price'] = '0';
            }
        } elseif ($estado === 'RESERVADO' || $estado === 'RESERVED') {
            if (! array_key_exists('reserved_units', $row) || empty($row['reserved_units'])) {
                $row['reserved_units'] = $row['total_units'] ?? 0;
            }
            $row['unit_status'] = 'low_stock';
            $row['available_units'] = 0;
        } else {
            if (empty($row['unit_status'])) {
                $row['unit_status'] = 'available';
            }
        }
    }

    public function preview(array $validRows, Projects $project): array
    {
        $preview = [];
        foreach ($validRows as $i => $row) {
            $this->deriveStatusFields($row);

            $unitCode = ! empty($row['unit_code'])
                ? Str::upper(Str::slug(trim($row['unit_code']), '_'))
                : null;

            $features = $this->extractFeatures($row);

            $existing = ProjectPlans::where('project_id', $project->id)
                ->where('title', trim($row['title']))
                ->first();

            $preview[] = [
                'row' => $i + 2,
                'title' => $row['title'] ?? '',
                'unit_code' => $unitCode ?: '',
                'price' => $row['price'] ?? null,
                'currency' => ! empty($row['currency']) ? strtoupper(trim($row['currency'])) : (system_setting('currency_code') ?: 'USD'),
                'total_units' => $row['total_units'] ?? null,
                'available_units' => $row['available_units'] ?? null,
                'sold_units' => $row['sold_units'] ?? null,
                'reserved_units' => $row['reserved_units'] ?? null,
                'unit_status' => $row['unit_status'] ?? null,
                'bedrooms' => $row['bedrooms'] ?? null,
                'bathrooms' => $row['bathrooms'] ?? null,
                'build_area' => $row['build_area'] ?? null,
                'features' => $features,
                'exists' => $existing !== null,
            ];
        }

        return $preview;
    }

    /**
     * @param array $rows          Parsed row data from CSV/XLSX
     * @param Projects $project
     * @param array $imageFiles    UploadedFile[] keyed by row index
     * @param array $sameAsPrev    bool[] keyed by row index — true = use previous row's image
     */
    public function importWithImages(array $rows, Projects $project, array $imageFiles, array $sameAsPrev): array
    {
        $created = 0;
        $updated = 0;
        $normalizedPlans = [];
        $path = config('global.PROJECT_DOCUMENT_PATH');
        $resolvedImages = [];

        foreach ($rows as $i => $row) {
            $this->deriveStatusFields($row);

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

            $hasFile = ! empty($imageFiles[$i]) && $imageFiles[$i] instanceof UploadedFile;
            $usePrev = ! empty($sameAsPrev[$i]) && $i > 0;

            if ($hasFile && $plan->exists && $plan->getRawOriginal('document')) {
                $plan->document = FileService::compressAndReplace(
                    $imageFiles[$i], $path, $plan->getRawOriginal('document'), true
                );
            } elseif ($hasFile) {
                $plan->document = FileService::compressAndUpload($imageFiles[$i], $path, true);
            } elseif ($usePrev && isset($resolvedImages[$i - 1])) {
                $plan->document = $resolvedImages[$i - 1];
            }

            $plan->save();
            $resolvedImages[$i] = $plan->document;

            $normalizedPlans[] = [
                'id' => $plan->id,
                'title' => $plan->title,
                'unit_code' => $unitCode,
                'price' => ! empty($row['price']) ? $row['price'] : null,
                'currency' => ! empty($row['currency']) ? strtoupper(trim($row['currency'])) : (system_setting('currency_code') ?: 'USD'),
                'total_units' => ! empty($row['total_units']) ? (int) $row['total_units'] : null,
                'available_units' => ! empty($row['available_units']) ? (int) $row['available_units'] : null,
                'sold_units' => ! empty($row['sold_units']) ? (int) $row['sold_units'] : null,
                'reserved_units' => ! empty($row['reserved_units']) ? (int) $row['reserved_units'] : null,
                'unit_status' => $row['unit_status'] ?? null,
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
            'total' => count($rows),
        ];
    }

    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return $rows;
        }

        $headers = [];
        $foundHeader = false;

        while (($data = fgetcsv($handle)) !== false) {
            if (! $foundHeader) {
                if ($data !== null && $this->isHeaderRow($data)) {
                    $rawHeaders = array_map(fn ($h) => $this->normalizeHeader((string) $h), $data);
                    $headers = [];
                    foreach ($rawHeaders as $idx => $h) {
                        if ($h !== '') {
                            $headers[$idx] = $h;
                        }
                    }
                    $foundHeader = true;
                }
                continue;
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $value = isset($data[$idx]) ? trim($data[$idx]) : '';
                if ($value !== '' && $value !== null) {
                    $row[$header] = $value;
                } elseif (! array_key_exists($header, $row) || $row[$header] === '' || $row[$header] === null) {
                    $row[$header] = $value;
                }
            }
            if (! empty(array_filter($row, fn ($v) => $v !== '')) && ! $this->isSummaryRow($row)) {
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
            $reader = new XLSXReader();
            $reader->open($file->getRealPath());

            $headers = [];
            $foundHeader = false;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($row->isEmpty()) {
                        continue;
                    }

                    $cells = $row->toArray();

                    if (! $foundHeader) {
                        if ($this->isHeaderRow($cells)) {
                            $rawHeaders = array_map(fn ($h) => $this->normalizeHeader((string) $h), $cells);
                            $headers = [];
                            foreach ($rawHeaders as $idx => $h) {
                                if ($h !== '') {
                                    $headers[$idx] = $h;
                                }
                            }
                            $foundHeader = true;
                        }
                        continue;
                    }

                    $parsedRow = [];
                    foreach ($headers as $hIdx => $header) {
                        $value = $cells[$hIdx] ?? '';
                        if ($value !== '' && $value !== null) {
                            $parsedRow[$header] = $value;
                        } elseif (! array_key_exists($header, $parsedRow) || $parsedRow[$header] === '' || $parsedRow[$header] === null) {
                            $parsedRow[$header] = $value;
                        }
                    }

                    $parsedRow = array_map(fn ($v) => $v === null ? '' : $v, $parsedRow);
                    if (! empty(array_filter($parsedRow, fn ($v) => $v !== '')) && ! $this->isSummaryRow($parsedRow)) {
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
