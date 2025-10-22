-- Add per-customer usage limit to coupons table
-- This allows the same coupon to be used by multiple customers,
-- but each customer can only use it a limited number of times

ALTER TABLE wp2s_pp_coupons 
ADD COLUMN max_uses_per_customer INT DEFAULT 0 
COMMENT 'Maximum times a single customer can use this coupon (0 = unlimited per customer)';

-- Add index for faster coupon lookups
CREATE INDEX idx_coupon_code ON wp2s_pp_coupons(code);

-- Show the updated table structure
DESCRIBE wp2s_pp_coupons;
