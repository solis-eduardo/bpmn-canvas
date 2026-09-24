# Trava de Edição otimista com transferência automática por timeout

O legado era explícito em não ter nenhuma trava de edição concorrente: "se duas pessoas salvarem o mesmo arquivo, vale o último". Isso era aceitável com um único usuário/senha compartilhado, mas com contas individuais (ADR-0001) e Colaboradores por Diagrama, colisões silenciosas passam a ser um risco real de perda de trabalho.

Introduzimos uma Trava de Edição: quem abre o Editor de um Diagrama passa a detê-la, bloqueando outras pessoas de abrir o mesmo Editor simultaneamente. Em vez de uma trava rígida (que travaria o Diagrama indefinidamente se alguém esquecer aberto), quem esbarra nela pode solicitar a trava por polling — se o detentor não responder em 30 segundos, a trava é transferida automaticamente para quem solicitou. Um Fork (cópia independente do Diagrama) fica disponível como alternativa imediata, e um Administrador pode forçar a liberação a qualquer momento como último recurso.

Optamos por esse desenho — trava otimista com timeout curto e fallback automático — em vez de (a) manter "sem trava" como no legado, que arrisca perda de trabalho, ou (b) uma trava rígida sem mecanismo de resgate, que travaria o fluxo de trabalho sempre que alguém esquecesse o Editor aberto.
</content>
