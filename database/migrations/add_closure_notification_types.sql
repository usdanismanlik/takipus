-- Migration: Add missing notification types for closure process
-- Date: 2025-12-25

ALTER TABLE notifications 
MODIFY COLUMN type ENUM(
    'action_created', 
    'action_assigned', 
    'checklist_nonconformity', 
    'action_completed', 
    'action_overdue', 
    'action_due_reminder', 
    'action_status_changed',
    'closure_requested',
    'closure_approved',
    'closure_rejected',
    'closure_completed',
    'upper_approval_required'
) NOT NULL;
