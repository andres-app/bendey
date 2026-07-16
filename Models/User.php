<?php

require_once __DIR__ . '/../Config/Conexion.php';

class User
{
    private $tableName = 'usuario';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    private function entero($valor)
    {
        return (int)$valor;
    }

    private function bandera($valor)
    {
        return ((int)$valor === 1) ? 1 : 0;
    }

    private function normalizarRol($rol)
    {
        $rol = strtoupper(trim((string)$rol));
        $permitidos = array('ADMINISTRADOR', 'CAJERO', 'VENDEDOR');

        return in_array($rol, $permitidos, true)
            ? $rol
            : 'VENDEDOR';
    }

    private function sincronizarPermisos($idusuario, $permisos)
    {
        $sw = true;

        $this->conexion->setData(
            'DELETE FROM usuario_permiso WHERE idusuario=?',
            array($idusuario)
        ) or $sw = false;

        $permisos = is_array($permisos) ? $permisos : array();
        $permisos = array_values(array_unique(array_map('intval', $permisos)));

        foreach ($permisos as $idpermiso) {
            if ($idpermiso <= 0) {
                continue;
            }

            $this->conexion->setData(
                'INSERT INTO usuario_permiso (idusuario,idpermiso) VALUES (?,?)',
                array($idusuario, $idpermiso)
            ) or $sw = false;
        }

        return $sw;
    }

    private function sincronizarSucursal(
        $idusuario,
        $idsucursal,
        $puedeVender,
        $puedeCobrar,
        $puedeAbrirCaja,
        $puedeCerrarCaja
    ) {
        $sw = true;

        $this->conexion->setData(
            'DELETE FROM usuario_sucursal WHERE idusuario=?',
            array($idusuario)
        ) or $sw = false;

        $idsucursal = $this->entero($idsucursal);

        if ($idsucursal > 0) {
            $sql = 'INSERT INTO usuario_sucursal
                    (idusuario,idsucursal,puede_vender,puede_cobrar,
                     puede_abrir_caja,puede_cerrar_caja,activo)
                    VALUES (?,?,?,?,?,?,1)';

            $this->conexion->setData(
                $sql,
                array(
                    $idusuario,
                    $idsucursal,
                    $this->bandera($puedeVender),
                    $this->bandera($puedeCobrar),
                    $this->bandera($puedeAbrirCaja),
                    $this->bandera($puedeCerrarCaja)
                )
            ) or $sw = false;
        }

        return $sw;
    }

    private function sincronizarCaja(
        $idusuario,
        $idcajas,
        $rol,
        $puedeAbrir,
        $puedeCerrar,
        $puedeOperar
    ) {
        $sw = true;

        $this->conexion->setData(
            'DELETE FROM usuario_caja WHERE idusuario=?',
            array($idusuario)
        ) or $sw = false;

        $idcajas = is_array($idcajas) ? $idcajas : array($idcajas);
        $idcajas = array_values(array_unique(array_map('intval', $idcajas)));

        foreach ($idcajas as $idcaja) {
            if ($idcaja <= 0) {
                continue;
            }

            $sql = 'INSERT INTO usuario_caja
                    (idusuario,idcaja,rol,puede_abrir,puede_cerrar,
                     puede_operar,activo,created_at,updated_at)
                    VALUES (?,?,?,?,?,?,1,NOW(),NOW())';

            $this->conexion->setData(
                $sql,
                array(
                    $idusuario,
                    $idcaja,
                    $this->normalizarRol($rol),
                    $this->bandera($puedeAbrir),
                    $this->bandera($puedeCerrar),
                    $this->bandera($puedeOperar)
                )
            ) or $sw = false;
        }

        return $sw;
    }

