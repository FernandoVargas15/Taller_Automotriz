<?php
declare(strict_types=1);

/**
 * Raíz de la app: manda a cada rol a su panel.
 * Sin sesión, requerirSesion() lleva al login; el cliente usa /consulta.
 */
final class InicioController extends Controlador
{
    public function index(): void
    {
        $this->requerirSesion();

        match (Sesion::rolId()) {
            Rol::ADMINISTRADOR          => $this->redirigir('/admin'),      // panel con las cifras
            Rol::ASESOR                 => $this->redirigir('/vehiculos'),  // control de vehículos
            Rol::MECANICO,
            Rol::HOJALATERO             => $this->redirigir('/taller'),     // sus autos asignados
            // Los cuatro roles del catálogo tienen panel; un rol fuera de la lista
            // solo puede venir de datos inconsistentes, así que no se le deja dentro.
            default                     => $this->sinPanel(),
        };
    }

    private function sinPanel(): never
    {
        Sesion::cerrar();
        $this->avisar('error', 'Tu rol no tiene un panel asignado. Habla con el administrador.');
        $this->redirigir('/login');
    }
}
