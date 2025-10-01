-- Initial database schema for Onion Classifieds
-- Run this in Supabase SQL Editor

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Users table
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    handle TEXT UNIQUE NOT NULL,
    pass_hash TEXT NOT NULL,
    about TEXT DEFAULT '',
    avatar_path TEXT DEFAULT '',
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Sessions table
CREATE TABLE sessions (
    id TEXT PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    csrf_token TEXT NOT NULL DEFAULT gen_random_uuid()::TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    last_seen_at TIMESTAMPTZ DEFAULT NOW()
);

-- Categories table
CREATE TABLE categories (
    id BIGSERIAL PRIMARY KEY,
    name TEXT UNIQUE NOT NULL,
    description TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Listings table
CREATE TABLE listings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES categories(id),
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    price_sats BIGINT DEFAULT 0,
    location TEXT DEFAULT '',
    is_published BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    view_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    published_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ
);

-- Listing images table
CREATE TABLE listing_images (
    id BIGSERIAL PRIMARY KEY,
    listing_id BIGINT NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    path TEXT NOT NULL,
    filename TEXT NOT NULL,
    width INTEGER DEFAULT 0,
    height INTEGER DEFAULT 0,
    file_size INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Conversations table (for 1-to-1 messaging)
CREATE TABLE conversations (
    id BIGSERIAL PRIMARY KEY,
    user_a BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user_b BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT unique_conversation UNIQUE(user_a, user_b),
    CONSTRAINT different_users CHECK (user_a != user_b)
);

-- Messages table
CREATE TABLE messages (
    id BIGSERIAL PRIMARY KEY,
    convo_id BIGINT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    is_read_by_a BOOLEAN DEFAULT FALSE,
    is_read_by_b BOOLEAN DEFAULT FALSE
);

-- Invoices table (for payments)
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    purpose TEXT NOT NULL, -- e.g., 'post_listing:123', 'bump_listing:456'
    status TEXT NOT NULL DEFAULT 'new', -- new|processing|settled|expired|failed
    fiat_currency TEXT NOT NULL DEFAULT 'USD',
    fiat_amount NUMERIC(10,2) NOT NULL,
    currency TEXT NOT NULL, -- BTC, XMR, ETH, SOL, DOGE
    crypto_rate NUMERIC(20,8) NOT NULL, -- snapshot rate at creation
    crypto_amount NUMERIC(20,8) NOT NULL,
    address_in TEXT NOT NULL,
    confirmations_required INTEGER NOT NULL DEFAULT 1,
    confirmations_received INTEGER DEFAULT 0,
    is_pending_notified BOOLEAN DEFAULT FALSE,
    webhook_raw JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    settled_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ DEFAULT NOW() + INTERVAL '24 hours'
);

-- Reports table (for content moderation)
CREATE TABLE reports (
    id BIGSERIAL PRIMARY KEY,
    reporter_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    reported_user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    listing_id BIGINT REFERENCES listings(id) ON DELETE CASCADE,
    message_id BIGINT REFERENCES messages(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    description TEXT DEFAULT '',
    status TEXT DEFAULT 'pending', -- pending|reviewed|resolved|dismissed
    reviewed_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT report_target_check CHECK (
        (reported_user_id IS NOT NULL) OR 
        (listing_id IS NOT NULL) OR 
        (message_id IS NOT NULL)
    )
);

-- Create indexes for performance
CREATE INDEX idx_users_handle ON users(handle);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_last_seen ON sessions(last_seen_at);

CREATE INDEX idx_listings_user_id ON listings(user_id);
CREATE INDEX idx_listings_category_id ON listings(category_id);
CREATE INDEX idx_listings_published ON listings(is_published, created_at DESC);
CREATE INDEX idx_listings_location ON listings(location) WHERE location != '';
CREATE INDEX idx_listings_search ON listings USING gin(to_tsvector('english', title || ' ' || body));

CREATE INDEX idx_listing_images_listing_id ON listing_images(listing_id);

CREATE INDEX idx_conversations_users ON conversations(user_a, user_b);
CREATE INDEX idx_messages_convo_id ON messages(convo_id, created_at);
CREATE INDEX idx_messages_sender_id ON messages(sender_id);

CREATE INDEX idx_invoices_user_id ON invoices(user_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_purpose ON invoices(purpose);
CREATE INDEX idx_invoices_expires_at ON invoices(expires_at);

CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_reports_created_at ON reports(created_at DESC);

-- Insert default categories
INSERT INTO categories (name, description, sort_order) VALUES
('Electronics', 'Computers, phones, gadgets', 1),
('Vehicles', 'Cars, motorcycles, boats', 2),
('Real Estate', 'Houses, apartments, land', 3),
('Jobs', 'Employment opportunities', 4),
('Services', 'Professional and personal services', 5),
('For Sale', 'General items for sale', 6),
('Community', 'Events, activities, groups', 7),
('Personals', 'Dating, relationships, missed connections', 8);

-- Create function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_listings_updated_at BEFORE UPDATE ON listings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_conversations_updated_at BEFORE UPDATE ON conversations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();