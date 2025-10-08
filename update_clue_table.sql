-- Database schema update for PuzzlePath WordPress Plugin
-- Run this SQL to add advanced answer validation fields to your clue table
-- 
-- IMPORTANT: Backup your database before running this script!

-- Add new columns to existing pp_clues table (WordPress plugin version)
ALTER TABLE wp_pp_clues 
ADD COLUMN IF NOT EXISTS input_type ENUM('text', 'number', 'photo', 'multiple_choice', 'none') DEFAULT 'none' AFTER hint_text,
ADD COLUMN IF NOT EXISTS required_answer VARCHAR(500) NULL AFTER input_type,
ADD COLUMN IF NOT EXISTS answer_options JSON NULL AFTER required_answer,
ADD COLUMN IF NOT EXISTS is_case_sensitive BOOLEAN DEFAULT FALSE AFTER answer_options,
ADD COLUMN IF NOT EXISTS min_value DECIMAL(10,2) NULL AFTER is_case_sensitive,
ADD COLUMN IF NOT EXISTS max_value DECIMAL(10,2) NULL AFTER min_value,
ADD COLUMN IF NOT EXISTS photo_required BOOLEAN DEFAULT FALSE AFTER max_value,
ADD COLUMN IF NOT EXISTS auto_advance BOOLEAN DEFAULT FALSE AFTER photo_required;

-- Alternative syntax for MySQL versions that don't support IF NOT EXISTS
-- Use this version if the above fails:
-- ALTER TABLE wp_pp_clues 
-- ADD COLUMN input_type ENUM('text', 'number', 'photo', 'multiple_choice', 'none') DEFAULT 'none' AFTER hint_text,
-- ADD COLUMN required_answer VARCHAR(500) NULL AFTER input_type,
-- ADD COLUMN answer_options JSON NULL AFTER required_answer,
-- ADD COLUMN is_case_sensitive BOOLEAN DEFAULT FALSE AFTER answer_options,
-- ADD COLUMN min_value DECIMAL(10,2) NULL AFTER is_case_sensitive,
-- ADD COLUMN max_value DECIMAL(10,2) NULL AFTER min_value,
-- ADD COLUMN photo_required BOOLEAN DEFAULT FALSE AFTER max_value,
-- ADD COLUMN auto_advance BOOLEAN DEFAULT FALSE AFTER photo_required;

-- Set default values for existing clues
UPDATE wp_pp_clues 
SET input_type = 'text', 
    required_answer = answer 
WHERE input_type IS NULL 
    AND answer IS NOT NULL 
    AND answer != '';

-- Set input_type to 'none' for clues without answers
UPDATE wp_pp_clues 
SET input_type = 'none' 
WHERE input_type IS NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_clues_input_type ON wp_pp_clues (input_type);
CREATE INDEX IF NOT EXISTS idx_clues_hunt_order ON wp_pp_clues (hunt_id, clue_order);

-- Verify the table structure
-- You can uncomment and run this to check if columns were added successfully:
-- DESCRIBE wp_pp_clues;