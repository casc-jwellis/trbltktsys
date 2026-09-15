-- Removes per-user category permissions -- categories are now assignable
-- to groups only. Users still get category coverage through whichever
-- groups they belong to.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

DROP TABLE user_categories;
