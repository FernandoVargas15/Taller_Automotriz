<?php
declare(strict_types=1);

// MODELO Bitacora — la línea de tiempo de cada auto: reportes con foto opcional

final class Bitacora
{
    public const DESCRIPCION_MIN = 5;
    public const DESCRIPCION_MAX = 1000;
    public const FOTO_MAX_BYTES  = 5 * 1024 * 1024;   // 5 MB

    /** Carpeta de fotos, relativa a public/. Lo que se guarda en BD es "subidas/bitacora/xxx.jpg". */
    public const CARPETA_FOTOS = 'subidas/bitacora';

    // Tipo MIME real (lo detecta PHP, no la extensión que mande el navegador) → extensión con la que se guarda
    private const FOTO_TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia()->conexion();
    }

    // Validacion

    /**
     * $foto es la entrada de $_FILES (o null si el formulario no la mandó).
     * Devuelve ['campo' => 'mensaje']; vacío si todo está bien.
     */
    public function validar(array $datos, ?array $foto): array
    {
        $errores = [];

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $areaId      = (int) ($datos['area_id'] ?? 0);

        if ($descripcion === '') {
            $errores['descripcion'] = 'Escribe qué se hizo o qué se encontró.';
        } elseif (mb_strlen($descripcion) < self::DESCRIPCION_MIN) {
            $errores['descripcion'] = 'El reporte debe tener al menos ' . self::DESCRIPCION_MIN . ' caracteres.';
        } elseif (mb_strlen($descripcion) > self::DESCRIPCION_MAX) {
            $errores['descripcion'] = 'El reporte no puede pasar de ' . self::DESCRIPCION_MAX . ' caracteres.';
        }

        if (!(new Area())->existe($areaId)) {
            $errores['area_id'] = 'Selecciona un área válida.';
        }

        if ($foto !== null && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errorFoto = $this->validarFoto($foto);

            if ($errorFoto !== null) {
                $errores['foto'] = $errorFoto;
            }
        }

        return $errores;
    }

    /** Mensaje de error de la foto, o null si es aceptable. */
    private function validarFoto(array $foto): ?string
    {
        if ($foto['error'] === UPLOAD_ERR_INI_SIZE || $foto['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'La foto es demasiado grande (máximo 5 MB).';
        }

        if ($foto['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($foto['tmp_name'])) {
            return 'No se pudo recibir la foto. Inténtalo de nuevo.';
        }

        if ($foto['size'] > self::FOTO_MAX_BYTES) {
            return 'La foto es demasiado grande (máximo 5 MB).';
        }

        if (!isset(self::FOTO_TIPOS[self::tipoMime($foto['tmp_name'])])) {
            return 'Solo se aceptan fotos JPG, PNG o WebP.';
        }

        return null;
    }

    // Consultas

    /** Guarda el reporte (y su foto, si viene) y devuelve el UUID. Espera datos ya validados. */
    public function crear(string $vehiculoId, string $usuarioId, int $areaId, string $descripcion, ?array $foto): string
    {
        $rutaFoto = null;

        if ($foto !== null && $foto['error'] === UPLOAD_ERR_OK) {
            $rutaFoto = $this->guardarFoto($foto);
        }

        $sql = 'INSERT INTO bitacora (vehiculo_id, usuario_id, area_id, descripcion, foto)
                VALUES (:vehiculo_id, :usuario_id, :area_id, :descripcion, :foto)
                RETURNING id';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute([
            ':vehiculo_id' => $vehiculoId,
            ':usuario_id'  => $usuarioId,
            ':area_id'     => $areaId,
            ':descripcion' => trim($descripcion),
            ':foto'        => $rutaFoto,
        ]);

        return (string) $sentencia->fetchColumn();
    }

    /** Entradas de un auto, la más reciente primero. */
    public function porVehiculo(string $vehiculoId): array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, vehiculo_id, usuario_id, usuario_nombre, usuario_rol,
                    area_id, area_nombre, descripcion, foto, creado_en
             FROM v_bitacora
             WHERE vehiculo_id = :vehiculo_id
             ORDER BY creado_en DESC'
        );
        $sentencia->execute([':vehiculo_id' => $vehiculoId]);

        return $sentencia->fetchAll();
    }

    /** Cuántas entradas con foto tiene un auto (la regla de evidencia se apoya en esto). */
    public function contarFotos(string $vehiculoId): int
    {
        $sentencia = $this->db->prepare(
            'SELECT COUNT(*) FROM bitacora WHERE vehiculo_id = :vehiculo_id AND foto IS NOT NULL'
        );
        $sentencia->execute([':vehiculo_id' => $vehiculoId]);

        return (int) $sentencia->fetchColumn();
    }

    public function buscar(string $id): ?array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, vehiculo_id, foto FROM bitacora WHERE id = :id'
        );
        $sentencia->execute([':id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** Borra una entrada y su foto del disco. Solo el administrador llega aquí. */
    public function eliminar(string $id): void
    {
        $entrada = $this->buscar($id);

        if ($entrada === null) {
            return;
        }

        $this->db->prepare('DELETE FROM bitacora WHERE id = :id')->execute([':id' => $id]);
        $this->borrarArchivo($entrada['foto']);
    }

    /**
     * Quita del disco las fotos de un auto. Se llama antes de eliminar el vehículo:
     * las filas las borra la BD en cascada, pero los archivos no.
     */
    public function borrarFotosDeVehiculo(string $vehiculoId): void
    {
        $sentencia = $this->db->prepare(
            'SELECT foto FROM bitacora WHERE vehiculo_id = :vehiculo_id AND foto IS NOT NULL'
        );
        $sentencia->execute([':vehiculo_id' => $vehiculoId]);

        foreach ($sentencia->fetchAll(PDO::FETCH_COLUMN) as $foto) {
            $this->borrarArchivo($foto);
        }
    }

    // Archivos

    /** Mueve la foto subida a public/subidas/bitacora con un nombre aleatorio; devuelve la ruta relativa. */
    private function guardarFoto(array $foto): string
    {
        $extension = self::FOTO_TIPOS[self::tipoMime($foto['tmp_name'])];
        $nombre    = bin2hex(random_bytes(16)) . '.' . $extension;
        $carpeta   = RUTA_RAIZ . '/public/' . self::CARPETA_FOTOS;

        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de fotos: ' . $carpeta);
        }

        if (!move_uploaded_file($foto['tmp_name'], $carpeta . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar la foto.');
        }

        return self::CARPETA_FOTOS . '/' . $nombre;
    }

    /** Borra el archivo si existe y está dentro de la carpeta de fotos (nunca fuera). */
    private function borrarArchivo(?string $rutaRelativa): void
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return;
        }

        $carpeta = realpath(RUTA_RAIZ . '/public/' . self::CARPETA_FOTOS);
        $archivo = realpath(RUTA_RAIZ . '/public/' . $rutaRelativa);

        if ($carpeta !== false && $archivo !== false && str_starts_with($archivo, $carpeta . '/')) {
            @unlink($archivo);
        }
    }

    private static function tipoMime(string $rutaTemporal): string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

        return $mime === false ? '' : $mime;
    }
}
