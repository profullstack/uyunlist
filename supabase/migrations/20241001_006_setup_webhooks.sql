-- Supabase Webhooks Schema
-- This file sets up webhook functionality for Supabase

-- Create the webhooks schema
CREATE SCHEMA IF NOT EXISTS webhooks;

-- Grant usage on the webhooks schema
GRANT usage ON SCHEMA webhooks TO postgres, anon, authenticated, service_role;

-- Create webhook tables
CREATE TABLE IF NOT EXISTS webhooks.hooks (
    id bigserial PRIMARY KEY,
    hook_table_id integer NOT NULL,
    hook_name text NOT NULL,
    created_at timestamp with time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    request_id bigint
);

CREATE TABLE IF NOT EXISTS webhooks.hook_table (
    id integer NOT NULL PRIMARY KEY,
    schema_name text NOT NULL,
    table_name text NOT NULL,
    created_at timestamp with time zone DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- Create webhook functions
CREATE OR REPLACE FUNCTION webhooks.get_url(hook_name text)
RETURNS text
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(
        current_setting('app.webhook.' || hook_name, true),
        current_setting('app.webhook.url', true)
    )
$$;

-- Function to send webhook
CREATE OR REPLACE FUNCTION webhooks.send_webhook()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    webhook_url text;
    payload jsonb;
    request_id bigint;
BEGIN
    -- Get webhook URL
    webhook_url := webhooks.get_url(TG_ARGV[0]);
    
    IF webhook_url IS NULL OR webhook_url = '' THEN
        RETURN COALESCE(NEW, OLD);
    END IF;

    -- Build payload
    payload := jsonb_build_object(
        'type', TG_OP,
        'table', TG_TABLE_NAME,
        'schema', TG_TABLE_SCHEMA,
        'record', row_to_json(NEW),
        'old_record', row_to_json(OLD)
    );

    -- Insert webhook request (this would typically be handled by a background worker)
    INSERT INTO webhooks.hooks (hook_table_id, hook_name, request_id)
    VALUES (
        (SELECT id FROM webhooks.hook_table WHERE schema_name = TG_TABLE_SCHEMA AND table_name = TG_TABLE_NAME),
        TG_ARGV[0],
        NULL
    );

    RETURN COALESCE(NEW, OLD);
END;
$$;

-- Create helper functions
CREATE OR REPLACE FUNCTION webhooks.create_hook_table(schema_name text, table_name text)
RETURNS integer
LANGUAGE sql
AS $$
    INSERT INTO webhooks.hook_table (schema_name, table_name)
    VALUES (schema_name, table_name)
    ON CONFLICT (schema_name, table_name) DO UPDATE SET
        created_at = timezone('utc'::text, now())
    RETURNING id;
$$;

-- Grant permissions
GRANT ALL ON SCHEMA webhooks TO postgres;
GRANT ALL ON ALL TABLES IN SCHEMA webhooks TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL SEQUENCES IN SCHEMA webhooks TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA webhooks TO postgres, anon, authenticated, service_role;

-- Create unique constraint
ALTER TABLE webhooks.hook_table ADD CONSTRAINT IF NOT EXISTS hook_table_schema_name_table_name_key UNIQUE (schema_name, table_name);

-- Example webhook setup for our tables (commented out - enable as needed)
-- SELECT webhooks.create_hook_table('public', 'listings');
-- SELECT webhooks.create_hook_table('public', 'messages');
-- SELECT webhooks.create_hook_table('public', 'invoices');

-- Create triggers for webhook notifications (commented out - enable as needed)
-- CREATE TRIGGER webhook_listings
--     AFTER INSERT OR UPDATE OR DELETE ON public.listings
--     FOR EACH ROW EXECUTE FUNCTION webhooks.send_webhook('listing_changes');

-- CREATE TRIGGER webhook_messages
--     AFTER INSERT ON public.messages
--     FOR EACH ROW EXECUTE FUNCTION webhooks.send_webhook('new_message');

-- CREATE TRIGGER webhook_invoices
--     AFTER UPDATE ON public.invoices
--     FOR EACH ROW EXECUTE FUNCTION webhooks.send_webhook('invoice_status');