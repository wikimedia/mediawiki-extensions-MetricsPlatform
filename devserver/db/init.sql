CREATE DATABASE IF NOT EXISTS mpic;

GRANT ALL PRIVILEGES ON mpic.* TO 'maria'@'%';

USE mpic;

CREATE TABLE IF NOT EXISTS instruments (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	slug VARCHAR(255) NOT NULL,
	description TEXT,
	creator VARCHAR(255) NOT NULL,
	owner JSON DEFAULT NULL,
	purpose JSON DEFAULT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
	utc_start_dt DATETIME NOT NULL,
	utc_end_dt DATETIME NOT NULL,
	task VARCHAR(1000) NOT NULL,
	risk_level VARCHAR(255) NOT NULL,
	sample_unit VARCHAR(255) NOT NULL,
	sample_rate JSON NOT NULL,  -- JSON field for sample rates
	environments SET('development', 'staging', 'production', 'external') NOT NULL,
	security_legal_review VARCHAR(1000) NOT NULL,
	status BOOLEAN DEFAULT FALSE,
	was_activated BOOLEAN DEFAULT FALSE,
	stream_name VARCHAR(255),   -- Made optional
	schema_title VARCHAR(255),  -- Made optional
	schema_type VARCHAR(255),   -- Made optional
	email_address VARCHAR(255) NOT NULL,
	type VARCHAR(255) NOT NULL,
	variants JSON DEFAULT NULL, -- New column to store variants as JSON
	PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS instruments_history (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	instrument_id INT UNSIGNED NOT NULL,
	utc_journal_dt DATETIME NOT NULL,
	action VARCHAR(255) NOT NULL,
	configuration JSON NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS contextual_attributes (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	contextual_attribute_name VARCHAR(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS instrument_contextual_attribute_lookup (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	instrument_id INT UNSIGNED NOT NULL,
	contextual_attribute_id INT UNSIGNED NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS okrs (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	description VARCHAR(255) NOT NULL,
	PRIMARY KEY (id)
);

INSERT INTO contextual_attributes (contextual_attribute_name) VALUES
	('page_id'),
	('page_title'),
	('page_namespace_id'),
	('page_namespace_name'),
	('page_revision_id'),
	('page_wikidata_qid'),
	('page_content_language'),
	('page_is_redirect'),
	('page_user_groups_allowed_to_move'),
	('page_user_groups_allowed_to_edit'),
	('mediawiki_skin'),
	('mediawiki_version'),
	('mediawiki_database'),
	('mediawiki_site_content_language'),
	('mediawiki_site_content_language_variant'),
	('performer_active_browsing_session_token'),
	('performer_is_logged_in'),
	('performer_is_temp'),
	('performer_id'),
	('performer_name'),
	('performer_session_id'),
	('performer_pageview_id'),
	('performer_groups'),
	('performer_is_bot'),
	('performer_language'),
	('performer_language_variant'),
	('performer_can_probably_edit_page'),
	('performer_edit_count'),
	('performer_edit_count_bucket'),
	('performer_registration_dt'),
	('agent_ua_string');

INSERT INTO okrs (name, description) VALUES
	('FY24/25 SDS 2.4', 'FY24/25 SDS 2.4'),
	('FY24/25 WE 1.7', 'FY24/25 WE 1.7'),
	('FY25/26 PES 1.1', 'FY25/26 PES 1.1'),
	('FY25/26 SDS 2.1', 'FY25/26 SDS 2.1'),
	('FY25/26 WE 1.1', 'FY25/26 WE 1.1'),
	('FY25/26 WE 1.2', 'FY25/26 WE 1.2'),
	('FY25/26 WE 1.4', 'FY25/26 WE 1.4'),
	('FY25/26 WE 1.7', 'FY25/26 WE 1.7'),
	('FY25/26 WE 2.1', 'FY25/26 WE 2.1'),
	('FY25/26 WE 2.2', 'FY25/26 WE 2.2'),
	('FY25/26 WE 3.1', 'FY25/26 WE 3.1'),
	('FY25/26 WE 3.2', 'FY25/26 WE 3.2'),
	('FY25/26 WE 3.3', 'FY25/26 WE 3.3'),
	('FY25/26 WE 3.6', 'FY25/26 WE 3.6');
