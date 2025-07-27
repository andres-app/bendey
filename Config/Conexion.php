<?php
// Conexion.php
require_once "config.php"; // Incluye configuración de conexión PDO

class Conexion
{
	private $conect;

	public function __construct()
	{
		global $conn;
		$this->conect = $conn;
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
}
