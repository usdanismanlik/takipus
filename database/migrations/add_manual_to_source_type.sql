-- Migration: Add 'manual' to source_type enum
-- Date: 2025-12-14

ALTER TABLE actions 
MODIFY COLUMN source_type ENUM('field_tour', 'periodic_inspection', 'third_party_audit', 'free_nonconformity', 'manual', 'other') 
DEFAULT 'other' 
COMMENT 'Aksiyon kaynağı';
