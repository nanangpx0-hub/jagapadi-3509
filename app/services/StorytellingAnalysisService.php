<?php

declare(strict_types=1);

/** Pure, deterministic statistical analyses for storytelling chart series. */
final class StorytellingAnalysisService
{
    private const METHODS = ['trend', 'correlation', 'predictive', 'clustering', 'outlier'];

    public function analyze(string $method, array $chartData, array $parameters = []): array
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Metode analisis tidak didukung.');
        }
        $series = $this->extractSeries($chartData);
        $result = match ($method) {
            'trend' => $this->trend($series, $parameters),
            'correlation' => $this->correlation($series, $parameters),
            'predictive' => $this->predictive($series, $parameters),
            'clustering' => $this->clustering($series, $parameters),
            'outlier' => $this->outlier($series, $parameters),
        };
        return $result + [
            'method' => $method,
            'algorithm_version' => '1.0.0',
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }

    public static function methods(): array
    {
        return self::METHODS;
    }

    private function extractSeries(array $chartData): array
    {
        $labels = array_values($chartData['labels'] ?? []);
        $datasets = array_values($chartData['datasets'] ?? []);
        if ($labels === [] || count($datasets) < 3) {
            throw new InvalidArgumentException('Data seri storytelling tidak lengkap.');
        }
        return [
            'labels' => $labels,
            'production' => array_values($datasets[0]['data'] ?? []),
            'rain' => array_values($datasets[1]['data'] ?? []),
            'pest' => array_values($datasets[2]['data'] ?? []),
        ];
    }

    private function trend(array $series, array $parameters): array
    {
        $window = $this->boundedInt($parameters['window'] ?? 3, 2, 12, 'Window');
        $movingAverage = [];
        foreach ($series['production'] as $index => $value) {
            $start = max(0, $index - $window + 1);
            $slice = array_slice($series['production'], $start, $index - $start + 1);
            $numeric = $this->numericValues($slice);
            $movingAverage[] = count($slice) === $window && count($numeric) === $window
                ? round(array_sum($numeric) / $window, 3) : null;
        }
        $values = $this->numericValues($series['production']);
        if (count($values) < 2) {
            throw new DomainException('Analisis tren membutuhkan minimal 2 observasi produksi bulanan.');
        }
        $first = $values[0] ?? null;
        $last = $values[count($values) - 1] ?? null;
        $change = $first !== null && $last !== null && abs($first) > 0.000001
            ? (($last - $first) / abs($first)) * 100 : null;
        return $this->result(
            ['window' => $window],
            $change === null ? 'Data belum cukup untuk menghitung perubahan tren.'
                : sprintf('Perubahan produksi dari observasi awal ke akhir sebesar %.2f%%.', $change),
            ['change_percent' => $change === null ? null : round($change, 3)],
            ['labels' => $series['labels'], 'series' => ['production' => $series['production'], 'moving_average' => $movingAverage]],
            count($values)
        );
    }

    private function correlation(array $series, array $parameters): array
    {
        $variable = (string) ($parameters['variable'] ?? 'rain');
        if (!in_array($variable, ['rain', 'pest'], true)) {
            throw new InvalidArgumentException('Variabel korelasi harus rain atau pest.');
        }
        [$x, $y, $labels] = $this->paired($series[$variable], $series['production'], $series['labels']);
        if (count($x) < 3) {
            throw new DomainException('Korelasi membutuhkan minimal 3 pasangan data lengkap.');
        }
        $coefficient = $this->pearson($x, $y);
        return $this->result(
            ['variable' => $variable, 'coefficient' => 'pearson'],
            sprintf('Korelasi Pearson %s terhadap produksi adalah %.3f; hasil tidak membuktikan kausalitas.', $variable, $coefficient),
            ['pearson_r' => round($coefficient, 6), 'strength' => $this->correlationStrength($coefficient)],
            ['labels' => $labels, 'series' => ['x' => $x, 'production' => $y]], count($x)
        );
    }

    private function predictive(array $series, array $parameters): array
    {
        $horizon = $this->boundedInt($parameters['horizon'] ?? 3, 1, 12, 'Horizon');
        $values = $this->numericValues($series['production']);
        if (count($values) < 3) {
            throw new DomainException('Prediksi membutuhkan minimal 3 observasi produksi lengkap.');
        }
        [$slope, $intercept] = $this->linearRegression(range(0, count($values) - 1), $values);
        $forecast = [];
        for ($step = 1; $step <= $horizon; $step++) {
            $forecast[] = max(0.0, round($intercept + $slope * (count($values) - 1 + $step), 3));
        }
        return $this->result(
            ['horizon' => $horizon, 'model' => 'linear_regression_baseline'],
            'Prediksi baseline menggunakan regresi linear; validasi dengan backtesting sebelum keputusan operasional.',
            ['slope' => round($slope, 6), 'intercept' => round($intercept, 6)],
            ['labels' => $series['labels'], 'series' => ['history' => $values, 'forecast' => $forecast]], count($values)
        );
    }

    private function clustering(array $series, array $parameters): array
    {
        $clusters = $this->boundedInt($parameters['clusters'] ?? 3, 2, 5, 'Jumlah cluster');
        [$production, $rain, $labels] = $this->triples($series);
        if (count($production) < $clusters) {
            throw new DomainException('Observasi lengkap harus minimal sama dengan jumlah cluster.');
        }
        $pScaled = $this->minMax($production);
        $rScaled = $this->minMax($rain);
        $scores = [];
        foreach ($pScaled as $index => $value) {
            $scores[$index] = ($value + $rScaled[$index]) / 2;
        }
        asort($scores);
        $assignments = array_fill(0, count($scores), 0);
        $position = 0;
        foreach (array_keys($scores) as $index) {
            $assignments[$index] = min($clusters - 1, (int) floor($position++ * $clusters / count($scores)));
        }
        return $this->result(
            ['clusters' => $clusters, 'method' => 'quantile_segmentation'],
            sprintf('%d observasi dibagi menjadi %d segmen produksi-hujan.', count($scores), $clusters),
            ['cluster_counts' => array_count_values($assignments)],
            ['labels' => $labels, 'series' => ['production' => $production, 'rain' => $rain, 'cluster' => $assignments]], count($scores)
        );
    }

    private function outlier(array $series, array $parameters): array
    {
        $threshold = (float) ($parameters['threshold'] ?? 3.5);
        if ($threshold < 2.0 || $threshold > 10.0) {
            throw new InvalidArgumentException('Ambang outlier harus antara 2 dan 10.');
        }
        $values = $this->numericValues($series['production']);
        if (count($values) < 5) {
            throw new DomainException('Deteksi outlier membutuhkan minimal 5 observasi produksi lengkap.');
        }
        $median = $this->median($values);
        $deviations = array_map(static fn (float $value): float => abs($value - $median), $values);
        $mad = $this->median($deviations);
        $outliers = [];
        foreach ($values as $index => $value) {
            $score = $mad > 0.0 ? 0.6745 * ($value - $median) / $mad : 0.0;
            if (abs($score) > $threshold) {
                $outliers[] = ['index' => $index, 'value' => $value, 'robust_z' => round($score, 4)];
            }
        }
        return $this->result(
            ['threshold' => $threshold, 'method' => 'modified_z_score'],
            sprintf('Ditemukan %d anomali dari %d observasi produksi.', count($outliers), count($values)),
            ['median' => $median, 'mad' => $mad, 'outlier_count' => count($outliers)],
            ['labels' => $series['labels'], 'series' => ['production' => $values], 'outliers' => $outliers], count($values)
        );
    }

    private function result(array $parameters, string $summary, array $metrics, array $visualization, int $sampleSize): array
    {
        return compact('parameters', 'summary', 'metrics', 'visualization') + ['sample_size' => $sampleSize];
    }

    private function numericValues(array $values): array
    {
        return array_values(array_map('floatval', array_filter($values, static fn ($value): bool => is_numeric($value))));
    }

    private function paired(array $x, array $y, array $labels): array
    {
        $left = $right = $validLabels = [];
        foreach ($labels as $index => $label) {
            if (isset($x[$index], $y[$index]) && is_numeric($x[$index]) && is_numeric($y[$index])) {
                $left[] = (float) $x[$index];
                $right[] = (float) $y[$index];
                $validLabels[] = (string) $label;
            }
        }
        return [$left, $right, $validLabels];
    }

    private function triples(array $series): array
    {
        $production = $rain = $labels = [];
        foreach ($series['labels'] as $index => $label) {
            if (isset($series['production'][$index], $series['rain'][$index])
                && is_numeric($series['production'][$index]) && is_numeric($series['rain'][$index])) {
                $production[] = (float) $series['production'][$index];
                $rain[] = (float) $series['rain'][$index];
                $labels[] = (string) $label;
            }
        }
        return [$production, $rain, $labels];
    }

    private function pearson(array $x, array $y): float
    {
        $meanX = array_sum($x) / count($x);
        $meanY = array_sum($y) / count($y);
        $numerator = $sumX = $sumY = 0.0;
        foreach ($x as $index => $value) {
            $dx = $value - $meanX;
            $dy = $y[$index] - $meanY;
            $numerator += $dx * $dy;
            $sumX += $dx ** 2;
            $sumY += $dy ** 2;
        }
        $denominator = sqrt($sumX * $sumY);
        return $denominator > 0.0 ? $numerator / $denominator : 0.0;
    }

    private function linearRegression(array $x, array $y): array
    {
        $meanX = array_sum($x) / count($x);
        $meanY = array_sum($y) / count($y);
        $numerator = $denominator = 0.0;
        foreach ($x as $index => $value) {
            $numerator += ($value - $meanX) * ($y[$index] - $meanY);
            $denominator += ($value - $meanX) ** 2;
        }
        $slope = $denominator > 0.0 ? $numerator / $denominator : 0.0;
        return [$slope, $meanY - $slope * $meanX];
    }

    private function minMax(array $values): array
    {
        $min = min($values);
        $range = max($values) - $min;
        return array_map(static fn (float $value): float => $range > 0.0 ? ($value - $min) / $range : 0.5, $values);
    }

    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 === 0 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
    }

    private function correlationStrength(float $value): string
    {
        return match (true) {
            abs($value) >= 0.8 => 'sangat_kuat',
            abs($value) >= 0.6 => 'kuat',
            abs($value) >= 0.4 => 'sedang',
            abs($value) >= 0.2 => 'lemah',
            default => 'sangat_lemah',
        };
    }

    private function boundedInt(mixed $value, int $min, int $max, string $label): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $min || $number > $max) {
            throw new InvalidArgumentException("{$label} harus antara {$min} dan {$max}.");
        }
        return $number;
    }
}
