-- TiquePOS - Recuperación de contraseña mediante OTP
-- Ejecutar una sola vez en la base de datos de cada cliente existente.

CREATE TABLE IF NOT EXISTS `usuario_password_otp` (
  `idreset` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `otp_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`idreset`),
  UNIQUE KEY `uk_usuario_password_otp_token` (`token_hash`),
  KEY `idx_usuario_password_otp_usuario_fecha` (`idusuario`,`created_at`),
  KEY `idx_usuario_password_otp_ip_fecha` (`request_ip`,`created_at`),
  CONSTRAINT `fk_usuario_password_otp_usuario`
    FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
