<?php

namespace Src\Models;

class FieldTourResponse extends Model
{
    protected string $table = 'field_tour_responses';

    protected array $fillable = [
        'field_tour_id',
        'question_id',
        'answer_type',
        'answer_value',
        'is_compliant',
        'notes',
        'photos',
        'location',
        'risk_score',
        'priority',
    ];

    public function getByTour(int $tourId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE field_tour_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tourId]);
        return $stmt->fetchAll();
    }

    public function getNonCompliantByTour(int $tourId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE field_tour_id = ? AND is_compliant = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tourId]);
        return $stmt->fetchAll();
    }
}
