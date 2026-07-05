-- Add threaded comments to listings
-- Migration: 002_add_comments.sql

-- Comments table for listing discussions
CREATE TABLE listing_comments (
    id BIGSERIAL PRIMARY KEY,
    listing_id BIGINT NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id BIGINT REFERENCES listing_comments(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Prevent deeply nested comments: a reply's parent must be top-level (max 2
-- levels). Postgres forbids subqueries in CHECK constraints, so enforce via a
-- trigger.
CREATE OR REPLACE FUNCTION enforce_comment_nesting()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.parent_id IS NOT NULL AND EXISTS (
        SELECT 1 FROM listing_comments p
        WHERE p.id = NEW.parent_id AND p.parent_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'Comments can only be nested one level deep';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER listing_comment_nesting
    BEFORE INSERT OR UPDATE ON listing_comments
    FOR EACH ROW EXECUTE FUNCTION enforce_comment_nesting();

-- Indexes for performance
CREATE INDEX idx_listing_comments_listing_id ON listing_comments(listing_id);
CREATE INDEX idx_listing_comments_user_id ON listing_comments(user_id);
CREATE INDEX idx_listing_comments_parent_id ON listing_comments(parent_id);
CREATE INDEX idx_listing_comments_created_at ON listing_comments(created_at);

-- Trigger for updated_at
CREATE TRIGGER update_listing_comments_updated_at BEFORE UPDATE ON listing_comments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Add comment count to listings table for performance
ALTER TABLE listings ADD COLUMN comment_count INTEGER DEFAULT 0;

-- Function to update comment count
CREATE OR REPLACE FUNCTION update_listing_comment_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE listings SET comment_count = comment_count + 1 WHERE id = NEW.listing_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE listings SET comment_count = comment_count - 1 WHERE id = OLD.listing_id;
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        -- Handle soft delete toggle
        IF OLD.is_deleted = FALSE AND NEW.is_deleted = TRUE THEN
            UPDATE listings SET comment_count = comment_count - 1 WHERE id = NEW.listing_id;
        ELSIF OLD.is_deleted = TRUE AND NEW.is_deleted = FALSE THEN
            UPDATE listings SET comment_count = comment_count + 1 WHERE id = NEW.listing_id;
        END IF;
        RETURN NEW;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Triggers for comment count
CREATE TRIGGER listing_comment_count_insert 
    AFTER INSERT ON listing_comments 
    FOR EACH ROW EXECUTE FUNCTION update_listing_comment_count();

CREATE TRIGGER listing_comment_count_delete 
    AFTER DELETE ON listing_comments 
    FOR EACH ROW EXECUTE FUNCTION update_listing_comment_count();

CREATE TRIGGER listing_comment_count_update 
    AFTER UPDATE ON listing_comments 
    FOR EACH ROW EXECUTE FUNCTION update_listing_comment_count();

-- Initialize comment counts for existing listings
UPDATE listings SET comment_count = (
    SELECT COUNT(*) 
    FROM listing_comments 
    WHERE listing_id = listings.id AND is_deleted = FALSE
);