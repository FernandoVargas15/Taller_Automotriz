<?php
declare(strict_types=1);

// MODELO Vehiculo — cada auto que entra al taller con los datos de su cliente

final class Vehiculo
{
    public const CLIENTE_MIN  = 3;
    public const CLIENTE_MAX  = 100;
    public const TELEFONO_MAX = 20;
    public const MARCA_MAX    = 50;
    public const MODELO_MAX   = 50;
    public const COLOR_MAX    = 30;
    public const PLACAS_MAX   = 15;
    public const ANIO_MIN     = 1900;

    private const COLUMNAS = 'id, folio, cliente_nombre, cliente_telefono, marca, modelo, anio, color, placas,
                              area_id, area_nombre, asignado_a, asignado_nombre, asignado_rol,
                              creado_en, actualizado_en, entregado_en';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia()->conexion();
    }

    // Reglas de negocio

    /**
     * REGLA DE EVIDENCIA: un auto no puede pasar a "Terminado" si su bitácora no tiene
     * al menos una foto. Así el cliente siempre ve el resultado del trabajo.
     */
    public const MENSAJE_SIN_EVIDENCIA = 'No se puede marcar como Terminado: la bitácora necesita al menos una foto del trabajo.';

    public function puedeTerminar(string $id): bool
    {
        return (new Bitacora())->contarFotos($id) > 0;
    }

    /**
     * REGLA DE FLUJO: una vez entregado, la bitácora se cierra. Nadie agrega reportes ni
     * cambia el área; solo el administrador puede corregir o eliminar.
     */
    public const MENSAJE_ENTREGADO = 'Este auto ya fue entregado: su bitácora está cerrada.';

    public static function estaEntregado(array $vehiculo): bool
    {
        return (int) $vehiculo['area_id'] === Area::ENTREGADO;
    }

    /**
     * REGLA DE ASIGNACIÓN: un mecánico solo trabaja los autos que el administrador
     * le asignó. Se usa antes de mostrar o modificar cualquier auto desde su panel.
     */
    public static function asignadoA(array $vehiculo, string $usuarioId): bool
    {
        return $vehiculo['asignado_a'] !== null && $vehiculo['asignado_a'] === $usuarioId;
    }

    // Validacion

    /**
     * Devuelve ['campo' => 'mensaje']; vacío si todo está bien.
     * $actual es el auto tal como está guardado (null en un alta): sirve para aplicar
     * la regla de evidencia solo cuando se intenta PASAR a Terminado.
     */
    public function validar(array $datos, ?array $actual = null): array
    {
        $errores = [];

        $cliente  = trim((string) ($datos['cliente_nombre'] ?? ''));
        $telefono = trim((string) ($datos['cliente_telefono'] ?? ''));
        $marca    = trim((string) ($datos['marca'] ?? ''));
        $modelo   = trim((string) ($datos['modelo'] ?? ''));
        $anio     = trim((string) ($datos['anio'] ?? ''));
        $color    = trim((string) ($datos['color'] ?? ''));
        $placas   = trim((string) ($datos['placas'] ?? ''));
        $areaId   = (int) ($datos['area_id'] ?? 0);
        $asignado = trim((string) ($datos['asignado_a'] ?? ''));

        if ($cliente === '') {
            $errores['cliente_nombre'] = 'El nombre del cliente es obligatorio.';
        } elseif (mb_strlen($cliente) < self::CLIENTE_MIN) {
            $errores['cliente_nombre'] = 'El nombre debe tener al menos ' . self::CLIENTE_MIN . ' caracteres.';
        } elseif (mb_strlen($cliente) > self::CLIENTE_MAX) {
            $errores['cliente_nombre'] = 'El nombre no puede pasar de ' . self::CLIENTE_MAX . ' caracteres.';
        }

        // Con este teléfono (y el folio) el cliente consultará su auto
        if ($telefono === '') {
            $errores['cliente_telefono'] = 'El teléfono es obligatorio.';
        } elseif (!preg_match('/^[0-9 +()\-]{8,' . self::TELEFONO_MAX . '}$/', $telefono)) {
            $errores['cliente_telefono'] = 'Escribe un teléfono válido (solo dígitos, espacios, + o guiones).';
        }

        if ($marca === '') {
            $errores['marca'] = 'La marca es obligatoria.';
        } elseif (mb_strlen($marca) > self::MARCA_MAX) {
            $errores['marca'] = 'La marca no puede pasar de ' . self::MARCA_MAX . ' caracteres.';
        }

        if ($modelo === '') {
            $errores['modelo'] = 'El modelo es obligatorio.';
        } elseif (mb_strlen($modelo) > self::MODELO_MAX) {
            $errores['modelo'] = 'El modelo no puede pasar de ' . self::MODELO_MAX . ' caracteres.';
        }

        $anioMax = (int) date('Y') + 1;
        if ($anio !== '' && (!ctype_digit($anio) || (int) $anio < self::ANIO_MIN || (int) $anio > $anioMax)) {
            $errores['anio'] = 'El año debe estar entre ' . self::ANIO_MIN . ' y ' . $anioMax . '.';
        }

        if (mb_strlen($color) > self::COLOR_MAX) {
            $errores['color'] = 'El color no puede pasar de ' . self::COLOR_MAX . ' caracteres.';
        }

        if (mb_strlen($placas) > self::PLACAS_MAX) {
            $errores['placas'] = 'Las placas no pueden pasar de ' . self::PLACAS_MAX . ' caracteres.';
        }

        if (!(new Area())->existe($areaId)) {
            $errores['area_id'] = 'Selecciona un área válida.';
        } elseif ($areaId === Area::TERMINADO && $actual === null) {
            $errores['area_id'] = 'Un auto recién ingresado no puede estar Terminado: primero necesita fotos en su bitácora.';
        } elseif ($areaId === Area::TERMINADO && (int) $actual['area_id'] !== Area::TERMINADO && !$this->puedeTerminar($actual['id'])) {
            $errores['area_id'] = self::MENSAJE_SIN_EVIDENCIA;
        }

        // Vacío = sin asignar. Si viene algo, debe ser un empleado real y activo.
        if ($asignado !== '') {
            $empleado = es_uuid($asignado) ? (new Usuario())->buscar($asignado) : null;

            if ($empleado === null || !$empleado['activo']) {
                $errores['asignado_a'] = 'Selecciona un empleado válido.';
            }
        }

        return $errores;
    }

    // Consultas

    /** Inserta y devuelve el UUID generado. Espera datos ya validados. */
    public function crear(array $datos): string
    {
        $sql = 'INSERT INTO vehiculos
                    (cliente_nombre, cliente_telefono, marca, modelo, anio, color, placas, area_id, asignado_a)
                VALUES
                    (:cliente_nombre, :cliente_telefono, :marca, :modelo, :anio, :color, :placas, :area_id, :asignado_a)
                RETURNING id';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($this->parametros($datos));

        return (string) $sentencia->fetchColumn();
    }

    /** Corrige cualquier dato del auto (el admin corrige lo que se capturó mal). */
    public function actualizar(string $id, array $datos): void
    {
        $sql = 'UPDATE vehiculos SET
                    cliente_nombre   = :cliente_nombre,
                    cliente_telefono = :cliente_telefono,
                    marca            = :marca,
                    modelo           = :modelo,
                    anio             = :anio,
                    color            = :color,
                    placas           = :placas,
                    area_id          = :area_id,
                    asignado_a       = :asignado_a,
                    actualizado_en   = NOW()
                WHERE id = :id';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($this->parametros($datos) + [':id' => $id]);
    }

    /** Mueve el auto de área (Recepción → Mecánica → ...). Las reglas las revisa quien llama. */
    public function cambiarArea(string $id, int $areaId): void
    {
        $sentencia = $this->db->prepare(
            'UPDATE vehiculos SET area_id = :area_id, actualizado_en = NOW() WHERE id = :id'
        );
        $sentencia->execute([':area_id' => $areaId, ':id' => $id]);
    }

    /** Autos asignados a un mecánico; los que siguen en el taller primero. */
    public function porMecanico(string $usuarioId): array
    {
        $sentencia = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . '
             FROM v_vehiculos
             WHERE asignado_a = :usuario_id
             ORDER BY (area_id = ' . Area::ENTREGADO . '), creado_en DESC'
        );
        $sentencia->execute([':usuario_id' => $usuarioId]);

        return $sentencia->fetchAll();
    }

    /**
     * Consulta pública del cliente: folio + teléfono. Los dos deben coincidir para que
     * nadie vea el auto de otro adivinando folios. El teléfono se compara solo por dígitos.
     */
    public function buscarPorFolioYTelefono(string $folio, string $telefono): ?array
    {
        $digitos = preg_replace('/\D+/', '', $telefono);

        if ($folio === '' || $digitos === '') {
            return null;
        }

        $sentencia = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . "
             FROM v_vehiculos
             WHERE UPPER(folio) = UPPER(:folio)
               AND REGEXP_REPLACE(cliente_telefono, '\D', '', 'g') = :telefono"
        );
        $sentencia->execute([':folio' => trim($folio), ':telefono' => $digitos]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * El cliente ya se llevó el auto: pasa a Entregado y se guarda la fecha.
     * El mecánico sigue asignado para que conserve el auto en su historial.
     */
    public function entregar(string $id): void
    {
        $sentencia = $this->db->prepare(
            'UPDATE vehiculos
             SET area_id = :entregado, entregado_en = NOW(), actualizado_en = NOW()
             WHERE id = :id'
        );
        $sentencia->execute([':entregado' => Area::ENTREGADO, ':id' => $id]);
    }

    /** Borrado definitivo; la BD borra en cascada su bitácora (las fotos las quita Bitacora antes). */
    public function eliminar(string $id): void
    {
        $this->db->prepare('DELETE FROM vehiculos WHERE id = :id')->execute([':id' => $id]);
    }

    public function buscar(string $id): ?array
    {
        $sentencia = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM v_vehiculos WHERE id = :id'
        );
        $sentencia->execute([':id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Todos los autos, del ingreso más reciente al más antiguo. Los que siguen en el taller
     * van primero y los entregados al final. Con $busqueda filtra por folio, cliente,
     * teléfono, placas, marca o modelo (sin distinguir mayúsculas ni acentos exactos).
     */
    public function todos(string $busqueda = '', int $limite = 500): array
    {
        $sql        = 'SELECT ' . self::COLUMNAS . ' FROM v_vehiculos';
        $parametros = [];

        if ($busqueda !== '') {
            // Todo en una sola cadena para buscar con un único parámetro: "Ford Mustang", "ABC-123", "María"...
            $sql .= " WHERE CONCAT_WS(' ', folio, cliente_nombre, cliente_telefono, placas, marca, modelo, anio) ILIKE :q";
            $parametros[':q'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY (area_id = ' . Area::ENTREGADO . '), creado_en DESC LIMIT :limite';

        $sentencia = $this->db->prepare($sql);
        foreach ($parametros as $clave => $valor) {
            $sentencia->bindValue($clave, $valor);
        }
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * Autos RECIBIDOS dentro de un periodo (día, semana o mes), del primero al
     * último que entró. Alimenta el reporte en PDF del administrador.
     *
     * El corte es por fecha de ingreso, no por fecha de entrega: el reporte
     * responde "¿qué entró al taller en este periodo?".
     * El fin del periodo es exclusivo (< y no <=), así no se pierde ningún auto
     * recibido a última hora del último día ni se cuela el del día siguiente.
     */
    public function recibidosEntre(Periodo $periodo): array
    {
        $sentencia = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . '
             FROM v_vehiculos
             WHERE creado_en >= :inicio
               AND creado_en <  :fin
             ORDER BY creado_en'
        );
        $sentencia->execute([
            ':inicio' => $periodo->inicioSql(),
            ':fin'    => $periodo->finSql(),
        ]);

        return $sentencia->fetchAll();
    }

    /** Cuántos autos se recibieron en el periodo. Sirve para avisar antes de generar el PDF. */
    public function contarRecibidosEntre(Periodo $periodo): int
    {
        $sentencia = $this->db->prepare(
            'SELECT COUNT(*) FROM vehiculos WHERE creado_en >= :inicio AND creado_en < :fin'
        );
        $sentencia->execute([
            ':inicio' => $periodo->inicioSql(),
            ':fin'    => $periodo->finSql(),
        ]);

        return (int) $sentencia->fetchColumn();
    }

    /** Los últimos N que ingresaron y siguen en el taller (dashboard). */
    public function recientes(int $limite = 5): array
    {
        $sentencia = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . '
             FROM v_vehiculos
             WHERE area_id <> :entregado
             ORDER BY creado_en DESC
             LIMIT :limite'
        );
        $sentencia->bindValue(':entregado', Area::ENTREGADO, PDO::PARAM_INT);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * Números del dashboard en una sola consulta.
     * 'en_taller' excluye los entregados; 'terminados' son los listos para entregar.
     */
    public function resumen(): array
    {
        $sentencia = $this->db->prepare(
            'SELECT COUNT(*) FILTER (WHERE area_id <> :entregado)  AS en_taller,
                    COUNT(*) FILTER (WHERE area_id  = :terminado)  AS terminados,
                    COUNT(*) FILTER (WHERE area_id  = :entregado2) AS entregados
             FROM vehiculos'
        );
        $sentencia->execute([
            ':entregado'  => Area::ENTREGADO,
            ':terminado'  => Area::TERMINADO,
            ':entregado2' => Area::ENTREGADO,
        ]);
        $fila = $sentencia->fetch();

        return [
            'en_taller'  => (int) $fila['en_taller'],
            'terminados' => (int) $fila['terminados'],
            'entregados' => (int) $fila['entregados'],
        ];
    }

    /**
     * Cuántos autos hay en cada área, sin contar los entregados.
     * Alimenta la barra de carga del panel: [['id', 'nombre', 'total'], ...]
     */
    public function porArea(): array
    {
        $sentencia = $this->db->prepare(
            'SELECT a.id, a.nombre, COUNT(v.id) AS total
             FROM areas a
             LEFT JOIN vehiculos v ON v.area_id = a.id
             WHERE a.id <> :entregado
             GROUP BY a.id, a.nombre
             ORDER BY a.id'
        );
        $sentencia->execute([':entregado' => Area::ENTREGADO]);

        return $sentencia->fetchAll();
    }

    public function contar(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM vehiculos')->fetchColumn();
    }

    /** Traduce el formulario a parámetros SQL: vacíos opcionales → NULL. */
    private function parametros(array $datos): array
    {
        $anio     = trim((string) ($datos['anio'] ?? ''));
        $color    = trim((string) ($datos['color'] ?? ''));
        $placas   = trim((string) ($datos['placas'] ?? ''));
        $asignado = trim((string) ($datos['asignado_a'] ?? ''));

        return [
            ':cliente_nombre'   => trim((string) $datos['cliente_nombre']),
            ':cliente_telefono' => trim((string) $datos['cliente_telefono']),
            ':marca'            => trim((string) $datos['marca']),
            ':modelo'           => trim((string) $datos['modelo']),
            ':anio'             => $anio === '' ? null : (int) $anio,
            ':color'            => $color === '' ? null : $color,
            ':placas'           => $placas === '' ? null : mb_strtoupper($placas),
            ':area_id'          => (int) $datos['area_id'],
            ':asignado_a'       => $asignado === '' ? null : $asignado,
        ];
    }
}
