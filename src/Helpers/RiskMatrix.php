<?php

namespace Src\Helpers;

class RiskMatrix
{
    /**
     * Risk seviyesi hesapla (Olasılık x Şiddet)
     * 
     * @param int $probability Olasılık (1-5)
     * @param int $severity Şiddet (1-5)
     * @return array ['score' => int, 'level' => string, 'color' => string]
     */
    public static function calculateRisk(int $probability, int $severity): array
    {
        $score = $probability * $severity;
        
        return [
            'score' => $score,
            'level' => self::getRiskLevel($score),
            'color' => self::getRiskColor($score),
            'priority' => self::getRiskPriority($score),
        ];
    }

    /**
     * Risk seviyesi belirle
     */
    private static function getRiskLevel(int $score): string
    {
        if ($score >= 20) return 'very_high';      // 20-25
        if ($score >= 15) return 'high';           // 15-19
        if ($score >= 10) return 'medium';         // 10-14
        if ($score >= 5) return 'low';             // 5-9
        return 'very_low';                         // 1-4
    }

    /**
     * Risk rengi belirle (UI için)
     */
    private static function getRiskColor(int $score): string
    {
        if ($score >= 20) return '#DC2626';  // Kırmızı - Çok Yüksek
        if ($score >= 15) return '#EA580C';  // Turuncu - Yüksek
        if ($score >= 10) return '#F59E0B';  // Sarı - Orta
        if ($score >= 5) return '#10B981';   // Yeşil - Düşük
        return '#6B7280';                    // Gri - Çok Düşük
    }

    /**
     * Risk bazlı öncelik belirle
     */
    private static function getRiskPriority(int $score): string
    {
        if ($score >= 15) return 'high';
        if ($score >= 10) return 'medium';
        return 'low';
    }

    /**
     * Risk matrisi tablosu (5x5)
     */
    public static function getMatrix(): array
    {
        $matrix = [];
        
        for ($severity = 5; $severity >= 1; $severity--) {
            $row = [];
            for ($probability = 1; $probability <= 5; $probability++) {
                $risk = self::calculateRisk($probability, $severity);
                $row[] = [
                    'probability' => $probability,
                    'severity' => $severity,
                    'score' => $risk['score'],
                    'level' => $risk['level'],
                    'color' => $risk['color'],
                ];
            }
            $matrix[] = $row;
        }
        
        return $matrix;
    }

    /**
     * Risk seviyesi açıklamaları
     */
    public static function getRiskLevelDescriptions(): array
    {
        return [
            'very_high' => [
                'label' => 'Çok Yüksek Risk',
                'description' => 'Acil müdahale gerektirir. İşi durdurun.',
                'action' => 'Derhal aksiyon alınmalı',
                'color' => '#DC2626',
                'score_range' => '20-25',
            ],
            'high' => [
                'label' => 'Yüksek Risk',
                'description' => 'Öncelikli müdahale gerektirir.',
                'action' => '24 saat içinde aksiyon alınmalı',
                'color' => '#EA580C',
                'score_range' => '15-19',
            ],
            'medium' => [
                'label' => 'Orta Risk',
                'description' => 'Planlı müdahale gerektirir.',
                'action' => '1 hafta içinde aksiyon alınmalı',
                'color' => '#F59E0B',
                'score_range' => '10-14',
            ],
            'low' => [
                'label' => 'Düşük Risk',
                'description' => 'İzleme ve kontrol yeterlidir.',
                'action' => '1 ay içinde aksiyon alınmalı',
                'color' => '#10B981',
                'score_range' => '5-9',
            ],
            'very_low' => [
                'label' => 'Çok Düşük Risk',
                'description' => 'Kabul edilebilir risk seviyesi.',
                'action' => 'Rutin kontrol yeterlidir',
                'color' => '#6B7280',
                'score_range' => '1-4',
            ],
        ];
    }

    /**
     * Olasılık seviyeleri
     */
    public static function getProbabilityLevels(): array
    {
        return [
            1 => ['label' => 'Çok Nadir', 'description' => 'Yılda bir veya daha az'],
            2 => ['label' => 'Nadir', 'description' => 'Yılda birkaç kez'],
            3 => ['label' => 'Olası', 'description' => 'Ayda birkaç kez'],
            4 => ['label' => 'Muhtemel', 'description' => 'Haftada birkaç kez'],
            5 => ['label' => 'Çok Muhtemel', 'description' => 'Günlük veya sürekli'],
        ];
    }

    /**
     * Şiddet seviyeleri
     */
    public static function getSeverityLevels(): array
    {
        return [
            1 => ['label' => 'Önemsiz', 'description' => 'İlk yardım gerektirmeyen'],
            2 => ['label' => 'Küçük', 'description' => 'İlk yardım gerektirir'],
            3 => ['label' => 'Orta', 'description' => 'Tıbbi tedavi gerektirir'],
            4 => ['label' => 'Ciddi', 'description' => 'Kalıcı hasar, iş kaybı'],
            5 => ['label' => 'Ölümcül', 'description' => 'Ölüm veya kalıcı sakatlık'],
        ];
    }
}
