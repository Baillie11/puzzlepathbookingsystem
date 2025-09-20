-- SQL script to drop pp_hunts table to avoid confusion
-- Run this in your WordPress database to clean up

-- Note: Replace wp2s_ with your actual WordPress table prefix

DROP TABLE IF EXISTS wp2s_pp_hunts;

-- Verify pp_clues table links to pp_events properly
-- The hunt_id column in pp_clues should reference pp_events.id

-- Optional: Add foreign key constraint to ensure data integrity
-- ALTER TABLE wp2s_pp_clues 
-- ADD CONSTRAINT fk_clues_event 
-- FOREIGN KEY (hunt_id) REFERENCES wp2s_pp_events(id) 
-- ON DELETE CASCADE;
