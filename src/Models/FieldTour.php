<?php

namespace Src\Models;

class FieldTour extends Model
{
    protected string $table = 'field_tours';

    protected array $fillable = [
        'checklist_id',
        'inspector_id',
        'location',
        'status',
        'started_at',
        'completed_at',
        'summary',
        'overall_score',
    ];

    public function withResponses(int $id): ?array
    {
        $fieldTour = $this->find($id);

        if (!$fieldTour) {
            return null;
        }

        $sql = "SELECT * FROM field_tour_responses WHERE field_tour_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $fieldTour['responses'] = $stmt->fetchAll();

        return $fieldTour;
    }

    public function complete(int $id, array $data): bool
    {
        $data['status'] = 'completed';
        $data['completed_at'] = date('Y-m-d H:i:s');

        return $this->update($id, $data);
    }
}
