<?php
// Conexion.php
require_once __DIR__ . '/config.php';

class Conexion
{
    private $conect;

    public function __construct()
    {
        /*
         * Config/config.php ya crea una conexión PDO en $conn.
         * Reutilizarla evita abrir dos conexiones MySQL por cada
         * petición HTTP y reduce el riesgo de agotar el límite
         * de conexiones simultáneas del hosting.
         */
        global $conn;

        if (isset($conn) && $conn instanceof PDO) {
            $this->conect = $conn;
            return;
        }

        $this->conect = self::crearConexion();
    }

    private static function crearConexion()
    {
        try {
            $pdo = new PDO(
                "mysql:host=" . HOST
                . ";port=" . PORT
                . ";dbname=" . DB_NAME
                . ";charset=" . CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );

            $pdo->exec("SET time_zone = '-05:00'");

            return $pdo;
        } catch (PDOException $e) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die(json_encode([
                    "success" => false,
                    "error" => "Error en la conexión: " . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE));
            }

            http_response_code(500);

            die(json_encode([
                "success" => false,
                "error" => "No se pudo conectar con la base de datos."
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    public function setData($sql, $arrData = [])
    {
        $query = $this->conect->prepare($sql);

        try {
            return $query->execute($arrData);
        } finally {
            $query->closeCursor();
        }
    }

    public function getData($sql, $arrData = [])
    {
        $query = $this->conect->prepare($sql);

        try {
            $query->execute($arrData);
            $resultado = $query->fetch(PDO::FETCH_ASSOC);

            return $resultado;
        } finally {
            $query->closeCursor();
        }
    }

    public function getDataAll($sql, $arrData = [])
    {
        $query = $this->conect->prepare($sql);

        try {
            $query->execute($arrData);
            $resultado = $query->fetchAll(PDO::FETCH_ASSOC);

            return is_array($resultado)
                ? $resultado
                : [];
        } finally {
            $query->closeCursor();
        }
    }

    public function setDataReturnId($sql, $arrData = [])
    {
        $query = $this->conect->prepare($sql);

        try {
            $query->execute($arrData);
            return $this->conect->lastInsertId();
        } finally {
            $query->closeCursor();
        }
    }

    public function lastInsertId()
    {
        return $this->conect->lastInsertId();
    }

    // Método estático requerido por los modelos antiguos.
    public static function conectar()
    {
        global $conn;

        if (isset($conn) && $conn instanceof PDO) {
            return $conn;
        }

        return self::crearConexion();
    }

    public function getValue($sql, $arrData = [])
    {
        $query = $this->conect->prepare($sql);

        try {
            $query->execute($arrData);
            return $query->fetchColumn();
        } finally {
            $query->closeCursor();
        }
    }

    // =====================================
    // MÉTODOS DE TRANSACCIÓN (PDO)
    // =====================================
    public function beginTransaction()
    {
        if ($this->conect->inTransaction()) {
            return true;
        }

        return $this->conect->beginTransaction();
    }

    public function commit()
    {
        if (!$this->conect->inTransaction()) {
            return true;
        }

        return $this->conect->commit();
    }

    public function rollBack()
    {
        if (!$this->conect->inTransaction()) {
            return true;
        }

        return $this->conect->rollBack();
    }
}
