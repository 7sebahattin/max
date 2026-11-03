<?php
// MAX-owned metadata only. GPL-3.0-or-later. No standalone SQL dump or native ERP writes.
$definitions=[
'CREATE TABLE IF NOT EXISTS __PREFIX__maxcustoms_declaration (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, ref varchar(80) NOT NULL,
 ref_key char(64) NOT NULL, public_data text NOT NULL, status varchar(16) NOT NULL,
 version integer NOT NULL, fk_soc integer NULL, fk_order integer NULL, order_reference varchar(80) NULL,
 fk_user_author integer NOT NULL, datec datetime NOT NULL, fk_user_editor integer NOT NULL, datem datetime NOT NULL,
 UNIQUE KEY uk_maxcustoms_ref(entity,ref_key)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxcustoms_private (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_declaration integer NOT NULL,
 contact_data text NULL, analysis_data text NULL, source_data text NULL,
 UNIQUE KEY uk_maxcustoms_private(entity,fk_declaration)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxcustoms_shipment (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_declaration integer NOT NULL, fk_shipment integer NOT NULL,
 UNIQUE KEY uk_maxcustoms_shipment(entity,fk_declaration,fk_shipment)
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxcustoms_history (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_declaration integer NOT NULL,
 event varchar(40) NOT NULL, category varchar(16) NOT NULL, field_name varchar(40) NOT NULL,
 previous_status varchar(16) NULL, next_status varchar(16) NULL, fk_user integer NOT NULL, datec datetime NOT NULL
) ENGINE=innodb',
'CREATE TABLE IF NOT EXISTS __PREFIX__maxcustoms_evidence (
 rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer NOT NULL, fk_declaration integer NOT NULL,
 content_hash char(64) NOT NULL, storage_name varchar(100) NOT NULL, display_name varchar(80) NOT NULL,
 mime varchar(80) NOT NULL, byte_size integer NOT NULL, status varchar(24) NOT NULL,
 fk_user integer NOT NULL, datec datetime NOT NULL,
 UNIQUE KEY uk_maxcustoms_evidence(entity,fk_declaration,content_hash)
) ENGINE=innodb'
];
return array_map(fn($sql)=>str_replace('__PREFIX__',MAIN_DB_PREFIX,$sql),$definitions);
