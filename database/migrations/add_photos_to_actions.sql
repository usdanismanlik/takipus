-- Migration: Add photos column to actions table
-- Date: 2025-12-14

ALTER TABLE actions 
ADD COLUMN photos JSON COMMENT 'Aksiyon fotoğrafları (URL array)' 
AFTER description;
