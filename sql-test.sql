-- Quick SQL commands to test and fix the adventures page issue

-- First, check if the table and column exist
SHOW TABLES LIKE 'wp2s_pp_events';
SHOW COLUMNS FROM wp2s_pp_events LIKE 'display_on_adventures_page';

-- Check current status of all quests
SELECT id, title, seats, display_on_adventures_page, display_on_site 
FROM wp2s_pp_events 
ORDER BY id;

-- Enable all quests with seats for the adventures page
UPDATE wp2s_pp_events 
SET display_on_adventures_page = 1 
WHERE seats > 0;

-- Check the results
SELECT id, title, seats, display_on_adventures_page, display_on_site 
FROM wp2s_pp_events 
WHERE display_on_adventures_page = 1 AND seats > 0
ORDER BY id;

-- If you want to enable specific quests by ID (replace X with the actual quest ID)
-- UPDATE wp2s_pp_events SET display_on_adventures_page = 1 WHERE id = X;