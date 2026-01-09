<?php
// Conexion.php
require_once __DIR__ . '/config.php';

class Conexion
{
	private $conect;

	public function __construct()
	{
		try {
			$this->conect = new PDO(
				"mysql:host=" . HOST . ";port=" . PORT . ";dbname=" . DB_NAME . ";charset=" . CHARSET,
				DB_USER,
				DB_PASS
			);
			$this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die(json_encode([
				"success" => false,
				"error" => "❌ Error en la conexión: " . $e->getMessage()
			]));
		}
	}

	public function setData($sql, $arrData = [])
	{
		$query = $this->conect->prepare($sql);
		return $query->execute($arrData);
	}

	public function getData($sql, $arrData = [])
	{
		$query = $this->conect->prepare($sql);
		$query->execute($arrData);
		return $query->fetch(PDO::FETCH_ASSOC);
	}

	public function getDataAll($sql, $arrData = [])
	{
		$query = $this->conect->prepare($sql);
		$query->execute($arrData);
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}

	public function setDataReturnId($sql, $arrData = [])
	{
		$query = $this->conect->prepare($sql);
		$query->execute($arrData);
		return $this->conect->lastInsertId();
	}

	public function lastInsertId()
	{
		return $this->conect->lastInsertId();
	}

	// ✅ Método estático requerido por los modelos antiguos
	public static function conectar()
	{
		try {
			$conect = new PDO(
				"mysql:host=" . HOST . ";port=" . PORT . ";dbname=" . DB_NAME . ";charset=" . CHARSET,
				DB_USER,
				DB_PASS
			);
			$conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $conect;
		} catch (PDOException $e) {
			die(json_encode([
				"success" => false,
				"error" => "❌ Error en la conexión: " . $e->getMessage()
			]));
		}
	}

	public function getValue($sql, $arrData = [])
	{
		$query = $this->conect->prepare($sql);
		$query->execute($arrData);
		return $query->fetchColumn();
	}

	    // =====================================
    // 🔐 MÉTODOS DE TRANSACCIÓN (PDO)
    // =====================================
    public function beginTransaction()
    {
        return $this->conect->beginTransaction();
    }

    public function commit()
    {
        return $this->conect->commit();
    }

    public function rollBack()
    {
        return $this->conect->rollBack();
    }


}
