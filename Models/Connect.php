<?php

require_once "../Config/config.php";

class Conexion
{
    private $conect = null;

    public function __construct()
    {
        $connectionString = "mysql:host=" . HOST . ";port=" . PORT . ";dbname=" . DB_NAME . ";charset=" . CHARSET;
        try {
            $this->conect = new PDO($connectionString, DB_USER, DB_PASS);
            $this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->conect = null;
            echo "Ocurrió un error en la conexión a la base de datos: " . $e->getMessage();
        }
    }

    // INSERTAR, ACTUALIZAR Y ELIMINAR DATOS
    public function setData($sql, $arrData)
    {
        $query = $this->conect->prepare($sql);
        $restQuery = $query->execute($arrData);
        return $restQuery;
    }

    // RETORNAR EL ID DEL ÚLTIMO REGISTRO
    public function getReturnId($sql, $arrData)
    {
        $query = $this->conect->prepare($sql);
        $query->execute($arrData);
        $idInsert = $this->conect->lastInsertId();
        return $idInsert;
    }

    // LISTAR TODOS LOS DATOS
    public function getDataAll($sql, $arrData = [])
    {
        if (!empty($arrData)) {
            $query = $this->conect->prepare($sql);
            $query->execute($arrData);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $execute = $this->conect->query($sql);
            return $execute->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // LISTAR DATOS POR ID (BUSCAR)
    public function getData($sql, $arrData)
    {
        $query = $this->conect->prepare($sql);
        $query->execute($arrData);
        $restQuery = $query->fetch(PDO::FETCH_ASSOC);
        return $restQuery;
    }
}

$conexion = new Conexion();