    private function existeTabla($tabla)
    {
        try {
            $resultado = $this->conexion->getDataAll(
                "SHOW TABLES LIKE '" . str_replace("'", "''", $tabla) . "'"
            );

            return is_array($resultado) && count($resultado) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function existeColumna($tabla, $columna)
    {
        try {
            $sql = "SHOW COLUMNS FROM `" . str_replace('`', '', $tabla)
                 . "` LIKE '" . str_replace("'", "''", $columna) . "'";

            $resultado = $this->conexion->getDataAll($sql);

            return is_array($resultado) && count($resultado) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function sincronizarAlmacen($idusuario, $idalmacen)
    {
        if (!$this->existeTabla('usuario_almacen')) {
            return true;
        }

        $sw = true;

        $this->conexion->setData(
            'DELETE FROM usuario_almacen WHERE idusuario=?',
            array($idusuario)
        ) or $sw = false;

        $idalmacen = $this->entero($idalmacen);

        if ($idalmacen > 0) {
            $this->conexion->setData(
                'INSERT INTO usuario_almacen
                 (idusuario,idalmacen,activo,created_at,updated_at)
                 VALUES (?,?,1,NOW(),NOW())',
                array($idusuario, $idalmacen)
            ) or $sw = false;
        }

        return $sw;
    }

    public function existeLogin($login, $idusuarioExcluir = 0)
    {
        $login = trim((string)$login);
        $idusuarioExcluir = (int)$idusuarioExcluir;

        if ($idusuarioExcluir > 0) {
            $sql = "SELECT idusuario
                    FROM {$this->tableName}
                    WHERE login=? AND idusuario<>?
                    LIMIT 1";
            $fila = $this->conexion->getData(
                $sql,
                array($login, $idusuarioExcluir)
            );
        } else {
            $sql = "SELECT idusuario
                    FROM {$this->tableName}
                    WHERE login=?
                    LIMIT 1";
            $fila = $this->conexion->getData($sql, array($login));
        }

        return is_array($fila) && !empty($fila);
    }

    public function insertar(
        $nombre,
        $tipoDocumento,
        $numDocumento,
        $direccion,
        $telefono,
        $email,
        $cargo,
        $login,
        $clave,
        $imagen,
        $permisos,
        $idsucursal,
        $idcajas,
        $idalmacen,
        $rol,
        $puedeVender,
        $puedeCobrar,
        $puedeAbrirCajaSucursal,
        $puedeCerrarCajaSucursal,
        $puedeAbrirCaja,
        $puedeCerrarCaja,
        $puedeOperarCaja
    ) {
        $sql = "INSERT INTO {$this->tableName}
                (nombre,tipo_documento,num_documento,direccion,telefono,
                 email,cargo,login,clave,imagen,condicion)
                VALUES (?,?,?,?,?,?,?,?,?,?,1)";

        $idusuario = $this->conexion->setDataReturnId(
            $sql,
            array(
                $nombre,
                $tipoDocumento,
                $numDocumento,
                $direccion,
                $telefono,
                $email,
                $cargo,
                $login,
                $clave,
                $imagen
            )
        );

        if ((int)$idusuario <= 0) {
            return false;
        }

        $sw = true;

        $this->sincronizarPermisos($idusuario, $permisos) or $sw = false;

        $this->sincronizarSucursal(
            $idusuario,
            $idsucursal,
            $puedeVender,
            $puedeCobrar,
            $puedeAbrirCajaSucursal,
            $puedeCerrarCajaSucursal
        ) or $sw = false;

        $this->sincronizarCaja(
            $idusuario,
            $idcajas,
            $rol,
            $puedeAbrirCaja,
            $puedeCerrarCaja,
            $puedeOperarCaja
        ) or $sw = false;

        $this->sincronizarAlmacen(
            $idusuario,
            $idalmacen
        ) or $sw = false;

        return $sw;
    }

    public function editar(
        $idusuario,
        $nombre,
        $tipoDocumento,
        $numDocumento,
        $direccion,
        $telefono,
        $email,
        $cargo,
        $login,
        $imagen,
        $permisos,
        $idsucursal,
        $idcajas,
        $idalmacen,
        $rol,
        $puedeVender,
        $puedeCobrar,
        $puedeAbrirCajaSucursal,
        $puedeCerrarCajaSucursal,
        $puedeAbrirCaja,
        $puedeCerrarCaja,
        $puedeOperarCaja
    ) {
        $sw = true;

        $sql = "UPDATE {$this->tableName}
                SET nombre=?,tipo_documento=?,num_documento=?,direccion=?,
                    telefono=?,email=?,cargo=?,login=?,imagen=?
                WHERE idusuario=?";

        $this->conexion->setData(
            $sql,
            array(
                $nombre,
                $tipoDocumento,
                $numDocumento,
                $direccion,
                $telefono,
                $email,
                $cargo,
                $login,
                $imagen,
                $idusuario
            )
        ) or $sw = false;

        $this->sincronizarPermisos($idusuario, $permisos) or $sw = false;

        $this->sincronizarSucursal(
            $idusuario,
            $idsucursal,
            $puedeVender,
            $puedeCobrar,
            $puedeAbrirCajaSucursal,
            $puedeCerrarCajaSucursal
        ) or $sw = false;

        $this->sincronizarCaja(
            $idusuario,
            $idcajas,
            $rol,
            $puedeAbrirCaja,
            $puedeCerrarCaja,
            $puedeOperarCaja
        ) or $sw = false;

        $this->sincronizarAlmacen(
            $idusuario,
            $idalmacen
        ) or $sw = false;

        return $sw;
    }

    public function editar_clave($idusuario, $clave)
    {
        return $this->conexion->setData(
            "UPDATE {$this->tableName} SET clave=? WHERE idusuario=?",
            array($clave, $idusuario)
        );
    }

    public function mostrar_clave($idusuario)
    {
        return $this->conexion->getData(
            "SELECT idusuario,clave
             FROM {$this->tableName}
             WHERE idusuario=?",
            array($idusuario)
        );
    }

    public function desactivar($idusuario)
    {
        return $this->conexion->setData(
            "UPDATE {$this->tableName} SET condicion=0 WHERE idusuario=?",
            array($idusuario)
        );
    }

    public function activar($idusuario)
    {
        return $this->conexion->setData(
            "UPDATE {$this->tableName} SET condicion=1 WHERE idusuario=?",
            array($idusuario)
        );
    }

    public function mostrar($idusuario)
    {
        $idusuario = (int)$idusuario;

        $usuario = $this->conexion->getData(
            "SELECT *
             FROM {$this->tableName}
             WHERE idusuario=?
             LIMIT 1",
            array($idusuario)
        );

        if (!is_array($usuario) || empty($usuario)) {
            return array();
        }

        $sucursal = $this->conexion->getData(
            'SELECT idsucursal,puede_vender,puede_cobrar,
                    puede_abrir_caja,puede_cerrar_caja
             FROM usuario_sucursal
             WHERE idusuario=? AND activo=1
             ORDER BY idusuario_sucursal DESC
             LIMIT 1',
            array($idusuario)
        );

        $cajas = $this->conexion->getDataAll(
            "SELECT idcaja,rol,puede_abrir,puede_cerrar,puede_operar
             FROM usuario_caja
             WHERE idusuario={$idusuario} AND activo=1
             ORDER BY idusuario_caja ASC"
        );

        $almacen = array();

        if ($this->existeTabla('usuario_almacen')) {
            $almacen = $this->conexion->getData(
                'SELECT idalmacen
                 FROM usuario_almacen
                 WHERE idusuario=? AND activo=1
                 ORDER BY idusuario_almacen DESC
                 LIMIT 1',
                array($idusuario)
            );
        }

        $idcajas = array();
        $rol = 'VENDEDOR';
        $puedeAbrir = 0;
        $puedeCerrar = 0;
        $puedeOperar = 0;

        if (is_array($cajas)) {
            foreach ($cajas as $indice => $caja) {
                $idcaja = (int)($caja['idcaja'] ?? 0);

                if ($idcaja > 0) {
                    $idcajas[] = $idcaja;
                }

                if ($indice === 0) {
                    $rol = (string)($caja['rol'] ?? 'VENDEDOR');
                    $puedeAbrir = (int)($caja['puede_abrir'] ?? 0);
                    $puedeCerrar = (int)($caja['puede_cerrar'] ?? 0);
                    $puedeOperar = (int)($caja['puede_operar'] ?? 0);
                }
            }
        }

        $usuario['idsucursal'] = (int)($sucursal['idsucursal'] ?? 0);
        $usuario['puede_vender'] = (int)($sucursal['puede_vender'] ?? 0);
        $usuario['puede_cobrar'] = (int)($sucursal['puede_cobrar'] ?? 0);
        $usuario['puede_abrir_caja_sucursal'] =
            (int)($sucursal['puede_abrir_caja'] ?? 0);
        $usuario['puede_cerrar_caja_sucursal'] =
            (int)($sucursal['puede_cerrar_caja'] ?? 0);

        $usuario['idcajas'] = $idcajas;
        $usuario['idcaja'] = isset($idcajas[0]) ? (int)$idcajas[0] : 0;
        $usuario['rol'] = $rol;
        $usuario['puede_abrir'] = $puedeAbrir;
        $usuario['puede_cerrar'] = $puedeCerrar;
        $usuario['puede_operar'] = $puedeOperar;
        $usuario['idalmacen'] = (int)($almacen['idalmacen'] ?? 0);

        return $usuario;
    }

    public function listar()
    {
        $selectAlmacen = '0 AS idalmacen';
        $joinAlmacen = '';
        $groupAlmacen = '';

        if ($this->existeTabla('usuario_almacen')) {
            $selectAlmacen = 'COALESCE(MAX(ua.idalmacen),0) AS idalmacen';
            $joinAlmacen = 'LEFT JOIN usuario_almacen ua
                            ON ua.idusuario=u.idusuario
                           AND ua.activo=1';
        }

        $sql = "SELECT
                    u.idusuario,
                    u.nombre,
                    u.tipo_documento,
                    u.num_documento,
                    u.telefono,
                    u.email,
                    u.login,
                    u.imagen,
                    u.condicion,
                    COALESCE(MAX(us.idsucursal),0) AS idsucursal,
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT uc.idcaja
                            ORDER BY uc.idcaja
                            SEPARATOR ','
                        ),
                        ''
                    ) AS idcajas,
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT uc.rol
                            ORDER BY uc.rol
                            SEPARATOR ', '
                        ),
                        'SIN ROL'
                    ) AS rol,
                    {$selectAlmacen}
                FROM {$this->tableName} u
                LEFT JOIN usuario_sucursal us
                    ON us.idusuario=u.idusuario
                   AND us.activo=1
                LEFT JOIN usuario_caja uc
                    ON uc.idusuario=u.idusuario
                   AND uc.activo=1
                {$joinAlmacen}
                GROUP BY
                    u.idusuario,u.nombre,u.tipo_documento,u.num_documento,
                    u.telefono,u.email,u.login,u.imagen,u.condicion
                ORDER BY u.idusuario DESC";

        return $this->conexion->getDataAll($sql);
    }

