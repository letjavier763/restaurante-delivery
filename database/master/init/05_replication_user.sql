-- ==============================================================================
-- 05_replication_user.sql - Usuario de replicación para MariaDB Slave
-- ==============================================================================

-- Usuario de Replicación
CREATE USER IF NOT EXISTS 'replicator'@'%' IDENTIFIED BY 'ReplicationPass_2026!';
GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'replicator'@'%';

-- Usuario de aplicación con privilegios sobre las 4 bases de datos de negocio
CREATE USER IF NOT EXISTS 'technova_app'@'%' IDENTIFIED BY 'AppMasterPass_2026!';
GRANT ALL PRIVILEGES ON `auth_db`.* TO 'technova_app'@'%';
GRANT ALL PRIVILEGES ON `catalogo_db`.* TO 'technova_app'@'%';
GRANT ALL PRIVILEGES ON `pedidos_db`.* TO 'technova_app'@'%';
GRANT ALL PRIVILEGES ON `pagos_db`.* TO 'technova_app'@'%';

FLUSH PRIVILEGES;
