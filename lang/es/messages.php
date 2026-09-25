<?php

return [

    // Auth
    'log_in' => 'Iniciar sesión',
    'log_out' => 'Cerrar sesión',
    'email' => 'Correo electrónico',
    'password' => 'Contraseña',
    'invalid_credentials' => 'Credenciales inválidas.',
    'keycloak_login_failed' => 'No se pudo iniciar sesión con Keycloak. Inténtalo de nuevo.',
    'log_in_locally' => 'Iniciar sesión con correo y contraseña',
    'log_in_with_keycloak' => 'Iniciar sesión con Keycloak',
    'or' => 'o',

    // Navigation
    'diagrams' => 'Diagramas',
    'users' => 'Usuarios',

    // Diagram list
    'diagram_name' => 'Nombre del diagrama',
    'start_event_label' => 'Inicio',
    'create_diagram' => 'Crear diagrama',
    'no_diagrams_yet' => 'Todavía no se ha creado ningún diagrama.',
    'author_label' => '(autor: :name)',
    'diagram_owner_label' => '(propietario: :name)',
    'last_modified_label' => 'Última modificación :time',
    'being_edited_by' => '— en edición por :name',

    // Editor
    'bpmn_editor' => 'Editor BPMN',
    'unsaved_changes' => 'Cambios sin guardar',
    'save' => 'Guardar',
    'delete' => 'Eliminar',
    'zoom_in' => 'Acercar',
    'zoom_out' => 'Alejar',
    'fit_to_screen' => 'Ajustar a la pantalla',
    'lock_not_held' => 'No tienes el bloqueo de este diagrama.',
    'back_to_diagrams' => 'Volver a Diagramas',
    'version_history' => 'Historial de versiones',
    'collaborators' => 'Colaboradores',

    // Historial de versiones y comparación
    'history' => 'Historial',
    'versions' => 'Versiones',
    'exit_comparison' => 'Salir de la comparación',
    'diff_added' => 'Añadido',
    'diff_removed' => 'Eliminado',
    'diff_changed' => 'Modificado',
    'diff_removed_elements' => 'Elementos eliminados (no están en el canvas actual)',
    'restore' => 'Restaurar',
    'restore_version_confirm' => '¿Restaurar esta versión? Esto crea una nueva versión encima del historial.',
    'version_restored' => 'Versión restaurada.',
    'version_restore_failed' => 'No se pudo restaurar esta versión.',

    // Locked diagram
    'being_edited' => 'En edición',
    'currently_being_edited_by' => 'Este diagrama está siendo editado por :name en este momento. No puedes abrir el Editor hasta que se libere el Bloqueo.',
    'create_a_copy_to_continue' => 'Crear una copia (Fork) para continuar ahora',
    'locked_since' => 'Bloqueado :time',
    'request_the_lock' => 'Solicitar el Bloqueo',
    'lock_transfers_in' => 'El Bloqueo se transfiere en :seconds s…',
    'lock_requested_by_notice' => ':name ha solicitado el Bloqueo de este diagrama.',

    // Gestión de usuarios
    'users_heading' => 'Usuarios',
    'name' => 'Nombre',
    'login_method' => 'Método de inicio de sesión',
    'login_method_local' => 'Local',
    'login_method_keycloak' => 'Keycloak',
    'administrator_status' => 'Administrador',
    'administrator_via_keycloak' => 'Administrador (vía Keycloak)',
    'not_administrator' => 'No es administrador',
    'grant_administrator' => 'Conceder Administrador',
    'revoke_administrator' => 'Revocar Administrador',
    'not_revocable_via_keycloak' => 'Concedido por Keycloak — no se puede revocar aquí.',
    'create_local_account' => 'Crear cuenta local',
    'no_users_yet' => 'Todavía no hay usuarios.',

    // Public share viewer
    'download' => 'Descargar',
    'png_image' => 'Imagen PNG',
    'svg_image' => 'Imagen SVG',
    'bpmn_file' => 'Archivo BPMN (.bpmn)',
    'loading' => 'Cargando…',
    'invalid_link' => 'Enlace no válido',
    'invalid_share_link' => 'Este enlace de compartición ha expirado o fue revocado por el Autor. Pide un nuevo enlace a quien compartió este Diagrama contigo.',

];
