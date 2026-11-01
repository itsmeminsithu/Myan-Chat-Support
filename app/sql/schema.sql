CREATE TABLE IF NOT EXISTS mcs_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 availability ENUM('online','away','offline') NOT NULL DEFAULT 'offline',
 last_seen_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_sites (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_key CHAR(48) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 allowed_origins TEXT NOT NULL,
 primary_color CHAR(7) NOT NULL DEFAULT '#3865D8',
 widget_title_en VARCHAR(120) NOT NULL DEFAULT 'Chat with us',
 widget_title_mm VARCHAR(120) NOT NULL DEFAULT 'ကျွန်ုပ်တို့ကို ဆက်သွယ်ပါ',
 welcome_en VARCHAR(255) NOT NULL DEFAULT 'Hello! How can we help you today?',
 welcome_mm VARCHAR(255) NOT NULL DEFAULT 'မင်္ဂလာပါ။ ဘာကူညီပေးရမလဲ?',
 default_language ENUM('en','mm') NOT NULL DEFAULT 'en',
 contact_email VARCHAR(190) NOT NULL,
 sound ENUM('chime','ping','bell','soft','double','bright','wood','ripple','crystal','pop','pulse','none') NOT NULL DEFAULT 'chime',
 collect_email TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_user_sites (
 user_id BIGINT UNSIGNED NOT NULL,
 site_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (user_id,site_id),
 CONSTRAINT mcs_us_user_fk FOREIGN KEY (user_id) REFERENCES mcs_users(id) ON DELETE CASCADE,
 CONSTRAINT mcs_us_site_fk FOREIGN KEY (site_id) REFERENCES mcs_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_conversations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id BIGINT UNSIGNED NOT NULL,
 guest_token_hash CHAR(64) NOT NULL,
 guest_name VARCHAR(120) NOT NULL,
 guest_email VARCHAR(190) NULL,
 guest_ip_hash CHAR(64) NOT NULL,
 page_url VARCHAR(1024) NULL,
 status ENUM('open','closed') NOT NULL DEFAULT 'open',
 assigned_operator_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_site_status (site_id,status,last_message_at),
 INDEX idx_operator (assigned_operator_id,status),
 CONSTRAINT mcs_conv_site_fk FOREIGN KEY (site_id) REFERENCES mcs_sites(id) ON DELETE CASCADE,
 CONSTRAINT mcs_conv_user_fk FOREIGN KEY (assigned_operator_id) REFERENCES mcs_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 conversation_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('guest','operator','system') NOT NULL,
 sender_user_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_by_operator_at DATETIME NULL,
 INDEX idx_conversation_id (conversation_id,id),
 CONSTRAINT mcs_msg_conv_fk FOREIGN KEY (conversation_id) REFERENCES mcs_conversations(id) ON DELETE CASCADE,
 CONSTRAINT mcs_msg_user_fk FOREIGN KEY (sender_user_id) REFERENCES mcs_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_typing (
 conversation_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('guest','operator') NOT NULL,
 last_typing_at DATETIME NOT NULL,
 PRIMARY KEY (conversation_id,sender_type),
 CONSTRAINT mcs_typing_conv_fk FOREIGN KEY (conversation_id) REFERENCES mcs_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 page_url VARCHAR(1024) NULL,
 status ENUM('new','reviewed') NOT NULL DEFAULT 'new',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_site_date (site_id,created_at),
 CONSTRAINT mcs_contacts_fk FOREIGN KEY (site_id) REFERENCES mcs_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_canned (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id BIGINT UNSIGNED NOT NULL,
 shortcut VARCHAR(60) NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY idx_shortcut (site_id,shortcut),
 CONSTRAINT mcs_canned_fk FOREIGN KEY (site_id) REFERENCES mcs_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_rate_limits (
 rate_key CHAR(64) PRIMARY KEY,
 window_start DATETIME NOT NULL,
 requests INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mcs_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 subject VARCHAR(160) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_date (created_at),
 CONSTRAINT mcs_audit_user_fk FOREIGN KEY (actor_id) REFERENCES mcs_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
