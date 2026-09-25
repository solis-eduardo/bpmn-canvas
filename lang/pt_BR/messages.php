<?php

return [

    // Auth
    'log_in' => 'Entrar',
    'log_out' => 'Sair',
    'email' => 'E-mail',
    'password' => 'Senha',
    'invalid_credentials' => 'Credenciais inválidas.',
    'keycloak_login_failed' => 'Não foi possível entrar com o Keycloak. Tente novamente.',
    'log_in_locally' => 'Entrar com e-mail e senha',
    'log_in_with_keycloak' => 'Entrar com Keycloak',
    'or' => 'ou',

    // Navigation
    'diagrams' => 'Diagramas',
    'users' => 'Usuários',

    // Diagram list
    'diagram_name' => 'Nome do diagrama',
    'start_event_label' => 'Início',
    'create_diagram' => 'Criar diagrama',
    'no_diagrams_yet' => 'Nenhum diagrama criado ainda.',
    'author_label' => '(autor: :name)',
    'diagram_owner_label' => '(dono: :name)',
    'last_modified_label' => 'Última modificação :time',
    'being_edited_by' => '— em edição por :name',

    // Editor
    'bpmn_editor' => 'Editor BPMN',
    'unsaved_changes' => 'Alterações não salvas',
    'save' => 'Salvar',
    'delete' => 'Excluir',
    'zoom_in' => 'Aproximar',
    'zoom_out' => 'Afastar',
    'fit_to_screen' => 'Ajustar à tela',
    'lock_not_held' => 'Você não detém a trava deste diagrama.',
    'back_to_diagrams' => 'Voltar para Diagramas',
    'version_history' => 'Histórico de versões',
    'collaborators' => 'Colaboradores',

    // Histórico de versões e comparação
    'history' => 'Histórico',
    'versions' => 'Versões',
    'exit_comparison' => 'Sair da comparação',
    'diff_added' => 'Adicionado',
    'diff_removed' => 'Removido',
    'diff_changed' => 'Alterado',
    'diff_removed_elements' => 'Elementos removidos (não estão no canvas atual)',
    'restore' => 'Restaurar',
    'restore_version_confirm' => 'Restaurar esta versão? Isso cria uma nova versão em cima do histórico.',
    'version_restored' => 'Versão restaurada.',
    'version_restore_failed' => 'Não foi possível restaurar esta versão.',

    // Locked diagram
    'being_edited' => 'Em edição',
    'currently_being_edited_by' => 'Este diagrama está sendo editado por :name no momento. Você não pode abrir o Editor até que a Trava seja liberada.',
    'create_a_copy_to_continue' => 'Criar uma cópia (Fork) para continuar agora',
    'locked_since' => 'Travado :time',
    'request_the_lock' => 'Solicitar a Trava',
    'lock_transfers_in' => 'A Trava é transferida em :seconds s…',
    'lock_requested_by_notice' => ':name solicitou a Trava deste diagrama.',

    // Gestão de usuários
    'users_heading' => 'Usuários',
    'name' => 'Nome',
    'login_method' => 'Método de login',
    'login_method_local' => 'Local',
    'login_method_keycloak' => 'Keycloak',
    'administrator_status' => 'Administrador',
    'administrator_via_keycloak' => 'Administrador (via Keycloak)',
    'not_administrator' => 'Não é administrador',
    'grant_administrator' => 'Conceder Administrador',
    'revoke_administrator' => 'Revogar Administrador',
    'not_revocable_via_keycloak' => 'Concedido pelo Keycloak — não pode ser revogado aqui.',
    'create_local_account' => 'Criar conta local',
    'no_users_yet' => 'Nenhum usuário ainda.',

    // Public share viewer
    'download' => 'Baixar',
    'png_image' => 'Imagem PNG',
    'svg_image' => 'Imagem SVG',
    'bpmn_file' => 'Arquivo BPMN (.bpmn)',
    'loading' => 'Carregando…',
    'invalid_link' => 'Link inválido',
    'invalid_share_link' => 'Este link de compartilhamento expirou ou foi revogado pelo Autor. Peça um novo link a quem compartilhou este Diagrama com você.',

];
