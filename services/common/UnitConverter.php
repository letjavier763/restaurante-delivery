<?php
// ==============================================================================
// UnitConverter.php - Conversión Unificada de Unidades de Medida Gastronómicas
// Soporte para: g, kg, lb, oz, ml, l, unid / piezas
// ==============================================================================

class UnitConverter {
    /**
     * Normalizar abreviaturas y variantes de unidades
     */
    public static function normalize(string $unit): string {
        $u = strtolower(trim($unit));
        return match($u) {
            'g', 'gr', 'grs', 'gramo', 'gramos', 'gram', 'grams' => 'g',
            'kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos', 'kilogram' => 'kg',
            'lb', 'lbs', 'libra', 'libras', 'pound', 'pounds' => 'lb',
            'oz', 'ozs', 'onza', 'onzas', 'ounce', 'ounces' => 'oz',
            'ml', 'mls', 'mililitro', 'mililitros', 'milliliter', 'milliliters', 'cc' => 'ml',
            'l', 'lt', 'lts', 'litro', 'litros', 'liter', 'liters' => 'l',
            'unid', 'unidad', 'unidades', 'unit', 'units', 'pz', 'pza', 'pzas', 'pieza', 'piezas' => 'unid',
            default => $u
        };
    }

    /**
     * Determina el tipo de magnitud de la unidad
     */
    public static function getMagnitudeType(string $unit): string {
        $norm = self::normalize($unit);
        if (in_array($norm, ['g', 'kg', 'lb', 'oz'], true)) return 'peso';
        if (in_array($norm, ['ml', 'l'], true)) return 'volumen';
        if (in_array($norm, ['unid'], true)) return 'unidad';
        return 'desconocido';
    }

    /**
     * Convierte una cantidad de una unidad a otra dentro de la misma dimensión física
     */
    public static function convert(float $amount, string $fromUnit, string $toUnit): float {
        $from = self::normalize($fromUnit);
        $to = self::normalize($toUnit);

        if ($from === $to) {
            return $amount;
        }

        // Tabla de factores de masa hacia Gramos (g)
        $weightFactors = [
            'g'  => 1.0,
            'kg' => 1000.0,
            'lb' => 453.59237,
            'oz' => 28.349523,
        ];

        if (isset($weightFactors[$from]) && isset($weightFactors[$to])) {
            $inGrams = $amount * $weightFactors[$from];
            return round($inGrams / $weightFactors[$to], 4);
        }

        // Tabla de factores de volumen hacia Mililitros (ml)
        $volumeFactors = [
            'ml' => 1.0,
            'l'  => 1000.0,
        ];

        if (isset($volumeFactors[$from]) && isset($volumeFactors[$to])) {
            $inMl = $amount * $volumeFactors[$from];
            return round($inMl / $volumeFactors[$to], 4);
        }

        // Unidades discretas
        if ($from === 'unid' && $to === 'unid') {
            return $amount;
        }

        // Densidad estándar para líquidos acuosos (1g ~ 1ml) si se combinan por error común
        if ($from === 'g' && $to === 'ml') return $amount;
        if ($from === 'ml' && $to === 'g') return $amount;
        if ($from === 'kg' && $to === 'l') return $amount;
        if ($from === 'l' && $to === 'kg') return $amount;

        // Si no son directamente convertibles, devolver la cantidad original
        return $amount;
    }

    /**
     * Formatear visualmente cantidad y unidad
     */
    public static function format(float $amount, string $unit): string {
        $norm = self::normalize($unit);
        $formattedNum = ($norm === 'unid') ? number_format($amount, 0) : number_format($amount, 3);
        // Eliminar ceros decimales innecesarios
        if (str_contains($formattedNum, '.')) {
            $formattedNum = rtrim(rtrim($formattedNum, '0'), '.');
        }
        return "{$formattedNum} {$norm}";
    }
}

