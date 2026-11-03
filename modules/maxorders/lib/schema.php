<?php
// MAX-owned schema via supported module _init SQL array. GPL-3.0-or-later.
return [
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_packet (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL, fk_soc integer NOT NULL, match_ref varchar(80) NOT NULL, match_key char(64) NOT NULL,
 status varchar(16) NOT NULL DEFAULT 'provisional', fk_order integer NULL,
 fk_user_author integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxorders_packet (entity,fk_soc,match_key)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_revision (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer NOT NULL, fk_packet integer NOT NULL, kind varchar(10) NOT NULL,
 document_ref varchar(80) NOT NULL, doc_key char(64) NOT NULL, revision_no integer NOT NULL,
 content_hash char(64) NOT NULL, status varchar(20) NOT NULL, fk_user_author integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxorders_revision (entity,fk_packet,kind,doc_key,revision_no)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_fact (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_revision integer NOT NULL,
 field_name varchar(32) NOT NULL, field_value varchar(255) NOT NULL,
 UNIQUE KEY uk_maxorders_fact (entity,fk_revision,field_name)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_current (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_packet integer NOT NULL,
 kind varchar(10) NOT NULL, doc_key char(64) NOT NULL, field_name varchar(32) NOT NULL,
 field_value varchar(255) NOT NULL, revision_no integer NOT NULL, fk_revision integer NOT NULL,
 UNIQUE KEY uk_maxorders_current (entity,fk_packet,kind,doc_key,field_name)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_lock (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_packet integer NOT NULL,
 field_name varchar(32) NOT NULL, field_value varchar(255) NOT NULL, is_locked integer NOT NULL,
 fk_user_author integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxorders_lock (entity,fk_packet,field_name)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_conflict (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_packet integer NOT NULL, fk_revision integer NOT NULL,
 field_name varchar(32) NOT NULL, old_value varchar(255) NOT NULL, new_value varchar(255) NOT NULL,
 reason varchar(32) NOT NULL, status varchar(16) NOT NULL, decision varchar(32) NULL,
 fk_user_resolver integer NULL, date_resolved datetime NULL,
 UNIQUE KEY uk_maxorders_conflict (entity,fk_packet,fk_revision,field_name,reason)
) ENGINE=innodb
SQL
),
str_replace('llx_', MAIN_DB_PREFIX, <<<'SQL'
CREATE TABLE IF NOT EXISTS llx_maxorders_audit (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_packet integer NOT NULL,
 fk_revision integer NULL, event varchar(32) NOT NULL, field_name varchar(32) NULL,
 fk_user_author integer NOT NULL, datec datetime NOT NULL
) ENGINE=innodb
SQL
)
];