    public function listarmarcados($idusuario)
    {
        $idusuario = (int)$idusuario;

        return $this->conexion->getDataAll(
            "SELECT idusuario,idpermiso
             FROM usuario_permiso
             WHERE idusuario={$idusuario}"
        );
    }

    public function listarSucursales()
    {
        return $this->conexion->getDataAll(
            "SELECT idsucursal,codigo,nombre,direccion,
                    codigo_establecimiento_sunat,principal,activo
             FROM sucursal
             WHERE activo=1
             ORDER BY principal DESC,nombre ASC"
        );
    }

    public function listarCajas()
    {
        /*
         * Se intenta cargar primero desde la tabla maestra de cajas.
         * Algunos proyectos la nombran `caja` y otros `cajas`.
         */
        $tablasCandidatas = array('caja', 'cajas');
        $salida = array();

        foreach ($tablasCandidatas as $tablaCaja) {
            if (!$this->existeTabla($tablaCaja)) {
                continue;
            }

            try {
                $filas = $this->conexion->getDataAll(
                    'SELECT * FROM `' . $tablaCaja . '` ORDER BY idcaja ASC'
                );
            } catch (Throwable $e) {
                $filas = array();
            }

            if (!is_array($filas)) {
                $filas = array();
            }

            foreach ($filas as $fila) {
                $idcaja = (int)($fila['idcaja'] ?? 0);

                if ($idcaja <= 0) {
                    continue;
                }

                /*
                 * No se confunde el estado de apertura con la vigencia
                 * de la caja. Solo se descartan valores explícitamente
                 * inactivos.
                 */
                $activo = 1;

                if (array_key_exists('activo', $fila)) {
                    $activo = (int)$fila['activo'];
                } elseif (array_key_exists('condicion', $fila)) {
                    $activo = (int)$fila['condicion'];
                } elseif (array_key_exists('estado', $fila)) {
                    $estado = strtoupper(trim((string)$fila['estado']));

                    if (in_array(
                        $estado,
                        array('0', 'INACTIVA', 'INACTIVO', 'DESACTIVADA', 'DESACTIVADO'),
                        true
                    )) {
                        $activo = 0;
                    }
                }

                if ($activo !== 1) {
                    continue;
                }

                $codigo = trim((string)($fila['codigo'] ?? ''));
                $nombre = trim((string)($fila['nombre'] ?? ''));
                $descripcion = trim((string)($fila['descripcion'] ?? ''));

                if ($codigo !== '' && $nombre !== '') {
                    $etiqueta = $codigo . ' - ' . $nombre;
                } elseif ($nombre !== '') {
                    $etiqueta = $nombre;
                } elseif ($codigo !== '') {
                    $etiqueta = $codigo;
                } elseif ($descripcion !== '') {
                    $etiqueta = $descripcion;
                } else {
                    $etiqueta = 'Caja #' . $idcaja;
                }

                $idsucursal = (int)(
                    $fila['idsucursal']
                    ?? $fila['id_sucursal']
                    ?? $fila['sucursal_id']
                    ?? 0
                );

                $salida[$idcaja] = array(
                    'idcaja' => $idcaja,
                    'idsucursal' => $idsucursal,
                    'etiqueta' => $etiqueta
                );
            }

            if (!empty($salida)) {
                break;
            }
        }

        /*
         * Compatibilidad con instalaciones donde ya existen asignaciones
         * en usuario_caja, pero todavía no hay una tabla maestra `caja`
         * utilizable. Así, Caja #1, Caja #2, etc. siguen siendo asignables.
         */
        if (empty($salida) && $this->existeTabla('usuario_caja')) {
            try {
                $filasAsignadas = $this->conexion->getDataAll(
                    'SELECT DISTINCT idcaja
                     FROM usuario_caja
                     WHERE idcaja IS NOT NULL
                       AND idcaja > 0
                     ORDER BY idcaja ASC'
                );
            } catch (Throwable $e) {
                $filasAsignadas = array();
            }

            if (is_array($filasAsignadas)) {
                foreach ($filasAsignadas as $fila) {
                    $idcaja = (int)($fila['idcaja'] ?? 0);

                    if ($idcaja <= 0) {
                        continue;
                    }

                    $salida[$idcaja] = array(
                        'idcaja' => $idcaja,
                        'idsucursal' => 0,
                        'etiqueta' => 'Caja #' . $idcaja
                    );
                }
            }
        }

        ksort($salida);

        return array_values($salida);
    }

