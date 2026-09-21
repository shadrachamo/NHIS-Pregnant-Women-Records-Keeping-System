-- ============================================================
-- Migration: Split full_name into first/middle/last name
--            Add ghana_card_number column
--            Widen nhis_membership_number to 8-digit only
-- Run this in phpMyAdmin > nhis_pregnancy > SQL tab
-- ============================================================

USE `nhis_pregnancy`;

-- Add name columns (after full_name for clean ordering)
ALTER TABLE `pregnant_women`
  ADD COLUMN `first_name`   VARCHAR(80)  DEFAULT NULL AFTER `nhis_membership_number`,
  ADD COLUMN `middle_name`  VARCHAR(80)  DEFAULT NULL AFTER `first_name`,
  ADD COLUMN `last_name`    VARCHAR(80)  DEFAULT NULL AFTER `middle_name`,
  ADD COLUMN `ghana_card_number` VARCHAR(20) DEFAULT NULL
      COMMENT 'Ghana Card Number: GHA-XXXXXXXXX-X'
      AFTER `nhis_membership_number`;

-- Populate first_name and last_name from existing full_name data
UPDATE `pregnant_women`
  SET `first_name` = TRIM(SUBSTRING_INDEX(`full_name`, ' ', 1)),
      `last_name`  = TRIM(SUBSTRING_INDEX(`full_name`, ' ', -1))
  WHERE `full_name` IS NOT NULL;

-- Add index on ghana_card_number
ALTER TABLE `pregnant_women`
  ADD KEY `idx_ghana_card` (`ghana_card_number`);

-- Add unique constraint on ghana_card_number (nullable unique)
-- Note: MySQL allows multiple NULLs in a unique index, which is correct behaviour here
ALTER TABLE `pregnant_women`
  ADD UNIQUE KEY `uq_ghana_card` (`ghana_card_number`);
