-- Migration: Two-Stage Action Closure Approval System
-- Created: 2025-12-23
-- Description: Adds support for two-stage approval process for action closures

-- 1. Add checklist_id and upper_approver_id to actions table
ALTER TABLE actions 
ADD COLUMN checklist_id INT NULL COMMENT 'İlişkili checklist ID (field tour aksiyonları için)' AFTER response_id,
ADD COLUMN upper_approver_id INT NULL COMMENT 'Manuel aksiyonlarda üst amir ID (ikinci onay için)' AFTER assigned_to_department_id;

-- 2. Add foreign key for checklist_id
ALTER TABLE actions
ADD FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE SET NULL;

-- 3. Update action_closures status enum to include first_approved
ALTER TABLE action_closures 
MODIFY COLUMN status ENUM('pending', 'first_approved', 'approved', 'rejected') DEFAULT 'pending';

-- 4. Populate checklist_id for existing field tour actions
UPDATE actions a
JOIN field_tours ft ON a.field_tour_id = ft.id
SET a.checklist_id = ft.checklist_id
WHERE a.field_tour_id IS NOT NULL AND a.checklist_id IS NULL;
