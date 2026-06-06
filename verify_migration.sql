-- Verify migration results
SHOW TABLES LIKE 'stok_log';
DESCRIBE stok_log;
SHOW VIEWS LIKE 'v_stok%';
SELECT * FROM v_stok_realtime LIMIT 3;
