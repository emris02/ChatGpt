-- Suppression des tables pour recréation propre (ordre ajusté selon les dépendances)
DROP TABLE IF EXISTS 
    `justificatifs`, `retards`, `absences`, `badge_journalier`, `badge_logs`, 
    `badge_scans`, `badge_tokens`, `demandes_badge`, `messages`, 
    `message_destinataires`, `password_resets`, `pointages`, `qr_codes`, 
    `employes`, `admins`;

-- Table employes
CREATE TABLE `employes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `prenom` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `rapport_quotidiens` tinyint(1) DEFAULT '0',
  `adresse` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `departement` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `poste` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qr_code` varchar(191) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `badge_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `badge_actif` tinyint(1) DEFAULT '1',
  `qr_code_data` text COLLATE utf8mb4_general_ci,
  `date_embauche` date DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_general_ci DEFAULT 'actif',
  `badge_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `matricule` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `qr_code` (`qr_code`),
  UNIQUE KEY `badge_id` (`badge_id`),
  UNIQUE KEY `matricule` (`matricule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table admins
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `adresse` varchar(25) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','super_admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `last_activity` timestamp NULL DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `poste` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `departement` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table qr_codes (créée avant pointages)
CREATE TABLE `qr_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `departement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `heure_arrivee` datetime NOT NULL,
  `type` enum('arriver','depart') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table badge_tokens (créée avant badge_scans et pointages)
CREATE TABLE `badge_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` int NOT NULL,
  `token_hash` varchar(128) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_info` text COLLATE utf8mb4_general_ci,
  `status` enum('active','revoked','expired') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `revoked_at` datetime DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'system',
  `last_used_at` datetime DEFAULT NULL,
  `usage_count` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_employe_status` (`employe_id`,`status`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_employe_id` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table pointages (créée avant justificatifs)
CREATE TABLE `pointages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_heure` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `employe_id` int NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `temps_total` time DEFAULT NULL,
  `type` enum('arrivee','depart','absence') COLLATE utf8mb4_general_ci NOT NULL,
  `retard_cause` text COLLATE utf8mb4_general_ci,
  `retard_justifie` enum('oui','non') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `est_justifie` tinyint(1) DEFAULT '0',
  `commentaire` text COLLATE utf8mb4_general_ci,
  `justifie_par` int DEFAULT NULL,
  `date_justification` datetime DEFAULT NULL,
  `type_justification` enum('médical','familial','autre') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `badge_token_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_info` text COLLATE utf8mb4_general_ci,
  `qr_code_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `justifie_par` (`justifie_par`),
  KEY `employe_id` (`employe_id`),
  KEY `badge_token_id` (`badge_token_id`),
  CONSTRAINT `fk_pointages_badge_token` FOREIGN KEY (`badge_token_id`) REFERENCES `badge_tokens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pointages_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pointages_justifie_par` FOREIGN KEY (`justifie_par`) REFERENCES `employes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pointages_qr_code` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table badge_scans (après badge_tokens)
CREATE TABLE `badge_scans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token_id` int DEFAULT NULL,
  `token_hash` varchar(128) COLLATE utf8mb4_general_ci NOT NULL,
  `scan_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `device_info` text COLLATE utf8mb4_general_ci,
  `is_valid` tinyint(1) NOT NULL,
  `validation_details` json DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `scan_type` enum('arrival','departure','access') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token_id` (`token_id`),
  KEY `idx_token_scan` (`token_hash`,`scan_time`),
  KEY `idx_scan_time` (`scan_time`),
  CONSTRAINT `badge_scans_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `badge_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table absences
CREATE TABLE `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_employe` int DEFAULT NULL,
  `date_absence` date DEFAULT NULL,
  `motif` text COLLATE utf8mb4_general_ci,
  `statut` enum('autorisé','non autorisé') COLLATE utf8mb4_general_ci DEFAULT 'non autorisé',
  PRIMARY KEY (`id`),
  KEY `id_employe` (`id_employe`),
  CONSTRAINT `fk_absence_employe` FOREIGN KEY (`id_employe`) REFERENCES `employes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table badge_journalier
CREATE TABLE `badge_journalier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` int NOT NULL,
  `code_badge` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `date_validite` date NOT NULL,
  `utilise_arrivee` tinyint(1) DEFAULT '0',
  `utilise_depart` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `employe_id` (`employe_id`,`date_validite`),
  CONSTRAINT `fk_badge_journalier_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table badge_logs
CREATE TABLE `badge_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employe_id` (`employe_id`),
  CONSTRAINT `fk_badge_logs_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table demandes_badge
CREATE TABLE `demandes_badge` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` int NOT NULL,
  `raison` text COLLATE utf8mb4_general_ci NOT NULL,
  `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_attente','approuve','rejete') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'en_attente',
  `date_traitement` datetime DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `raison_rejet` text COLLATE utf8mb4_general_ci,
  `is_read` tinyint(1) DEFAULT '0',
  `traite_par` int DEFAULT NULL COMMENT 'ID de l''admin qui a traité la demande',
  PRIMARY KEY (`id`),
  KEY `employe_id` (`employe_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_demandes_badge_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_demandes_badge_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table justificatifs (après pointages)
CREATE TABLE `justificatifs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pointage` int NOT NULL,
  `type_justif` enum('retard','absence') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `fichier` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_pointage` (`id_pointage`),
  CONSTRAINT `fk_justificatifs_pointage` FOREIGN KEY (`id_pointage`) REFERENCES `pointages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table late_reasons
CREATE TABLE `late_reasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scan_time` datetime NOT NULL,
  `late_time` int NOT NULL COMMENT 'En minutes',
  `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employe_id` (`employe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table messages (correction du COLLATE)
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expediteur_id` int NOT NULL,
  `sujet` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `contenu` text COLLATE utf8mb4_general_ci NOT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table message_destinataires (correction du COLLATE)
CREATE TABLE `message_destinataires` (
  `message_id` int NOT NULL,
  `destinataire_id` int NOT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `date_lecture` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`,`destinataire_id`),
  KEY `destinataire_id` (`destinataire_id`),
  CONSTRAINT `message_destinataires_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`),
  CONSTRAINT `message_destinataires_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `employes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table password_resets
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`(250)),
  KEY `token` (`token`(250))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table retards
CREATE TABLE `retards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employe_id` int NOT NULL,
  `date_retard` date NOT NULL,
  `heure_arrivee_prevue` time DEFAULT '09:00:00',
  `heure_arrivee_reelle` time DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT '0',
  `motif` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_retard` (`employe_id`,`date_retard`),
  CONSTRAINT `fk_retards_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trigger pour invalider les badges à la sortie
DROP TRIGGER IF EXISTS `after_depart_pointage`;
DELIMITER $$
CREATE TRIGGER `after_depart_pointage` AFTER INSERT ON `pointages` FOR EACH ROW
BEGIN
    IF NEW.type = 'depart' THEN
        UPDATE badge_tokens 
        SET expires_at = NOW() 
        WHERE employe_id = NEW.employe_id AND expires_at > NOW();
        
        INSERT INTO badge_logs (employe_id, action, details)
        VALUES (NEW.employe_id, 'invalidation', 'Badge invalidé après pointage de départ');
    END IF;
END
$$
DELIMITER ;