-- Seed the `test` database for Playwright runs.
-- Mirrors the data previously held in BackendEnvironment.csv (which uses the
-- TYPO3 testing-framework CSV format). Imported here as SQL because the
-- connector's CSV parser does not support multi-line quoted fields.
--
-- Runs once per worker via dbConfig.seedFiles; idempotent thanks to the
-- TRUNCATEs at the top.

SET
FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE be_users;
TRUNCATE TABLE be_groups;
TRUNCATE TABLE pages;

-- argon2id hash of the cleartext password "Password.1" (TYPO3 v13/v14 default
-- backend hashing algorithm). Same hash used for the admin and editor users.
INSERT INTO be_users (uid, pid, tstamp, username, password, admin, disable, starttime, endtime, options, crdate,
											workspace_perms, deleted, TSconfig, lastlogin, workspace_id, db_mountpoints, usergroup, realName)
VALUES (1, 0, 1366642540, 'admin',
				'$argon2id$v=19$m=65536,t=4,p=1$V3lYekJpeVp6TEdPbmU2NQ$akxm34MxFiRQ717lGkRA3rrxL2zYdA9cpVATldUIx7k', 1, 0, 0, 0,
				0, 1366642540, 1, 0, '', 1371033743, 0, '0', '0', 'Klaus Admin'),
			 (2, 0, 1452944912, 'editor',
				'$argon2id$v=19$m=65536,t=4,p=1$V3lYekJpeVp6TEdPbmU2NQ$akxm34MxFiRQ717lGkRA3rrxL2zYdA9cpVATldUIx7k', 0, 0, 0, 0,
				0, 1452944912, 1, 0, '', 1452944915, 0, '1', '1', '');

INSERT INTO be_groups (uid, pid, tstamp, title, tables_modify, crdate, subgroup)
VALUES (1, 0, 1452959228, 'editor-group', 'pages', 1452959228, ''),
			 (2, 0, 1452959228, 'some test group', 'pages', 1452959228, '1');

INSERT INTO pages (uid, pid, is_siteroot, doktype, sorting, title, sys_language_uid, slug, l10n_parent, l10n_source,
									 TSconfig)
VALUES (1, 0, 1, 1, 256, 'Reveal Demo Root', 0, '/', 0, 0,
				''),
			 (2, 1, 0, 1, 256, 'THE CÄMP', 0, '/subpage-germany', 0, 0, NULL);
SET
FOREIGN_KEY_CHECKS = 1;
