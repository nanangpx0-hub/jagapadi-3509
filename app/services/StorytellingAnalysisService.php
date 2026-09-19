<?php

declare(strict_types=1);

/** Pure, deterministic statistical analyses for storytelling chart series. */
final class StorytellingAnalysisService
{
    private const METHODS = ['trend', 'correlation', 'predictive', 'clustering', 'outlier'];
    private const SUPPORTED_VARIABLES = ['rain', 'pest', 'irrigation', 'wind'];

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
            'algorithm_version' => '1.1.0',
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }

    public static function methods(): array
    {
        return self::METHODS;
    }

    public static function supportedVariables(): array
    {
        return self::SUPPORTED_VARIABLES;
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
            'irrigation' => array_values($datasets[3]['data'] ?? []),
            'wind' => array_values($datasets[4]['data'] ?? []),
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
        $variable = strtolower(trim((string) ($parameters['variable'] ?? 'rain')));
        if (!in_array($variable, self::SUPPORTED_VARIABLES, true)) {
            throw new InvalidArgumentException('Variabel korelasi harus rain, pest, irrigation, atau wind.');
        }
        $coefficientType = strtolower(trim((string) ($parameters['coefficient'] ?? 'pearson')));
        if (!in_array($coefficientType, ['pearson', 'spearman'], true)) {
            $coefficientType = 'pearson';
        }

        if (empty($series[$variable]) || count($this->numericValues($series[$variable])) === 0) {
            throw new DomainException("Data variabel {$variable} tidak tersedia dalam deret waktu yang dipilih.");
        }

        [$x, $y, $labels] = $this->paired($series[$variable], $series['production'], $series['labels']);
        if (count($x) < 3) {
            throw new DomainException('Korelasi membutuhkan minimal 3 pasangan data lengkap.');
        }

        $coefficient = $coefficientType === 'spearman'
            ? $this->spearman($x, $y)
            : $this->pearson($x, $y);

        $n = count($x);
        $df = $n - 2;
        $tStat = null;
        $pValue = null;

        if ($df > 0) {
            if (abs($coefficient) >= 0.999999) {
                $tStat = $coefficient > 0 ? 999.0 : -999.0;
                $pValue = 0.0001;
            } else {
                $tStat = round($coefficient * sqrt($df / (1.0 - ($coefficient ** 2))), 4);
                $pValue = round($this->calculatePValue(abs($tStat), $df), 4);
            }
        }

        $isSignificant = $pValue !== null && $pValue < 0.05;
        $significanceText = $pValue !== null
            ? ($isSignificant ? sprintf('signifikan secara statistik (p = %.4f)', $pValue) : sprintf('tidak signifikan (p = %.4f)', $pValue))
            : 'signifikansi belum terhitung';

        $summary = sprintf(
            'Korelasi %s %s terhadap produksi adalah %.3f (%s); hasil tidak membuktikan kausalitas.',
            ucfirst($coefficientType),
            $variable,
            $coefficient,
            $significanceText
        );

        return $this->result(
            ['variable' => $variable, 'coefficient' => $coefficientType],
            $summary,
            [
                'variable' => $variable,
                'coefficient_type' => $coefficientType,
                'coefficient_value' => round($coefficient, 6),
                'correlation_coefficient' => round($coefficient, 6),
                'pearson_r' => round($coefficient, 6),
                'strength' => $this->correlationStrength($coefficient),
                't_statistic' => $tStat,
                'p_value' => $pValue,
                'is_significant' => $isSignificant,
                'degrees_of_freedom' => $df,
            ],
            ['labels' => $labels, 'series' => ['x' => $x, 'production' => $y]],
            $n
        );
    }

    private function predictive(array $series, array $parameters): array
    {
        $horizon = $this->boundedInt($parameters['horizon'] ?? 3, 1, 12, 'Horizon');
        $values = $this->numericValues($series['production']);
        if (count($values) < 3) {
            throw new DomainException('Prediksi membutuhkan minimal 3 observasi produksi lengkap.');
        }
        $x = range(0, count($values) - 1);
        [$slope, $intercept] = $this->linearRegression($x, $values);

        // Calculate Root Mean Squared Error (RMSE) of historical fit
        $sse = 0.0;
        foreach ($values as $i => $actual) {
            $predicted = $intercept + $slope * $i;
            $sse += ($actual - $predicted) ** 2;
        }
        $rmse = round(sqrt($sse / count($values)), 3);

        $forecast = [];
        $lowerBound = [];
        $upperBound = [];
        for ($step = 1; $step <= $horizon; $step++) {
            $t = count($values) - 1 + $step;
            $val = max(0.0, round($intercept + $slope * $t, 3));
            $forecast[] = $val;
            $lowerBound[] = max(0.0, round($val - 1.96 * $rmse, 3));
            $upperBound[] = round($val + 1.96 * $rmse, 3);
        }

        return $this->result(
            ['horizon' => $horizon, 'model' => 'linear_regression_baseline'],
            'Prediksi baseline menggunakan regresi linear dengan interval kepercayaan 95%; validasi dengan backtesting sebelum keputusan operasional.',
            [
                'slope' => round($slope, 6),
                'intercept' => round($intercept, 6),
                'rmse' => $rmse,
                'confidence_level' => '95%',
            ],
            [
                'labels' => $series['labels'],
                'series' => [
                    'history' => $values,
                    'forecast' => $forecast,
                    'lower_bound' => $lowerBound,
                    'upper_bound' => $upperBound,
                ],
            ],
            count($values)
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
            ['labels' => $labels, 'series' => ['production' => $production, 'rain' => $rain, 'cluster' => $assignments]],
            count($scores)
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
            ['labels' => $series['labels'], 'series' => ['production' => $values], 'outliers' => $outliers],
            count($values)
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
        $count = count($x);
        if ($count === 0) {
            return 0.0;
        }
        $meanX = array_sum($x) / $count;
        $meanY = array_sum($y) / $count;
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

    private function spearman(array $x, array $y): float
    {
        $rankX = $this->calculateRanks($x);
        $rankY = $this->calculateRanks($y);
        return $this->pearson($rankX, $rankY);
    }

    private function calculateRanks(array $values): array
    {
        $indexed = [];
        foreach ($values as $i => $v) {
            $indexed[] = ['index' => $i, 'val' => (float) $v];
        }
        usort($indexed, static fn ($a, $b): int => $a['val'] <=> $b['val']);

        $ranks = [];
        $n = count($indexed);
        $i = 0;
        while ($i < $n) {
            $j = $i;
            while ($j < $n - 1 && abs($indexed[$j + 1]['val'] - $indexed[$j]['val']) < 0.000001) {
                $j++;
            }
            $avgRank = ($i + 1 + $j + 1) / 2.0;
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$indexed[$k]['index']] = $avgRank;
            }
            $i = $j + 1;
        }
        ksort($ranks);
        return array_values($ranks);
    }

    /**
     * Approximate two-tailed p-value from Student's t distribution.
     */
    private function calculatePValue(float $t, int $df): float
    {
        if ($df <= 0) {
            return 1.0;
        }
        $t = abs($t);
        // Standard normal approximation for df >= 30
        if ($df >= 30) {
            $z = $t;
            $p = 2.0 * (1.0 - 0.5 * (1.0 + $this->erf($z / sqrt(2))));
            return max(0.0001, min(1.0, $p));
        }

        // Numerical approximation for smaller df
        $x = $df / ($df + $t * $t);
        $a = 0.5 * $df;
        $b = 0.5;

        // Incomplete beta approximation
        $beta = $this->incompleteBetaApproximation($x, $a, $b);
        return max(0.0001, min(1.0, $beta));
    }

    private function erf(float $x): float
    {
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;

        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    private function incompleteBetaApproximation(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }
        // Simpson integration of beta density function
        $steps = 60;
        $h = $x / $steps;
        $sum = 0.0;
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i * $h;
            if ($t <= 0.0 || $t >= 1.0) {
                continue;
            }
            $weight = ($i === 0 || $i === $steps) ? 1 : (($i % 2 === 1) ? 4 : 2);
            $density = ($t ** ($a - 1.0)) * ((1.0 - $t) ** ($b - 1.0));
            $sum += $weight * $density;
        }
        $integral = ($h / 3.0) * $sum;

        // Complete beta function B(a, b) = Gamma(a)*Gamma(b)/Gamma(a+b)
        // NOTE: memakai logGamma() internal agar tidak bergantung pada
        // ekstensi lgamma() yang tidak tersedia di sebagian build PHP.
        $logBeta = $this->logGamma($a) + $this->logGamma($b) - $this->logGamma($a + $b);
        $completeBeta = exp($logBeta);

        return $completeBeta > 0 ? $integral / $completeBeta : 1.0;
    }

    /**
     * Aproksimasi Lanczos untuk ln(Gamma(x)), x > 0.
     * Pengganti mandiri lgamma() agar deterministik lintas build PHP.
     */
    private function logGamma(float $x): float
    {
        if ($x <= 0.0) {
            return INF;
        }
        $coeff = [
            0.99999999999980993, 676.5203681218851, -1259.1392167224028,
            771.32342877765313, -176.61502916214059, 12.507343278686905,
            -0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7,
        ];
        if ($x < 0.5) {
            return log(M_PI / (sin(M_PI * $x) * exp($this->logGamma(1.0 - $x))));
        }
        $x -= 1.0;
        $a = $coeff[0];
        for ($i = 1; $i < 9; $i++) {
            $a += $coeff[$i] / ($x + $i);
        }
        $t = $x + 7.5;
        return 0.5 * log(2.0 * M_PI) + ($x + 0.5) * log($t) - $t + log($a);
    }

    private function linearRegression(array $x, array $y): array
    {
        $count = count($x);
        if ($count === 0) {
            return [0.0, 0.0];
        }
        $meanX = array_sum($x) / $count;
        $meanY = array_sum($y) / $count;
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
