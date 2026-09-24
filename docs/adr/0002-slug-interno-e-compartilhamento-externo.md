# Dois canais de acesso separados: Slug interno e Compartilhamento externo

O stack legado usava um único identificador — o nome do arquivo — tanto para acesso interno quanto para o link público do viewer (`?d=nome.bpmn`), permanente e sem expiração. O próprio README do legado já registrava isso como fraqueza: "nomes óbvios podem ser adivinhados".

Na reconstrução, separamos os dois: o **Slug** é o identificador interno, legível, usado nas rotas autenticadas (Editor), mutável ao renomear o Diagrama, com o Slug anterior reservado permanentemente e redirecionando para o Diagrama atual (nunca reaproveitado). O **Compartilhamento** é um mecanismo à parte para acesso público — um link assinado, com expiração em dias (padrão 7, máximo 60) e revogável a qualquer momento pelo Autor, com uma permissão de download própria (desabilitada por padrão).

Essa separação custa mais complexidade (duas entidades em vez de uma), mas elimina a fraqueza estrutural do legado: o link público nunca mais é "só o nome do arquivo", e pode ser desativado sem afetar o acesso interno ao Diagrama.
</content>
