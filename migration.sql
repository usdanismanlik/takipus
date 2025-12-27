-- Add qr_code_url column to periodic_inspections table
ALTER TABLE periodic_inspections ADD COLUMN qr_code_url VARCHAR(255) NULL AFTER notes;
