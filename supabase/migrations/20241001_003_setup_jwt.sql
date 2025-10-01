-- JWT helper functions for Supabase

CREATE SCHEMA IF NOT EXISTS extensions;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA extensions;
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions;

-- Function to generate JWT tokens
CREATE OR REPLACE FUNCTION extensions.jwt_generate(
  payload json,
  secret text,
  algorithm text DEFAULT 'HS256'
)
RETURNS text
LANGUAGE plpgsql
AS $$
DECLARE
  header json;
  segments text[];
  signature text;
BEGIN
  header := json_build_object('typ', 'JWT', 'alg', algorithm);
  
  segments := ARRAY[
    replace(replace(replace(encode(header::text::bytea, 'base64'), E'\n', ''), '+', '-'), '/', '_'),
    replace(replace(replace(encode(payload::text::bytea, 'base64'), E'\n', ''), '+', '-'), '/', '_')
  ];
  
  signature := replace(replace(replace(encode(
    extensions.hmac(array_to_string(segments, '.'), secret, 'sha256'), 'base64'
  ), E'\n', ''), '+', '-'), '/', '_');
  
  RETURN array_to_string(segments, '.') || '.' || signature;
END;
$$;

-- Function to verify JWT tokens
CREATE OR REPLACE FUNCTION extensions.jwt_verify(
  token text,
  secret text,
  algorithm text DEFAULT 'HS256'
)
RETURNS json
LANGUAGE plpgsql
AS $$
DECLARE
  segments text[];
  header json;
  payload json;
  signature text;
  expected_signature text;
BEGIN
  segments := string_to_array(token, '.');
  
  IF array_length(segments, 1) != 3 THEN
    RAISE EXCEPTION 'Invalid JWT format';
  END IF;
  
  header := convert_from(decode(replace(replace(segments[1], '-', '+'), '_', '/'), 'base64'), 'utf8')::json;
  payload := convert_from(decode(replace(replace(segments[2], '-', '+'), '_', '/'), 'base64'), 'utf8')::json;
  signature := segments[3];
  
  expected_signature := replace(replace(replace(encode(
    extensions.hmac(segments[1] || '.' || segments[2], secret, 'sha256'), 'base64'
  ), E'\n', ''), '+', '-'), '/', '_');
  
  IF signature != expected_signature THEN
    RAISE EXCEPTION 'Invalid JWT signature';
  END IF;
  
  RETURN payload;
END;
$$;

-- Grant execute permissions
GRANT EXECUTE ON FUNCTION extensions.jwt_generate(json, text, text) TO service_role;
GRANT EXECUTE ON FUNCTION extensions.jwt_verify(text, text, text) TO service_role;