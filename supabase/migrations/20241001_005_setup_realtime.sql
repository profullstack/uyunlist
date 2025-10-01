-- Supabase Realtime Schema
-- This file sets up the realtime functionality for Supabase

-- Create the realtime schema
CREATE SCHEMA IF NOT EXISTS _realtime;

-- Grant usage on the realtime schema
GRANT usage ON SCHEMA _realtime TO postgres, anon, authenticated, service_role;

-- Create the realtime publication
CREATE PUBLICATION supabase_realtime FOR ALL TABLES;

-- Create realtime tables
CREATE TABLE IF NOT EXISTS _realtime.subscription (
    id bigserial PRIMARY KEY,
    subscription_id uuid NOT NULL,
    entity regclass NOT NULL,
    filters realtime.user_defined_filter[] NOT NULL DEFAULT '{}',
    claims jsonb NOT NULL,
    claims_role regrole NOT NULL GENERATED ALWAYS AS (realtime.to_regrole((claims ->> 'role'))) STORED,
    created_at timestamp DEFAULT timezone('utc', now()) NOT NULL
);

-- Create indexes
CREATE INDEX IF NOT EXISTS subscription_subscription_id_entity_filters_idx ON _realtime.subscription USING btree (subscription_id, entity, filters);
CREATE INDEX IF NOT EXISTS subscription_entity_idx ON _realtime.subscription USING btree (entity);
CREATE INDEX IF NOT EXISTS subscription_claims_role_idx ON _realtime.subscription USING btree (claims_role);

-- Create realtime functions
CREATE OR REPLACE FUNCTION _realtime.apply_rls(wal jsonb, max_record_bytes int DEFAULT (1024 * 1024))
RETURNS SETOF realtime.wal_rls
LANGUAGE plpgsql
AS $$
DECLARE
    -- Regclass of the table e.g. public.notes
    entity_ regclass = (quote_ident(wal ->> 'schema') || '.' || quote_ident(wal ->> 'table'))::regclass;

    -- I, U, D, T: insert, update, delete, truncate
    action realtime.action = (
        CASE wal ->> 'action'
            WHEN 'I' THEN 'INSERT'
            WHEN 'U' THEN 'UPDATE'
            WHEN 'D' THEN 'DELETE'
            ELSE ERROR('Unrecognized action')
        END
    );

    -- Is row level security enabled for the table
    is_rls_enabled bool = relrowsecurity FROM pg_class WHERE oid = entity_;

    subscriptions realtime.subscription[] = array_agg(subs)
        FROM
            _realtime.subscription subs
        WHERE
            subs.entity = entity_;

    -- Subscription may not exist if none are active on the table
    subscription realtime.subscription;

    rec record;
BEGIN

    IF is_rls_enabled IS FALSE THEN
        RETURN;
    END IF;

    -- Update record
    FOR subscription IN SELECT * FROM unnest(subscriptions) LOOP
        rec := (
            SELECT
                t.subscription_id,
                t.claims,
                t.claims_role,
                t.filters,
                (CASE
                    WHEN action = 'INSERT' THEN wal -> 'columns'
                    WHEN action = 'UPDATE' THEN wal -> 'columns'
                    WHEN action = 'DELETE' THEN wal -> 'old_record'
                END) AS record
            FROM
                unnest(subscriptions) AS t
            WHERE
                t.subscription_id = subscription.subscription_id
        );

        IF rec.record IS NULL THEN
            CONTINUE;
        END IF;

        -- The claims role does not have SELECT permission to the table
        PERFORM
        FROM
            pg_attribute
        WHERE
            attrelid = entity_
            AND NOT attisdropped
            AND attsecurity IS NOT NULL;

        IF FOUND IS FALSE THEN
            RETURN QUERY
            SELECT
                rec.subscription_id,
                rec.claims,
                rec.claims_role,
                rec.filters,
                entity_,
                action,
                rec.record,
                NULL::jsonb AS old_record,
                NULL::jsonb AS old_record_diff
            WHERE
                realtime.is_visible_through_filters(rec.filters, action, rec.record);

        ELSE
            -- If RLS is enabled, we need to check if the user can see the record
            RETURN QUERY
            SELECT
                rec.subscription_id,
                rec.claims,
                rec.claims_role,
                rec.filters,
                entity_,
                action,
                rec.record,
                NULL::jsonb AS old_record,
                NULL::jsonb AS old_record_diff
            WHERE
                realtime.is_visible_through_filters(rec.filters, action, rec.record);
        END IF;

    END LOOP;

    RETURN;
END;
$$;

-- Grant permissions
GRANT ALL ON SCHEMA _realtime TO postgres;
GRANT ALL ON ALL TABLES IN SCHEMA _realtime TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL SEQUENCES IN SCHEMA _realtime TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA _realtime TO postgres, anon, authenticated, service_role;

-- Enable realtime for our tables (optional - can be enabled per table as needed)
-- ALTER PUBLICATION supabase_realtime ADD TABLE listings;
-- ALTER PUBLICATION supabase_realtime ADD TABLE listing_comments;
-- ALTER PUBLICATION supabase_realtime ADD TABLE messages;