<?php
// Namespaced intake, never a commercial/agenda ledger. GPL-3.0-or-later.
$tables=[
'CREATE TABLE IF NOT EXISTS __PREFIX__maxmail_account (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, label varchar(80) NOT NULL,
 identity_hash char(64) NOT NULL, host varchar(253) NOT NULL, port integer NOT NULL,
 transport varchar(16) NOT NULL, username varchar(128) NOT NULL, secret text NOT NULL, folder varchar(128) NOT NULL,
 active integer NOT NULL DEFAULT 1, uidvalidity bigint NOT NULL DEFAULT 0,
 state varchar(24) NOT NULL DEFAULT \'pending\', error_code varchar(48) NOT NULL DEFAULT \'\',
 connection_failures integer NOT NULL DEFAULT 0, retry_at bigint NOT NULL DEFAULT 0,
 fk_user integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxmail_account(entity,identity_hash)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxmail_grant (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_account integer NOT NULL, fk_user integer NOT NULL,
 can_view integer NOT NULL DEFAULT 0, can_poll integer NOT NULL DEFAULT 0,
 can_review integer NOT NULL DEFAULT 0, can_download integer NOT NULL DEFAULT 0,
 UNIQUE KEY uk_maxmail_grant(entity,fk_account,fk_user)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxmail_entry (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_account integer NOT NULL,
 uidvalidity bigint NOT NULL, imap_uid bigint NOT NULL, state varchar(24) NOT NULL,
 attempts integer NOT NULL DEFAULT 0, retry_at bigint NOT NULL DEFAULT 0, error_code varchar(48) NOT NULL DEFAULT \'\',
 message_id varchar(255) NULL, content_hash char(64) NULL, subject varchar(255) NULL, sender varchar(255) NULL,
 remote_deleted integer NOT NULL DEFAULT 0, reviewed_by integer NULL, reviewed_at datetime NULL,
 fk_user integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxmail_identity(entity,fk_account,uidvalidity,imap_uid)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxmail_file (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_account integer NOT NULL, fk_entry integer NOT NULL,
 part_no integer NOT NULL, display_name varchar(120) NOT NULL, detected_mime varchar(100) NOT NULL,
 byte_size integer NOT NULL, content_hash char(64) NOT NULL, storage_name varchar(100) NULL,
 state varchar(24) NOT NULL, reason varchar(48) NOT NULL,
 UNIQUE KEY uk_maxmail_part(entity,fk_entry,part_no)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxmail_event (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_account integer NOT NULL,
 fk_entry integer NOT NULL DEFAULT 0, event varchar(32) NOT NULL, code varchar(48) NOT NULL,
 fk_user integer NOT NULL, datec datetime NOT NULL
) ENGINE=innodb'
];
return array_map(fn($sql)=>str_replace('__PREFIX__',MAIN_DB_PREFIX,$sql),$tables);
