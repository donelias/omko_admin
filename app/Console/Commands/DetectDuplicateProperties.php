<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;

/**
 * FASE 7 (T5) — Detección de propiedades duplicadas.
 */
class DetectDuplicateProperties extends Command
{
    protected $signature = 'properties:detect-duplicates
        {--price-tolerance=5 : margen en % para precios equivalentes}
        {--mark : marcar los duplicados (añade flag a edit_reason)}
        {--limit=0 : máximo de grupos a procesar (0 = todos)}';

    protected $description = 'Detecta propiedades duplicadas por título/ciudad/precio/coordenadas';

    public function handle(): int
    {
        $tolerance = max(0, (float) $this->option('price-tolerance'));
        $mark = (bool) $this->option('mark');
        $limit = (int) $this->option('limit');

        $this->info('Cargando propiedades activas...');

        $properties = Property::query()
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->get(['id', 'title', 'city', 'address', 'price', 'latitude', 'longitude', 'edit_reason']);

        if ($properties->isEmpty()) {
            $this->warn('No hay propiedades aprobadas para analizar.');

            return self::SUCCESS;
        }

        $buckets = [];
        foreach ($properties as $prop) {
            $key = $this->normalize($prop->title).'|'.mb_strtolower(trim((string) $prop->city));
            $buckets[$key][] = $prop;
        }

        $groupsReported = 0;
        $flagged = 0;
        $candidates = 0;

        foreach ($buckets as $group) {
            if (count($group) < 2) {
                continue;
            }

            $pairs = $this->findDuplicatePairs($group, $tolerance);

            if (empty($pairs)) {
                continue;
            }

            if ($limit > 0 && $groupsReported >= $limit) {
                break;
            }
            $groupsReported++;

            $this->line('');
            $this->line("Grupo: <fg=yellow>{$group[0]->title}</> (".count($group).' propiedades)');
            foreach ($pairs as $pair) {
                $this->line(sprintf('  #%d <-> #%d  precio %s vs %s  similitud %d%% (%s)',
                    $pair[0]->id, $pair[1]->id,
                    number_format($pair[0]->price), number_format($pair[1]->price),
                    $pair[2], $pair[3]
                ));
                $candidates++;
            }

            if ($mark) {
                $primary = array_shift($group);
                foreach ($group as $dup) {
                    $reason = trim((string) $dup->edit_reason);
                    $flag = '[duplicate-of:'.$primary->id.']';
                    if (! str_contains($reason, $flag)) {
                        $dup->edit_reason = trim($reason.' '.$flag);
                        $dup->save();
                        $flagged++;
                    }
                }
            }
        }

        $this->line('');
        $this->info("Análisis completado: {$groupsReported} grupo(s), {$candidates} par(es) de duplicados propuestos."
            .($mark ? " Marcados: {$flagged}." : ''));
        $this->line('Sugerencia: revisa manualmente antes de eliminar. Usa --mark para marcar con [duplicate-of:ID].');

        return self::SUCCESS;
    }

    /** @return array<int, array{0:object,1:object,2:int,3:string}> */
    private function findDuplicatePairs(array $group, float $tolerance): array
    {
        $pairs = [];
        $count = count($group);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $group[$i];
                $b = $group[$j];

                $priceMatch = $this->priceWithinTolerance($a->price, $b->price, $tolerance);
                $addressMatch = $this->sameAddress($a, $b);
                $coordMatch = $this->nearbyCoordinates($a, $b);

                if ($priceMatch || $addressMatch || $coordMatch) {
                    $reason = $priceMatch ? 'precio' : '';
                    if ($addressMatch) {
                        $reason .= '+dirección';
                    }
                    if ($coordMatch) {
                        $reason .= '+coordenadas';
                    }
                    $reason = ltrim($reason, '+');

                    $similarity = 60;
                    if ($priceMatch) {
                        $similarity += 20;
                    }
                    if ($addressMatch) {
                        $similarity += 10;
                    }
                    if ($coordMatch) {
                        $similarity += 10;
                    }

                    $pairs[] = [$a, $b, min(100, $similarity), $reason];
                }
            }
        }

        return $pairs;
    }

    private function priceWithinTolerance($priceA, $priceB, float $tolerance): bool
    {
        $a = (float) $priceA;
        $b = (float) $priceB;
        if ($a <= 0 && $b <= 0) {
            return true;
        }
        if ($a <= 0 || $b <= 0) {
            return false;
        }
        $diff = abs($a - $b) / max($a, $b) * 100;

        return $diff <= $tolerance;
    }

    private function sameAddress($a, $b): bool
    {
        $addrA = $this->normalize($a->address);
        $addrB = $this->normalize($b->address);

        return $addrA !== '' && $addrA === $addrB;
    }

    private function nearbyCoordinates($a, $b): bool
    {
        $latA = (float) $a->latitude;
        $latB = (float) $b->latitude;
        $lngA = (float) $a->longitude;
        $lngB = (float) $b->longitude;

        if ($latA == 0 && $lngA == 0 || $latB == 0 && $lngB == 0) {
            return false;
        }

        return abs($latA - $latB) < 0.0005 && abs($lngA - $lngB) < 0.0005;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $text
        );
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
