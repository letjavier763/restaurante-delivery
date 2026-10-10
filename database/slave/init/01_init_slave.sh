#!/bin/bash
set -e

echo "[SLAVE INIT] Esperando a que MariaDB Master esté listo para replicación..."

until mariadb -h "${DB_MASTER_HOST:-mariadb-master}" -u "${DB_REPL_USER:-replicator}" -p"${DB_REPL_PASSWORD:-ReplicationPass_2026!}" -e "SELECT 1;" > /dev/null 2>&1; do
    echo "[SLAVE INIT] Master no disponible aún. Reintentando en 3 segundos..."
    sleep 3
done

echo "[SLAVE INIT] Conectado a Master con éxito. Configurando replicación..."

mariadb -u root -p"${MARIADB_ROOT_PASSWORD}" <<-EOSQL
    STOP SLAVE;
    CHANGE MASTER TO
        MASTER_HOST='${DB_MASTER_HOST:-mariadb-master}',
        MASTER_PORT=3306,
        MASTER_USER='${DB_REPL_USER:-replicator}',
        MASTER_PASSWORD='${DB_REPL_PASSWORD:-ReplicationPass_2026!}',
        MASTER_CONNECT_RETRY=10;
    START SLAVE;
    SHOW SLAVE STATUS\G
EOSQL

echo "[SLAVE INIT] Replicación inicializada correctamente."