    public function listarAlmacenes()
    {
        if (!$this->existeTabla('almacen')) {
            return array();
        }

        try {
            $filas = $this->conexion->getDataAll('SELECT * FROM almacen ORDER BY nombre ASC');
        } catch (Throwable $e) {
            return array();
        }

        $tieneSucursal = $this->existeColumna('almacen', 'idsucursal');
        $salida = array();

        foreach ($filas as $fila) {
            $activo = array_key_exists('estado', $fila)
                ? (int)$fila['estado']
                : (int)($fila['activo'] ?? 1);

            if ($activo !== 1) {
                continue;
            }

            $nombre = trim((string)($fila['nombre'] ?? ''));
            $ubicacion = trim((string)($fila['ubicacion'] ?? ''));

            $salida[] = array(
                'idalmacen' => (int)($fila['idalmacen'] ?? 0),
                'idsucursal' => $tieneSucursal
                    ? (int)($fila['idsucursal'] ?? 0)
                    : 0,
                'etiqueta' => $nombre !== ''
                    ? $nombre
                    : ('Almacén #' . (int)($fila['idalmacen'] ?? 0)),
                'ubicacion' => $ubicacion
            );
        }

        return $salida;
    }

    public function obtenerAsignacionOperativa($idusuario)
    {
        $usuario = $this->mostrar($idusuario);

        if (!is_array($usuario)) {
            return array();
        }

        return array(
            'idsucursal' => (int)($usuario['idsucursal'] ?? 0),
            'idcaja' => (int)($usuario['idcaja'] ?? 0),
            'idcajas' => isset($usuario['idcajas']) && is_array($usuario['idcajas'])
                ? $usuario['idcajas']
                : array(),
            'idalmacen' => (int)($usuario['idalmacen'] ?? 0),
            'rol' => (string)($usuario['rol'] ?? 'VENDEDOR'),
            'puede_vender' => (int)($usuario['puede_vender'] ?? 0),
            'puede_cobrar' => (int)($usuario['puede_cobrar'] ?? 0),
            'puede_abrir_caja' => (int)($usuario['puede_abrir'] ?? 0),
            'puede_cerrar_caja' => (int)($usuario['puede_cerrar'] ?? 0),
            'puede_operar_caja' => (int)($usuario['puede_operar'] ?? 0)
        );
    }

    public function verificar($login, $clave)
    {
        $sql = "SELECT idusuario,nombre,tipo_documento,num_documento,
                       telefono,email,cargo,imagen,login
                FROM {$this->tableName}
                WHERE login=? AND clave=? AND condicion=1
                LIMIT 1";

        return $this->conexion->getData($sql, array($login, $clave));
    }
}
