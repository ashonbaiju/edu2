-- =============================================
-- FIXING DOUBTS TABLE SCHEMA
-- This script adds the missing teacher_id column
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Check if teacher_id exists, if not add it
ALTER TABLE `doubts` ADD COLUMN IF NOT EXISTS `teacher_id` INT DEFAULT NULL AFTER `subject_id`;

-- Add foreign key constraint to ensure data integrity
-- (If it already exists, this command might fail, which is fine)
ALTER TABLE `doubts` 
ADD CONSTRAINT `doubts_ibfk_teacher` 
FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
