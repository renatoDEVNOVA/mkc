<?php

use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;
use App\Permission; 

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Permisos de usuarios
        $permission = new Permission();
        $permission->name = 'Listar Usuarios';
        $permission->slug = 'user.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Usuario';
        $permission->slug = 'user.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Usuario';
        $permission->slug = 'user.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Usuario';
        $permission->slug = 'user.delete';
        $permission->save();


        // Permisos de roles
        $permission = new Permission();
        $permission->name = 'Listar Roles';
        $permission->slug = 'role.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Rol';
        $permission->slug = 'role.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Rol';
        $permission->slug = 'role.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Rol';
        $permission->slug = 'role.delete';
        $permission->save();


        // Permisos de tipos de cambio
        $permission = new Permission();
        $permission->name = 'Listar TC';
        $permission->slug = 'tipo-cambio.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear TC';
        $permission->slug = 'tipo-cambio.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar TC';
        $permission->slug = 'tipo-cambio.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar TC';
        $permission->slug = 'tipo-cambio.delete';
        $permission->save();


        // Permisos de Clipping
        $permission = new Permission();
        $permission->name = 'Listar Clipping';
        $permission->slug = 'clipping.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Miembros';
        $permission->slug = 'clipping.miembro.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Agregar Miembro';
        $permission->slug = 'clipping.miembro.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Miembro';
        $permission->slug = 'clipping.miembro.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Miembro';
        $permission->slug = 'clipping.miembro.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Enviar Credenciales';
        $permission->slug = 'clipping.miembro.send-cd';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Reportes';
        $permission->slug = 'clipping.reporte.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Envios Automaticos';
        $permission->slug = 'clipping.send-auto.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Agregar Envio Automatico';
        $permission->slug = 'clipping.send-auto.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Envio Automatico';
        $permission->slug = 'clipping.send-auto.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Envio Automatico';
        $permission->slug = 'clipping.send-auto.delete';
        $permission->save();


        // Permisos de actividad de usuarios
        $permission = new Permission();
        $permission->name = 'Actividad de usuarios';
        $permission->slug = 'activity';
        $permission->save();


        // Permisos de personas
        $permission = new Permission();
        $permission->name = 'Listar Personas';
        $permission->slug = 'persona.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Descargar Personas';
        $permission->slug = 'persona.download';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Persona';
        $permission->slug = 'persona.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Persona';
        $permission->slug = 'persona.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Persona';
        $permission->slug = 'persona.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Persona';
        $permission->slug = 'persona.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Medios';
        $permission->slug = 'persona.medio.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Medio';
        $permission->slug = 'persona.medio.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Medio';
        $permission->slug = 'persona.medio.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Activar/Desactivar Medio';
        $permission->slug = 'persona.medio.activo';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Clientes';
        $permission->slug = 'persona.cliente.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Cliente';
        $permission->slug = 'persona.cliente.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Cliente';
        $permission->slug = 'persona.cliente.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Detalles PM';
        $permission->slug = 'persona.dpm.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Agregar Detalle PM';
        $permission->slug = 'persona.dpm.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Detalle PM';
        $permission->slug = 'persona.dpm.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Detalle PM';
        $permission->slug = 'persona.dpm.delete';
        $permission->save();


        // Permisos de compañias
        $permission = new Permission();
        $permission->name = 'Listar Compañias';
        $permission->slug = 'company.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Compañia';
        $permission->slug = 'company.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Compañia';
        $permission->slug = 'company.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Compañia';
        $permission->slug = 'company.delete';
        $permission->save();


        // Permisos de medios
        $permission = new Permission();
        $permission->name = 'Listar Medios';
        $permission->slug = 'medio.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Medio';
        $permission->slug = 'medio.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Medio';
        $permission->slug = 'medio.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Medio';
        $permission->slug = 'medio.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Medio';
        $permission->slug = 'medio.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Plataformas';
        $permission->slug = 'medio.plataforma.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Plataforma';
        $permission->slug = 'medio.plataforma.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Plataforma';
        $permission->slug = 'medio.plataforma.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Programas';
        $permission->slug = 'medio.programa.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Programa';
        $permission->slug = 'medio.programa.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Programa';
        $permission->slug = 'medio.programa.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Agregar Plataforma al Programa';
        $permission->slug = 'medio.programa-plataforma.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Plataforma del Programa';
        $permission->slug = 'medio.programa-plataforma.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Plataforma del Programa';
        $permission->slug = 'medio.programa-plataforma.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Contactos';
        $permission->slug = 'medio.contacto.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Contacto';
        $permission->slug = 'medio.contacto.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Contacto';
        $permission->slug = 'medio.contacto.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Activar/Desactivar Contacto';
        $permission->slug = 'medio.contacto.activo';
        $permission->save();


        // Permisos de clientes
        $permission = new Permission();
        $permission->name = 'Listar Clientes';
        $permission->slug = 'cliente.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Cliente';
        $permission->slug = 'cliente.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Cliente';
        $permission->slug = 'cliente.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Cliente';
        $permission->slug = 'cliente.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Cliente';
        $permission->slug = 'cliente.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Encargados';
        $permission->slug = 'cliente.encargado.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Encargado';
        $permission->slug = 'cliente.encargado.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Encargado';
        $permission->slug = 'cliente.encargado.delete';
        $permission->save();


        // Permisos de campañas
        $permission = new Permission();
        $permission->name = 'Listar Campañas';
        $permission->slug = 'campaign.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Descargar Campañas';
        $permission->slug = 'campaign.download';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Campaña';
        $permission->slug = 'campaign.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Campaña';
        $permission->slug = 'campaign.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Campaña';
        $permission->slug = 'campaign.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Campaña';
        $permission->slug = 'campaign.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Voceros';
        $permission->slug = 'campaign.vocero.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Vocero';
        $permission->slug = 'campaign.vocero.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Vocero';
        $permission->slug = 'campaign.vocero.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Agentes';
        $permission->slug = 'campaign.agente.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Agente';
        $permission->slug = 'campaign.agente.delete';
        $permission->save();


        // Permisos de nota de prensa
        $permission = new Permission();
        $permission->name = 'Listar Notas de Prensa';
        $permission->slug = 'nota-prensa.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Nota de Prensa';
        $permission->slug = 'nota-prensa.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Nota de Prensa';
        $permission->slug = 'nota-prensa.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Nota de Prensa';
        $permission->slug = 'nota-prensa.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Nota de Prensa';
        $permission->slug = 'nota-prensa.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Planes de Medios';
        $permission->slug = 'nota-prensa.plan-medio.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Plan de Medios';
        $permission->slug = 'nota-prensa.plan-medio.delete';
        $permission->save();


        // Permisos de plan de medios
        $permission = new Permission();
        $permission->name = 'Listar Planes de Medios';
        $permission->slug = 'plan-medio.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Crear Plan de Medios';
        $permission->slug = 'plan-medio.create';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Plan de Medios';
        $permission->slug = 'plan-medio.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Plan de Medios';
        $permission->slug = 'plan-medio.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Duplicar Plan de Medios';
        $permission->slug = 'plan-medio.duplicate';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Ver Detalles PM';
        $permission->slug = 'plan-medio.dpm.view';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Agregar Detalle PM';
        $permission->slug = 'plan-medio.dpm.add';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Editar Detalle PM';
        $permission->slug = 'plan-medio.dpm.edit';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar Detalle PM';
        $permission->slug = 'plan-medio.dpm.delete';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Vincular Detalle PM';
        $permission->slug = 'plan-medio.dpm.associate';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Desvincular Detalle PM';
        $permission->slug = 'plan-medio.dpm.dissociate';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Enviar al SE';
        $permission->slug = 'plan-medio.dpm.sistema-experto';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Enviar Nota de Prensa';
        $permission->slug = 'plan-medio.dpm.send-np';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Asignar Voceros';
        $permission->slug = 'plan-medio.dpm.add-vocero';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Gestionar Muestras';
        $permission->slug = 'plan-medio.dpm.manage-muestra';
        $permission->save();


        // Permisos de reportes
        $permission = new Permission();
        $permission->name = 'Generar Reporte';
        $permission->slug = 'reporte.generar-reporte';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Impactos por Plataformas';
        $permission->slug = 'reporte.impactos';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Grafico Impactos por Plataformas';
        $permission->slug = 'reporte.grafico-impactos';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Estado por Campaña o Vocero';
        $permission->slug = 'reporte.estado';
        $permission->save();


        // Sistema experto
        $permission = new Permission();
        $permission->name = 'Analisis Estrategico';
        $permission->slug = 'sistema-experto.analisis';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Listar Casos';
        $permission->slug = 'sistema-experto.casos.list';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Enviar al SE';
        $permission->slug = 'sistema-experto.casos.send';
        $permission->save();

        $permission = new Permission();
        $permission->name = 'Eliminar del SE';
        $permission->slug = 'sistema-experto.casos.delete';
        $permission->save();

        // Mantenimiento
        $permission = new Permission();
        $permission->name = 'Mantenimiento';
        $permission->slug = 'mantenimiento';
        $permission->save();

    }
}
