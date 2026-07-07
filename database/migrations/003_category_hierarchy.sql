-- 003: Two-level category hierarchy (parent_id + slug) and subcategory seed.
--
-- Adds a self-referential parent_id and a URL slug to categories so listings can
-- be browsed at /<category>/<subcategory> (e.g. /jobs/dealer). Idempotent — safe
-- to run more than once and safe to apply by hand to an already-provisioned DB
-- (scripts/apply-migrations.sh skips migrations once the schema exists).

ALTER TABLE categories ADD COLUMN IF NOT EXISTS parent_id BIGINT REFERENCES categories(id) ON DELETE CASCADE;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS slug TEXT;

-- Backfill slugs for any category missing one (the existing top-level rows).
UPDATE categories
   SET slug = trim(both '-' from lower(regexp_replace(name, '[^a-zA-Z0-9]+', '-', 'g')))
 WHERE slug IS NULL OR slug = '';

-- A slug is unique within its parent (top-level parents are NULL -> 0).
CREATE UNIQUE INDEX IF NOT EXISTS idx_categories_parent_slug
    ON categories (COALESCE(parent_id, 0), slug);
CREATE INDEX IF NOT EXISTS idx_categories_parent_id ON categories (parent_id);

-- Seed subcategories under each existing top-level category. The parent is
-- resolved by its (top-level) slug; the NOT EXISTS guard keeps re-runs clean.
INSERT INTO categories (name, slug, parent_id, sort_order, is_active)
SELECT s.name, s.slug, p.id, s.sort_order, true
FROM (VALUES
    ('electronics', 'Computers',            'computers',          1),
    ('electronics', 'Phones & Tablets',     'phones-tablets',     2),
    ('electronics', 'Audio & Video',        'audio-video',        3),
    ('electronics', 'Gaming',               'gaming',             4),
    ('electronics', 'Cameras',              'cameras',            5),
    ('vehicles',    'Cars & Trucks',        'cars-trucks',        1),
    ('vehicles',    'Motorcycles',          'motorcycles',        2),
    ('vehicles',    'Boats',                'boats',              3),
    ('vehicles',    'Parts & Accessories',  'parts',              4),
    ('vehicles',    'Bicycles',             'bicycles',           5),
    ('real-estate', 'For Rent',             'for-rent',           1),
    ('real-estate', 'Homes for Sale',       'homes-for-sale',     2),
    ('real-estate', 'Rooms & Shared',       'rooms-shared',       3),
    ('real-estate', 'Commercial',           'commercial',         4),
    ('real-estate', 'Land',                 'land',               5),
    ('jobs',        'Full-time',            'full-time',          1),
    ('jobs',        'Part-time',            'part-time',          2),
    ('jobs',        'Gigs',                 'gigs',               3),
    ('jobs',        'Dealer',               'dealer',             4),
    ('jobs',        'Remote',               'remote',             5),
    ('services',    'Skilled Trades',       'skilled-trades',     1),
    ('services',    'Computer & IT',        'computer-it',        2),
    ('services',    'Creative',             'creative',           3),
    ('services',    'Lessons & Tutoring',   'lessons',            4),
    ('services',    'Financial & Legal',    'financial-legal',    5),
    ('for-sale',    'Furniture',            'furniture',          1),
    ('for-sale',    'Clothing',             'clothing',           2),
    ('for-sale',    'Appliances',           'appliances',         3),
    ('for-sale',    'Tools',                'tools',              4),
    ('for-sale',    'Free Stuff',           'free',               5),
    ('community',   'Events',               'events',             1),
    ('community',   'Groups',               'groups',             2),
    ('community',   'Volunteers',           'volunteers',         3),
    ('community',   'Lost & Found',         'lost-found',         4),
    ('community',   'Classes',              'classes',            5),
    ('personals',   'Dating',               'dating',             1),
    ('personals',   'Missed Connections',   'missed-connections', 2),
    ('personals',   'Friends & Activities', 'friends-activities', 3)
) AS s(parent_slug, name, slug, sort_order)
JOIN categories p ON p.slug = s.parent_slug AND p.parent_id IS NULL
WHERE NOT EXISTS (
    SELECT 1 FROM categories c2 WHERE c2.parent_id = p.id AND c2.slug = s.slug
);

-- Every category now has a slug.
ALTER TABLE categories ALTER COLUMN slug SET NOT NULL;
