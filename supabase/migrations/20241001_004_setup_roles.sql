-- Set up Supabase roles and permissions

-- Create roles
CREATE ROLE anon                nologin noinherit;
CREATE ROLE authenticated       nologin noinherit; -- "logged in" user: web_user, app_user, etc
CREATE ROLE service_role        nologin noinherit bypassrls; -- allow developers to create JWT's that bypass their policies

-- Grant usage on schema
GRANT usage                     ON SCHEMA public TO anon, authenticated, service_role;

-- Grant permissions on tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT INSERT ON TABLES TO anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT UPDATE ON TABLES TO anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT DELETE ON TABLES TO anon, authenticated, service_role;

-- Grant permissions on sequences
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO anon, authenticated, service_role;

-- Grant permissions on functions
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT EXECUTE ON FUNCTIONS TO anon, authenticated, service_role;

-- Create authenticator role
CREATE ROLE authenticator noinherit;
GRANT anon, authenticated, service_role TO authenticator;

-- Create supabase_admin role
CREATE ROLE supabase_admin;
GRANT ALL PRIVILEGES ON DATABASE postgres TO supabase_admin;
GRANT ALL PRIVILEGES ON SCHEMA public TO supabase_admin;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO supabase_admin;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO supabase_admin;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO supabase_admin;

-- Create supabase_auth_admin role
CREATE ROLE supabase_auth_admin noinherit createrole createdb;
GRANT ALL PRIVILEGES ON DATABASE postgres TO supabase_auth_admin;
GRANT ALL PRIVILEGES ON SCHEMA public TO supabase_auth_admin;

-- Create supabase_storage_admin role
CREATE ROLE supabase_storage_admin noinherit createrole;
GRANT ALL PRIVILEGES ON DATABASE postgres TO supabase_storage_admin;
GRANT ALL PRIVILEGES ON SCHEMA public TO supabase_storage_admin;