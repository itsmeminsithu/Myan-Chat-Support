CREATE TABLE IF NOT EXISTS mcs_user_sites (
 user_id BIGINT UNSIGNED NOT NULL,
 site_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (user_id,site_id),
 CONSTRAINT mcs_us_user_fk FOREIGN KEY (user_id) REFERENCES mcs_users(id) ON DELETE CASCADE,
 CONSTRAINT mcs_us_site_fk FOREIGN KEY (site_id) REFERENCES mcs_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
