-- Adds Firebase Authentication identity mapping without removing legacy sessions.
-- Run once on existing installations before enabling POST /auth/firebase-session.

SET @add_firebase_uid = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'firebase_uid') = 0,
    'ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL AFTER id',
    'SELECT 1'
);
PREPARE add_firebase_uid_stmt FROM @add_firebase_uid;
EXECUTE add_firebase_uid_stmt;
DEALLOCATE PREPARE add_firebase_uid_stmt;

SET @add_firebase_provider = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'firebase_provider') = 0,
    'ALTER TABLE users ADD COLUMN firebase_provider VARCHAR(80) NULL AFTER firebase_uid',
    'SELECT 1'
);
PREPARE add_firebase_provider_stmt FROM @add_firebase_provider;
EXECUTE add_firebase_provider_stmt;
DEALLOCATE PREPARE add_firebase_provider_stmt;

SET @add_firebase_uid_index = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uq_users_firebase_uid') = 0,
    'ALTER TABLE users ADD UNIQUE INDEX uq_users_firebase_uid (firebase_uid)',
    'SELECT 1'
);
PREPARE add_firebase_uid_index_stmt FROM @add_firebase_uid_index;
EXECUTE add_firebase_uid_index_stmt;
DEALLOCATE PREPARE add_firebase_uid_index_stmt;
