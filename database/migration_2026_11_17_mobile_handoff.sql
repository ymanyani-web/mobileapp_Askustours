-- Apply once to existing Release 1 databases after taking a backup.
-- Improves OTP rate-limit lookups. No data is removed or transformed.
ALTER TABLE auth_challenges DROP INDEX destination, ADD INDEX idx_auth_challenges_destination_created(destination,created_at);
