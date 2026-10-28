# Evolução da plataforma: guia de estudo

> A história da Loja Demo contada pelos commits: de 06/10/2026 a 28/10/2026, 250 commits e 32 pull requests. Para cada entrega, o problema, a solução aplicada, um exemplo do código, as alternativas com vantagens e desvantagens e o sinal que pede a mesma mudança em outro sistema. No fim, como perceber que uma arquitetura precisa evoluir, um roteiro para escalar a milhões de requisições, as práticas de disponibilidade e um plano de estudo.
>
> Última atualização: 28/10/2026 (PR [#32](https://github.com/diashelter/laravel-module-ecomerce/pull/32), HEL-17, o modelo de leitura da vitrine).
>
> Fontes: o histórico do git, os designs em [`.design/`](../.design/), os planos e as verificações em [`.specs/features/`](../.specs/features/), a [análise de domínio](domain-analysis.md) e o [README](../README.md). Os números citados foram medidos neste projeto (contagens no repositório e rodadas do k6 numa máquina de desenvolvimento): eles servem para comparar uma versão com a outra e nunca valem como capacidade de produção.

## Sumário

- [Como usar este guia](#como-usar-este-guia)
- [Parte 1. A plataforma hoje, em uma página](#parte-1-a-plataforma-hoje-em-uma-página)
- [Parte 2. A linha do tempo](#parte-2-a-linha-do-tempo)
- [Parte 3. As entregas, uma a uma](#parte-3-as-entregas-uma-a-uma)
  1. [O ponto de partida: do código por camada ao monólito modular](#1-o-ponto-de-partida-do-código-por-camada-ao-monólito-modular)
  2. [A rede de segurança: CI, processo e provas](#2-a-rede-de-segurança-ci-processo-e-provas)
  3. [Modelagem tática: dinheiro, value objects e listas tipadas](#3-modelagem-tática-dinheiro-value-objects-e-listas-tipadas)
  4. [Pagamento atrás de uma porta](#4-pagamento-atrás-de-uma-porta)
  5. [Identidade: equipe e clientes em contas separadas](#5-identidade-equipe-e-clientes-em-contas-separadas)
  6. [Endereços, frete e a cópia no pedido](#6-endereços-frete-e-a-cópia-no-pedido)
  7. [Fachadas e vocabulário público dos módulos](#7-fachadas-e-vocabulário-público-dos-módulos)
  8. [Ids públicos: UUID v7, o dono na busca e nenhuma rota resolvendo registro](#8-ids-públicos-uuid-v7-o-dono-na-busca-e-nenhuma-rota-resolvendo-registro)
  9. [Outbox transacional: nenhum evento perdido](#9-outbox-transacional-nenhum-evento-perdido)
  10. [O lado de leitura com consultas](#10-o-lado-de-leitura-com-consultas)
  11. [A borda: limite de requisições sem brechas](#11-a-borda-limite-de-requisições-sem-brechas)
  12. [Medir antes de otimizar: testes de carga com k6](#12-medir-antes-de-otimizar-testes-de-carga-com-k6)
  13. [Um ambiente de testes por checkout](#13-um-ambiente-de-testes-por-checkout)
  14. [Mensageria plugável: feature flag, Horizon e RabbitMQ](#14-mensageria-plugável-feature-flag-horizon-e-rabbitmq)
  15. [Módulos como pacotes, e a regressão que a carga pegou](#15-módulos-como-pacotes-e-a-regressão-que-a-carga-pegou)
  16. [Idempotência dos consumidores](#16-idempotência-dos-consumidores)
  17. [Módulos isolados: a porta do consumidor e o 503](#17-módulos-isolados-a-porta-do-consumidor-e-o-503)
  18. [CQRS com banco próprio: a vitrine no MongoDB](#18-cqrs-com-banco-próprio-a-vitrine-no-mongodb)
  19. [O que já está desenhado e ainda não foi feito](#19-o-que-já-está-desenhado-e-ainda-não-foi-feito)
- [Parte 4. Como perceber que a arquitetura precisa evoluir](#parte-4-como-perceber-que-a-arquitetura-precisa-evoluir)
- [Parte 5. Escalar para milhões de requisições](#parte-5-escalar-para-milhões-de-requisições)
- [Parte 6. Disponibilidade: continuar no ar quando algo falha](#parte-6-disponibilidade-continuar-no-ar-quando-algo-falha)
- [Parte 7. Roteiro de estudo e de carreira](#parte-7-roteiro-de-estudo-e-de-carreira)
- [Glossário](#glossário)
- [Referências](#referências)

---

## Como usar este guia

Cada capítulo da Parte 3 segue a mesma ficha:

| Seção | O que responde |
| --- | --- |
| **Situação e problema** | O que doía, ou o que se queria aprender, e o número que decidiu, quando havia um |
| **O que foi feito** | A solução, em poucas linhas |
| **Exemplo** | Um trecho real do código, encurtado, com o link para o arquivo |
| **Por que assim** | As decisões-chave do design |
| **Alternativas** | O que mais poderia ser feito, com vantagens, desvantagens e quando cada uma ganharia |
| **Sinal para aplicar** | Como reconhecer o mesmo problema em outro sistema |
| **Para praticar** | Um exercício neste repositório |

Para ver o que um commit mudou, sem sair do terminal:

```bash
git log --oneline --merges
```

```bash
git show --stat 7588917
```

```bash
git log --follow -p -- backend/app-modules/messaging/src/Core/OutboxRelay.php
```

Três ideias atravessam o projeto inteiro. Vale lê-las antes dos capítulos, porque são elas que separam "aplicar um padrão" de "saber quando aplicá-lo":

1. **Construção ou dor medida.** Cada design diz se a mudança responde a um problema observado (um defeito, um número medido) ou se é uma construção: algo feito para aprender um padrão ou para preparar o terreno da próxima entrega. O outbox nasceu de um defeito real; o modelo de leitura no MongoDB nasceu como estudo, com a vitrine em 24 ms de p95. Dizer qual dos dois é o caso, com o número na mão, é uma habilidade de quem decide arquitetura.
2. **Porta de mão única.** Cada design nomeia o que é caro de desfazer: o tipo do id, o formato de um evento publicado, a topologia de um broker, o nome de uma migration já executada. O cuidado vai para essas portas; o resto pode mudar rápido. É a distinção que a Amazon chama de decisões tipo 1 (irreversíveis) e tipo 2 (reversíveis).
3. **"A alternativa mais pesada só ganha quando..."** Todo design descreve a solução mais pesada que foi recusada e o gatilho que a justificaria (um gateway real, um segundo banco, um consumidor sem chave natural, uma leitura lenta medida). Saber o próximo passo e o sinal que o pede é o que permite evoluir sem construir antes da hora.

---

## Parte 1. A plataforma hoje, em uma página

Um e-commerce pequeno e completo: catálogo, estoque, carrinho, checkout com frete por UF, pagamento e entrega simulados, área do cliente e painel administrativo. O backend é um **monólito modular** em Laravel 13: um único deploy, com um pacote Composer por contexto de negócio. O frontend é um SPA em Vue 3.

```txt
Navegador ─► Nginx (o gateway)
               ├─ GET e HEAD da vitrine ─► api-read ─► MongoDB (modelo de leitura da vitrine)
               ├─ o resto de /api ───────► api (Laravel) ─► PostgreSQL (escrita + outbox_messages)
               └─ /* ────────────────────► frontend (Vite, Vue 3)

PostgreSQL (outbox_messages) ─► outbox-relay ─► flag event-transport ─► Redis (Horizon) ou RabbitMQ
                                                                           └─► consumidores
                                                                                ├─ Ordering e Fulfillment: o ciclo do pedido
                                                                                └─ Catalog: o projetor da vitrine (grava no MongoDB)
```

| Módulo | Tipo | Responsabilidade |
| --- | --- | --- |
| Ordering | Core | Carrinho, checkout e o ciclo de vida do pedido (o único que muda o status) |
| Catalog | Supporting | Produtos, categorias, a regra "pode ser comprado" e o modelo de leitura da vitrine |
| Inventory | Supporting | Estoque, nunca negativo |
| Payment | Supporting | Cobrança por uma porta de gateway (fake) e as tentativas de pagamento |
| Fulfillment | Supporting | Frete por UF, data prevista e a entrega simulada |
| Identity | Generic | As contas da equipe e dos clientes, os logins e os papéis |
| Customers | Supporting | "Minha conta", o caderno de endereços e a tela de clientes do admin |
| Backoffice | Leitura | O dashboard, montado pelos contratos de métricas |
| Shared, Messaging, Integration | Infraestrutura | O kernel (erros, ids, limite de requisições), a mensageria (outbox, relay, transportes) e a composição entre módulos |

O pedido atravessa três módulos que só conversam por eventos, gravados no outbox na mesma transação da mudança:

```txt
POST /api/orders ─► OrderPlaced ─► Ordering: placed → awaiting_payment
POST /api/orders/{order}/payment ─► gateway ─► PaymentApproved ─► Ordering: → payment_approved ─► OrderPaid
OrderPaid ─► Fulfillment: data prevista (DeliveryScheduled) e entrega simulada (OrderDelivered) ─► Ordering: → delivered
```

Os detalhes de cada peça estão no [README](../README.md); as fronteiras e os contextos, na [análise de domínio](domain-analysis.md).

---

## Parte 2. A linha do tempo

A ordem importa tanto quanto o conteúdo. Repare no desenho: primeiro as fronteiras e os formatos (o que é caro mudar depois), depois a confiabilidade, a leitura, a medição, o transporte, o empacotamento e o isolamento. Cada entrega foi construída sobre a anterior, e várias foram feitas **antes** de outras de propósito: o UUID antes do outbox, para que os eventos já nascessem com o id definitivo; as fachadas antes do outbox e do CQRS, para que existisse uma superfície pública estável sobre a qual construir.

| Quando | Entrega | O problema | O que mudou | Conceitos |
| --- | --- | --- | --- | --- |
| antes de 06/10 | o plano de 5 passos da análise de domínio | O código era organizado por camada técnica (`app/Models`, `app/Services`...) e misturava 7 contextos | A regra de disponibilidade centralizada, `Customer` separado de `User`, Payment e Fulfillment separados por eventos, contratos do Inventory e a migração para `app/Modules` | DDD estratégico, contextos delimitados |
| 06/10 | commit inicial `37e9296` | (o resultado do plano) | 9 módulos em `app/Modules` e o `ModuleBoundariesTest` | Monólito modular, teste de arquitetura |
| 06/10 | [#1](https://github.com/diashelter/laravel-module-ecomerce/pull/1) | O Catalog importava o `OrderItem` do Ordering para bloquear a exclusão de produto | O contrato `ProductOrderHistory`, definido pelo Catalog e implementado pelo Ordering | Inversão de dependência |
| 06/10 | [#2](https://github.com/diashelter/laravel-module-ecomerce/pull/2) | Nada conferia estilo e testes a cada mudança | GitHub Actions com o mesmo Docker Compose e o mesmo Makefile do ambiente local | Integração contínua, paridade de ambientes |
| 06/10 | [#3](https://github.com/diashelter/laravel-module-ecomerce/pull/3) | Sem um processo repetível do problema à verificação | As skills de spec, revisão e arquitetura | Desenvolvimento guiado por especificação |
| 06/10 | [#4](https://github.com/diashelter/laravel-module-ecomerce/pull/4) | Dinheiro em `decimal` e em texto, somado com `BcMath` | Centavos inteiros do banco à tela | Dinheiro na menor unidade |
| 06/10 | [#5](https://github.com/diashelter/laravel-module-ecomerce/pull/5) | Regras de e-mail e senha copiadas em vários formulários; `Ana@x.com` e `ana@x.com` viravam duas contas | Value objects `Email` e `Password` e um `CHECK` no banco | Value object, invariante |
| 06/10 | [#6](https://github.com/diashelter/laravel-module-ecomerce/pull/6), [#8](https://github.com/diashelter/laravel-module-ecomerce/pull/8) | Listas de domínio como `array` sem tipo; totais somados ao lado das linhas | Listas tipadas e imutáveis | Coleções tipadas |
| 07/10 | [#9](https://github.com/diashelter/laravel-module-ecomerce/pull/9) | Todo pagamento era aprovado, e o Payment não tinha modelo | A porta `PaymentGateway`, o gateway fake, a tabela `payments` e o `402` | Portas e adaptadores, idempotência pelo banco |
| 07/10 | [#10](https://github.com/diashelter/laravel-module-ecomerce/pull/10) | Equipe e clientes na mesma tabela, sem papel de suporte | Tabelas e guards separados, papéis `admin` e `support` | Autenticação por área, controle de acesso por papel |
| 07/10 | [#11](https://github.com/diashelter/laravel-module-ecomerce/pull/11) | O pedido não tinha destino nem frete | O caderno de endereços, o frete por UF, a cópia do endereço no pedido e a data prevista | Snapshot, contrato definido pelo consumidor |
| 09/10 | [#12](https://github.com/diashelter/laravel-module-ecomerce/pull/12) (HEL-7) | 90 de 102 referências entre módulos entravam nas partes internas | `Contracts` e `Events` como a fachada; o teste vira uma lista do que é permitido | Encapsulamento de módulo |
| 09/10 | [#13](https://github.com/diashelter/laravel-module-ecomerce/pull/13) (HEL-10) | DTOs, value objects, enums e `Http` de um módulo usados por outros | O vocabulário público calculado das assinaturas; o `Http` privado | Superfície pública mínima |
| 15/10 | [#14](https://github.com/diashelter/laravel-module-ecomerce/pull/14) (HEL-11) | O id sequencial vazava o volume de pedidos; o `403` confirmava que o registro existia | UUID v7, o dono na busca (`404`), nenhuma rota resolvendo registro e o código do pedido | Enumeração, IDOR |
| 16/10 | [#15](https://github.com/diashelter/laravel-module-ecomerce/pull/15) (HEL-9) | Um evento perdido na fila travava o pedido para sempre | O outbox transacional, o relay e a porta `EventTransport` | Outbox, entrega ao menos uma vez |
| 16/10 | [#16](https://github.com/diashelter/laravel-module-ecomerce/pull/16) (HEL-6) | Leituras entre módulos por relações do Eloquent; 9 exceções no teste | Consultas (`Queries/`) e contratos de leitura; a regra de disponibilidade no Catalog | CQRS no código |
| 16/10 | [#17](https://github.com/diashelter/laravel-module-ecomerce/pull/17) | O limite de requisições confiava no `X-Forwarded-For` enviado pelo cliente | Nenhum proxy confiável: o IP é o da conexão | Proxies confiáveis |
| 16/10 | [#18](https://github.com/diashelter/laravel-module-ecomerce/pull/18) | As rotas com limite dividiam um contador por cliente | Um contador nomeado por rota | Chave do limitador |
| 16/10 | [#19](https://github.com/diashelter/laravel-module-ecomerce/pull/19) (HEL-14) | Ninguém sabia quanto a API aguentava | k6: pilha de carga própria, 8 perfis, linha de base e smoke no CI | Teste de carga, linha de base |
| 16/10 | [#20](https://github.com/diashelter/laravel-module-ecomerce/pull/20) | Numa rajada simultânea, todas as requisições passavam do limite | Contar antes de decidir, com um incremento atômico em Lua | Condição de corrida, atomicidade |
| 17/10 | [#21](https://github.com/diashelter/laravel-module-ecomerce/pull/21) a [#23](https://github.com/diashelter/laravel-module-ecomerce/pull/23) | O harness do k6 e um teste de rajada podiam dar resultado falso | Provas do próprio harness e a sonda do teste em `/api` | Testar o teste |
| 17/10 | [#24](https://github.com/diashelter/laravel-module-ecomerce/pull/24), [#25](https://github.com/diashelter/laravel-module-ecomerce/pull/25) | Duas suítes em worktrees diferentes quebravam uma à outra | Um banco de testes e alvos do Makefile por checkout | Isolamento de testes |
| 19/10 | [#26](https://github.com/diashelter/laravel-module-ecomerce/pull/26) (HEL-13) | A porta de transporte nunca tinha recebido uma segunda implementação | A flag `event-transport`, o RabbitMQ, o Horizon e as filas de mortas | Feature flag, broker, DLQ |
| 20/10 | [#27](https://github.com/diashelter/laravel-module-ecomerce/pull/27) (HEL-12) | Criar um módulo exigia editar 5 lugares globais; o `Shared` virou depósito | Um pacote Composer por módulo, as camadas `Core`, `Persistence` e `Http` e o módulo `Messaging` | Pacote por funcionalidade |
| 20/10 | [#28](https://github.com/diashelter/laravel-module-ecomerce/pull/28) (HEL-12) | Rotas e migrations centralizadas; a carga mostrou o p95 da vitrine 10 vezes pior | Rotas e migrations no módulo e o cache de módulos | Regressão pega por teste de carga |
| 20/10 | [#29](https://github.com/diashelter/laravel-module-ecomerce/pull/29) (HEL-16) | A reentrega do `OrderPaid` multiplicava eventos e jobs | A tabela `deliveries` como chave natural e um teste de reentrega por consumidor | Idempotência |
| 21/10 | [#30](https://github.com/diashelter/laravel-module-ecomerce/pull/30) | Um módulo não rodava sem os outros | A porta do consumidor (`OrdersToPay`), o pacote `Integration`, o `503` sem o dono e os testes no módulo | Isolamento de módulo, degradação |
| 27/10 | [#31](https://github.com/diashelter/laravel-module-ecomerce/pull/31) | (processo) | A skill de spec atualizada | Ferramentas do processo |
| 28/10 | [#32](https://github.com/diashelter/laravel-module-ecomerce/pull/32) (HEL-17) | O CQRS não tinha banco nem processo próprios (estudo) | A vitrine no MongoDB, o projetor versionado e o `api-read` atrás do Nginx | Modelo de leitura, consistência eventual |

Os códigos `HEL-n` são as issues do Linear que pediram cada entrega; os designs citam a issue de cada uma.

---

## Parte 3. As entregas, uma a uma

### 1. O ponto de partida: do código por camada ao monólito modular

**Entregas:** o plano de 5 passos (antes do primeiro commit), o commit inicial `37e9296` e o PR [#1](https://github.com/diashelter/laravel-module-ecomerce/pull/1) (06/10).

**Situação e problema.** O código nasceu organizado por **camada técnica**: `app/Models`, `app/Services`, `app/UseCases`, `app/Repositories`, `app/Http`. É o formato que todo tutorial ensina, e funciona enquanto o sistema é pequeno. A [análise de domínio](domain-analysis.md) de 06/10 leu o vocabulário do código e encontrou **7 contextos delimitados** (pedidos, catálogo, estoque, pagamento, entrega, identidade e clientes), um contexto de leitura (o dashboard) e um kernel de infraestrutura, todos misturados nas mesmas pastas. Ela listou 8 problemas de coesão e acoplamento, por exemplo:

- o `Product` (catálogo) decidia se algo "pode ser comprado", uma regra que depende do estoque e cujas mensagens são do pedido;
- o `User` tinha dois significados, a identidade de quem entra e o comprador que tem pedidos;
- o caso de uso que aprovava o pagamento também despachava o job de entrega: o pagamento acionava a entrega diretamente;
- o catálogo importava o `OrderItem` do pedido só para impedir a exclusão de um produto já vendido.

Para medir a coesão de cada contexto, a análise usou uma nota de 0 a 10 com quatro critérios: linguagem compartilhada (0 a 3), conceitos usados juntos (0 a 3), dados relacionados (0 a 2) e conceitos que mudam juntos (0 a 2). A nota é subjetiva, mas força a explicar o porquê de cada número.

**O que foi feito.** Um plano de 5 passos, na ordem do que reduz mais risco com o menor esforço: centralizar a regra de disponibilidade, separar o `Customer` do `User`, separar pagamento e entrega por eventos, expor o estoque por contratos e, por último, mover as 118 classes para 9 módulos em `app/Modules`, **um módulo por vez**, com a suíte rodando entre eles. A mudança de pasta foi feita por um script que reescrevia as referências usando o tokenizer do PHP (para não confundir um nome de classe com o texto de um comentário). O commit inicial já traz esse resultado e o `ModuleBoundariesTest`, um teste de arquitetura que falha quando um módulo usa o que não deve de outro.

O PR #1 resolveu o problema 7 (o catálogo conhecer pedidos) com **inversão de dependência**: quem precisa da informação define a interface, e quem a tem a implementa.

**Exemplo.** O Catalog define o contrato; o Ordering o implementa e o liga no próprio service provider ([`ProductOrderHistory`](../backend/app-modules/catalog/src/Contracts/ProductOrderHistory.php)):

```php
// Modules\Catalog\Contracts\ProductOrderHistory: definido pelo Catalog, que precisa da resposta
interface ProductOrderHistory
{
    public function hasBeenOrdered(string $productId): bool;
}

// Modules\Ordering\Providers\OrderingServiceProvider: quem tem os dados implementa
public array $bindings = [
    ProductOrderHistory::class => OrderRepository::class,
    // ...
];
```

O `DeleteProductUseCase` do Catalog recebe o `ProductOrderHistory` no construtor e nunca nomeia o Ordering. A dependência entre os módulos continua num sentido só: o Ordering depende do Catalog (para ler preços), e nunca o contrário. A chave estrangeira `order_items.product_id` com `restrict` continua como última linha de defesa no banco.

**Por que assim.**

- **Monólito modular, e não microsserviços.** O checkout precisa bloquear o estoque, conferir a disponibilidade, baixar o estoque e criar o pedido na mesma transação. Num monólito isso é uma transação do banco; entre serviços seria uma saga, com compensações e estados intermediários. A análise registra: "a transação única entre os contextos é uma escolha correta num monólito".
- **Um módulo por vez, com a suíte verde entre os passos**, é o que torna uma reorganização grande reversível a qualquer momento.
- **O contrato fica com quem consome.** Se a interface ficasse no Ordering, o Catalog passaria a depender dele, e o Ordering já depende do Catalog: os dois ficariam presos um ao outro.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Manter as camadas técnicas | Familiar, zero esforço | Toda funcionalidade toca todas as pastas; nada impede um contexto de usar o interior do outro | Sistema pequeno, um time, poucos conceitos |
| **Monólito modular** (escolhida) | Um deploy e um banco, transações locais, fronteiras explícitas e testáveis | Exige disciplina e testes de arquitetura; o banco ainda é compartilhado | O padrão para a maioria dos produtos até haver um motivo claro para separar |
| Microsserviços | Deploy, escala e tecnologia independentes por serviço | Rede no meio de cada chamada, consistência eventual, sagas, observabilidade distribuída, custo operacional alto | Times independentes, partes com escala muito diferente, isolamento de falha exigido |
| Pacotes de módulos (`nwidart/laravel-modules`) desde o início | Estrutura pronta | Mais um framework para aprender antes de conhecer as fronteiras | Quando as fronteiras já são conhecidas (o projeto adotou o `internachi/modular` depois, no capítulo 15) |

Para o problema 7 em especial, havia ainda: deixar só a chave estrangeira decidir (o erro do banco viraria a regra de negócio, sem mensagem clara), ou manter no Catalog uma marca "já foi vendido" atualizada por um evento do Ordering (consistência eventual para uma regra que pode esperar, mas com mais peças). O contrato do consumidor é o mais simples que mantém a direção.

**Sinal para aplicar.** Uma mudança pequena de negócio obriga a editar arquivos de áreas que não têm nada a ver com ela; uma palavra significa duas coisas diferentes (o `User`); aparecem dependências circulares; o teste de uma área precisa montar o sistema inteiro; ninguém sabe dizer quem é o "dono" de uma regra.

**Para praticar.** Recoloque a relação `orderItems()` no model `Product` e rode `make test-filter FILTER=ModuleBoundariesTest`: o teste deve falhar apontando o Catalog usando o Ordering. Depois desfaça.

---

### 2. A rede de segurança: CI, processo e provas

**Entregas:** [#2](https://github.com/diashelter/laravel-module-ecomerce/pull/2) (CI), [#3](https://github.com/diashelter/laravel-module-ecomerce/pull/3) e [#31](https://github.com/diashelter/laravel-module-ecomerce/pull/31) (skills do processo), e as rodadas de verificação de cada entrega seguinte.

**Situação e problema.** Uma arquitetura só se mantém se alguém a confere a cada mudança. No primeiro dia, nada conferia o estilo nem os testes de um pull request, e as regras de fronteira existiam só no teste que alguém lembrasse de rodar.

**O que foi feito.**

- **CI com o mesmo ambiente do desenvolvimento.** O workflow sobe os mesmos serviços do Docker Compose e chama os mesmos alvos do `Makefile` (`make pint`, `make test`), então o CI testa na mesma imagem PHP e na mesma versão do PostgreSQL do `make test` local. Depois ganhou o RabbitMQ, o MongoDB e um segundo job com o smoke de carga do k6.
- **Um processo escrito para cada entrega**, com as skills instaladas no repositório: uma *discovery* que vira um design (`.design/`), um plano com critérios de aceite e provas (`.specs/features/<entrega>/plan.md` e `checks.md`), a construção e uma **verificação independente** (quem verifica não é quem construiu), registrada em `verification.md`. A verificação planta defeitos de propósito (mutantes) para ver se as provas os pegam.
- **Testes de arquitetura como funções de aptidão** (*fitness functions*, do livro *Building Evolutionary Architectures*): regras sobre a estrutura do código que rodam como teste. Hoje são 14 arquivos em `backend/tests/Unit/Architecture`, e cada regra foi "vista falhando com uma violação de propósito" antes de ser aceita.
- **A documentação faz parte da entrega** ([AGENTS.md](../AGENTS.md)): uma tarefa só termina quando o README, a análise de domínio e este guia descrevem o código como ele ficou.

**Exemplo.** O `ModuleBoundariesTest` gera uma expectativa para cada pasta privada de cada módulo, a partir das pastas que existem, então uma pasta nova já nasce privada sem ninguém editar o teste ([arquivo](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php)):

```php
// Privacidade: um módulo alcança outro só pelas pastas públicas (uma expectativa por namespace).
foreach (MODULES as $source) {
    foreach (array_diff(MODULES, [$source, 'Shared']) as $target) {
        foreach (privateDirectoriesOf($target) as $directory) {
            arch("{$source} reaches another module only through its public directories: {$target}\\{$directory}")
                ->expect('Modules\\'.$source)
                ->not->toUse('Modules\\'.$target.'\\'.str_replace('/', '\\', $directory));
        }
    }
}
```

Dois tropeços reais mostram por que "ver o teste falhar" é obrigatório:

1. Com uma **lista** de namespaces, o `not->toUse` dessa versão do Pest nunca falha. Por isso o teste gera uma expectativa por namespace.
2. Numa regra escrita com aspas duplas, `\{$namespace}` não interpolava, e o alvo virava o texto literal `...\{Services}`: a regra passava sem verificar nada. Só a violação plantada de propósito mostrou o erro.

Um teste que nunca falhou não provou nada.

**Por que assim.** O CI que usa o mesmo Compose elimina a classe inteira de defeitos "passa no CI e falha na máquina" (e o contrário). O processo escrito força as perguntas certas antes do código: qual é o problema, qual número o comprova, o que é caro desfazer, qual alternativa mais pesada foi recusada e por quê.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens |
| --- | --- | --- |
| CI direto no runner (PHP instalado na máquina do CI) | Mais rápido de configurar e de rodar | Versões diferentes das do dev; extensões e serviços configurados duas vezes |
| **CI com o Docker Compose do dev** (escolhida) | Paridade total, um só lugar para mudar o ambiente | Build da imagem a cada run (mitigado pelo cache do Composer) |
| Deptrac ou PHPArkitect no lugar dos testes do Pest | Ferramentas dedicadas a camadas, com relatórios próprios | Mais uma ferramenta; o projeto já tinha o arch test do Pest como precedente |
| Infection (testes de mutação automáticos) | Mede a força da suíte inteira | Lento numa suíte com banco e broker; o projeto usa mutantes dirigidos na verificação de cada entrega |
| Revisão humana sem regras automáticas | Nada a manter | A mesma violação é discutida em todo PR, e uma hora passa |

**Sinal para aplicar.** "Funciona na minha máquina"; regras de arquitetura que só existem num wiki; revisões que apontam o mesmo erro de fronteira repetidas vezes; documentação que descreve um sistema que não existe mais.

**Para praticar.** Abra qualquer `verification.md` em `.specs/features/` (por exemplo o do [modelo de leitura](../.specs/features/storefront-read-model/verification.md)) e leia a tabela de achados: cada mutante que sobreviveu virou um teste novo. Escreva uma regra nova de arquitetura, plante uma violação e veja-a falhar antes de aceitá-la.

---

### 3. Modelagem tática: dinheiro, value objects e listas tipadas

**Entregas:** [#4](https://github.com/diashelter/laravel-module-ecomerce/pull/4) (dinheiro em centavos), [#5](https://github.com/diashelter/laravel-module-ecomerce/pull/5) (e-mail e senha), [#6](https://github.com/diashelter/laravel-module-ecomerce/pull/6) e [#8](https://github.com/diashelter/laravel-module-ecomerce/pull/8) (listas tipadas), todas em 06/10.

**Situação e problema.**

- **Dinheiro.** O banco usava `decimal(10,2)`, a API trafegava texto (`"199.90"`) e o backend somava com `BcMath\Number`. Era exato, e nenhum usuário era prejudicado: o design diz que a mudança foi uma **construção**, para praticar o padrão que o mercado usa. No caminho apareceu um achado real: `BcMath\Number` só existe a partir do PHP 8.4, e o `composer.json` declarava `^8.3`.
- **E-mail e senha.** A regra de e-mail estava escrita em 5 Form Requests, e o mínimo de senha em 4. E havia um defeito: o e-mail não era normalizado, o PostgreSQL compara texto diferenciando maiúsculas, e `ADMIN@example.com` não encontrava a conta `admin@example.com`. Dava para cadastrar `Ana@x.com` e `ana@x.com` como duas contas.
- **Listas.** O carrinho, as linhas do pedido e o resultado da validação atravessavam casos de uso, services e contratos como `array`, com o formato descrito só em docblock (`array{items, total_cents, is_valid}`), e os totais eram acumulados ao lado das linhas.

**O que foi feito.**

- Todo valor monetário virou um **inteiro de centavos** em todas as camadas, com a unidade no nome (`price_cents`, `total_cents`), colunas `bigint` com `CHECK (... >= 0)` e a conversão para reais só no frontend (exibição e o campo que o admin digita).
- `Email` e `Password` viraram **value objects** do Identity, e o banco ganhou o `CHECK (email = lower(btrim(email)))`.
- As listas viraram **classes imutáveis, uma por conceito**, que calculam os próprios totais.

**Exemplo.** O antes e o depois do cálculo das linhas do pedido:

```php
// Antes (commit inicial): decimal em texto, BcMath e um array sem tipo
/** @return array{total: string, lines: list<array<string, mixed>>} */
$total = new Number('0.00');
$subtotal = new Number($product->price) * $quantity;
$total = $total + $subtotal;
$lines[] = ['unit_price' => $product->price, 'subtotal' => (string) $subtotal /* ... */];
```

```php
// Depois: centavos inteiros e uma lista tipada que deriva o total das próprias linhas
final readonly class OrderLine
{
    public function __construct(
        public string $productId,
        public string $productName,
        public int $unitPriceCents,
        public int $quantity,
    ) {}

    public function subtotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }
}

final readonly class OrderLines implements Countable, IteratorAggregate
{
    public function __construct(OrderLine ...$lines) { /* ... */ }

    public function totalCents(): int  // o total nunca diverge das linhas
    {
        return array_sum(array_map(fn (OrderLine $line) => $line->subtotalCents(), $this->lines));
    }
}
```

([`OrderLine`](../backend/app-modules/ordering/src/Core/ValueObjects/OrderLine.php), [`OrderLines`](../backend/app-modules/ordering/src/Core/ValueObjects/OrderLines.php)). O parâmetro variádico tipado (`OrderLine ...$lines`) faz o PHP recusar, na construção, um elemento de outro tipo.

E o e-mail, com uma fonte só para a forma canônica ([`Email`](../backend/app-modules/identity/src/Core/ValueObjects/Email.php)):

```php
final readonly class Email
{
    public function __construct(string $email)
    {
        $normalized = self::normalize($email);
        // ...recusa o texto longo demais e o formato inválido com InvalidArgumentException
        $this->value = $normalized;
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email)); // o único lugar que decide a forma canônica
    }
}
```

O `Password` guarda o texto num `SensitiveParameterValue`, marca o argumento com `#[SensitiveParameter]` e não tem `__toString`: a senha não aparece em `var_dump`, `json_encode`, `serialize` nem em stack traces. O login **não** constrói um `Password`: ele confere qualquer senha contra o hash, para que endurecer a política nunca tranque quem já tem conta.

**Por que assim.**

- **`bigint`, não `integer`:** o maior preço aceito, R$ 99.999.999,99, são 9.999.999.999 centavos, que não cabem num inteiro de 32 bits.
- **A unidade no nome e sem alias para os nomes antigos:** um consumidor desatualizado quebra na checagem de tipos, em vez de mostrar R$ 1.990,00 no lugar de R$ 19,90. Por isso backend e frontend mudaram no mesmo PR.
- **A regra mora no value object, e o banco é a última defesa:** o `CHECK` faz uma gravação que fuja do `Email` (factory, seeder, `tinker`) falhar, em vez de criar uma segunda conta.
- **Uma classe por lista, sem classe base genérica:** cada lista carrega a sua regra (ids canônicos e sem repetição em `ProductIds`, total derivado em `OrderLines`). O `array` aparece só onde o framework o exige (`whereIn`, `sync`, os resources).

O PR #8 corrigiu um detalhe que ensina muito: o código usava `array_all`, que só existe no PHP 8.4, enquanto o `composer.json` prometia `^8.3`. **Uma restrição de plataforma declarada precisa ser verdadeira.**

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| `float` | Nenhuma | Erros de arredondamento (`0.1 + 0.2`) | Nunca para dinheiro |
| `decimal` + biblioteca de precisão (`BcMath`, `brick/math`) | Exato, legível no banco | Conversões em cada camada; JSON com texto | Taxas, juros, câmbio com muitas casas decimais |
| **Inteiro de centavos** (escolhida) | Exato, rápido, simples em JSON e em JavaScript | Cuidado na exibição e com moedas de outras casas decimais | Uma moeda, valores de varejo |
| Value object `Money` (`moneyphp/money`) com moeda | Impede somar reais com dólares; centraliza arredondamento e rateio | Mais código e conversões | Várias moedas, descontos, rateio de frete, impostos |
| `Email` como cast do Eloquent | O e-mail vira objeto em todo o sistema | Mexe no provider de autenticação, nas factories e nos resources sem regra nova | Quando algo precisar de comportamento do e-mail (domínio, mascaramento para LGPD) |
| Arrays com tipos genéricos no PHPStan (`list<OrderLine>`) | Nenhuma classe nova | O tipo só existe na análise estática; o total continua podendo divergir | Times que já rodam PHPStan em nível alto |

**Sinal para aplicar.** A mesma validação copiada em vários lugares; um defeito que depende de maiúsculas e minúsculas; `float` perto de dinheiro; totais guardados ao lado das linhas que os compõem; arrays cujo formato só existe no comentário.

**Para praticar.** Tente criar duas contas com `Ana@x.com` e `ana@x.com` pela API e veja o `422`. Depois tente inserir `Ana@x.com` direto no banco (`make db-shell`) e veja o `CHECK customers_email_normalized` recusar.

---

### 4. Pagamento atrás de uma porta

**Entrega:** [#9](https://github.com/diashelter/laravel-module-ecomerce/pull/9) (06/10 e 07/10), design em [.design/fake-payment-gateway.md](../.design/fake-payment-gateway.md).

**Situação e problema.** O módulo de pagamento não tinha gateway nem modelo: todo pagamento era aprovado na hora, e o "pagamento recusado" não existia. A análise de domínio dava 7/10 de coesão ao Payment justamente por não ter um modelo próprio. A entrega deveria tornar duas coisas possíveis: trocar o gateway escrevendo só um adaptador novo, e o cliente ter o pagamento recusado e tentar de novo.

**O que foi feito.**

- Uma **porta** `PaymentGateway` (recebe `ChargeRequest`: pedido, valor em centavos e token do cartão; devolve `ChargeResult`: aprovado ou recusado, o motivo e o id da transação) e um adaptador `FakePaymentGateway`, que decide pelo cartão de teste, como os sandboxes dos gateways reais.
- A tabela `payments`, com **uma linha por tentativa**, inclusive a recusada, final depois de gravada.
- **No máximo um pagamento aprovado por pedido, garantido pelo banco** com um índice único parcial. Na corrida entre duas aprovações, a perdedora recebe `409` e não grava nada.
- A recusa responde `402 PAYMENT_DECLINED` e não muda o pedido, que continua aguardando pagamento.

**Exemplo.** A porta, o adaptador, a garantia no banco e a corrida tratada no caso de uso ([`PaymentGateway`](../backend/app-modules/payment/src/Contracts/PaymentGateway.php), [`FakePaymentGateway`](../backend/app-modules/payment/src/Gateways/FakePaymentGateway.php), [`PayOrderUseCase`](../backend/app-modules/payment/src/Core/UseCases/PayOrderUseCase.php)):

```php
interface PaymentGateway
{
    public function charge(ChargeRequest $request): ChargeResult;
}

final class FakePaymentGateway implements PaymentGateway
{
    public function charge(ChargeRequest $request): ChargeResult
    {
        $declineReason = match ($request->cardToken) {
            'fake_card_approved' => null,
            'fake_card_insufficient_funds' => DeclineReason::InsufficientFunds,
            'fake_card_declined' => DeclineReason::CardDeclined,
            default => DeclineReason::InvalidCard,
        };
        // ...
    }
}
```

```sql
-- No máximo um aprovado por pedido: a segunda aprovação simultânea falha aqui.
CREATE UNIQUE INDEX payments_order_id_approved_unique ON payments (order_id) WHERE status = 'approved';
```

```php
// PayOrderUseCase (trecho): o valor vem do pedido, nunca da requisição
$request = new ChargeRequest($order->id, $order->totalCents, $data->cardToken);
$result = $this->gateway->charge($request);

try {
    $payment = DB::transaction(function () use ($request, $result, $order): Payment {
        $payment = $this->payments->record($request, $result);
        // (desde o capítulo 9, o PaymentApproved é gravado aqui, na mesma transação)
        return $payment;
    });
} catch (UniqueConstraintViolationException) {
    throw $this->rules->notPayable(); // outra requisição aprovou primeiro: 409
}

if ($result->status === PaymentStatus::Declined) {
    throw new PaymentDeclinedException($result->declineReason); // 402, depois de gravar a tentativa
}
```

**Por que assim.**

- **Só o service provider do Payment conhece o adaptador** (`$bindings`), e o `ModuleBoundariesTest` cobra que nada mais use o `FakePaymentGateway`. Trocar de gateway é uma classe e uma linha.
- **A recusa é gravada antes de a exceção sair.** O precedente de lançar uma exceção de negócio dentro da transação desfaria a linha da recusa; aqui a exceção só é lançada depois do commit.
- **"Pode ser pago" olha também os registros do próprio Payment:** o pedido precisa aguardar pagamento **e** não ter aprovação. Isso fecha a janela em que a aprovação já foi gravada, mas a fila ainda não moveu o pedido.
- **O índice único parcial é a idempotência mais barata que existe:** o banco recusa a duplicata, sem trava na aplicação.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| SDK do gateway direto no caso de uso | Menos código | Teste depende da rede ou de mocks do SDK; trocar de fornecedor reescreve o caso de uso | Protótipo descartável |
| **Porta + adaptador** (escolhida) | Teste com o fake, troca de fornecedor localizada, regra do domínio separada do formato do fornecedor | Uma interface e um mapeamento a mais | Toda integração externa que importa |
| Gravar a tentativa como "pendente" antes de cobrar e enviar o id dela como chave de idempotência ao gateway | Uma requisição duplicada nunca cobra duas vezes | Mais estados e uma reconciliação | **Obrigatória com dinheiro de verdade**: no formato atual, a requisição que perde a corrida já foi cobrada quando o banco a recusa (o design registra isso) |
| Pagamento assíncrono (Pix, 3DS, webhook) | Cobre os meios de pagamento reais do Brasil | Estado pendente, endpoint de callback com verificação de assinatura, idempotência do webhook | Assim que houver Pix ou boleto |

**Sinal para aplicar.** Uma integração externa que você quer testar sem rede; a chance de trocar de fornecedor; um provedor que pode ficar fora do ar; dinheiro ou outro efeito que não pode acontecer duas vezes.

**Para praticar.** Escreva um segundo adaptador (`AlwaysDeclinedGateway`), ligue-o no `PaymentServiceProvider` e rode os testes do Payment: só os que dependem do cartão aprovado devem mudar. Depois desenhe, numa ADR, a versão com a tentativa pendente e a chave de idempotência.

---

### 5. Identidade: equipe e clientes em contas separadas

**Entrega:** [#10](https://github.com/diashelter/laravel-module-ecomerce/pull/10) (07/10), design em [.design/staff-and-customer-accounts.md](../.design/staff-and-customer-accounts.md).

**Situação e problema.** Havia uma tabela de contas, `users`, onde cliente e administrador se distinguiam só pelo `role`. Os dois entravam pelo mesmo login, não podiam usar o mesmo e-mail, e não existia um perfil de suporte (quem atende e mantém o catálogo sem poder excluir). Pior: todas as rotas autenticadas usavam `auth:sanctum`, que aceita a primeira sessão que autenticar. Acrescentar a equipe à lista de guards dele deixaria uma sessão de equipe fazer pedidos na loja. O momento de separar era aquele: as contas só existiam no seed, e depois de haver histórico de pedidos a mesma separação exigiria migrar dados.

**O que foi feito.**

- Duas tabelas: `users` (a equipe, com `role` `admin` ou `support`, `CHECK` e sem valor padrão) e `customers` (os compradores). O mesmo e-mail pode existir nas duas.
- **Um guard de sessão por área** (`customer` e `staff`) e nenhuma rota com `auth:sanctum`. Uma sessão de uma área recebe `401` na outra.
- **"Só o admin remove"**, aplicado por um grupo de rotas com o middleware `admin`, e um teste que percorre a tabela de rotas e falha se algum `DELETE` de `/api/admin/*` não responder `403` ao suporte.
- O admin não muda o próprio papel nem remove a própria conta (`409`).

**Exemplo.** Os guards e o grupo que faz uma rota `DELETE` nova nascer restrita ([`config/auth.php`](../backend/config/auth.php), [rotas do Catalog](../backend/app-modules/catalog/routes/api.php)):

```php
'guards' => [
    // Um guard por área: uma sessão da equipe nunca autentica a loja, e vice-versa.
    'customer' => ['driver' => 'session', 'provider' => 'customers'],
    'staff' => ['driver' => 'session', 'provider' => 'users'],
],
```

```php
Route::middleware('auth:staff')->group(function (): void {
    Route::apiResource('products', AdminProductController::class)->except('destroy');

    // Só o papel admin: as remoções. Um DELETE novo entra aqui por padrão.
    Route::middleware('admin')->group(function (): void {
        Route::apiResource('products', AdminProductController::class)->only('destroy');
    });
});
```

**Por que assim.**

- **A conta do cliente ficou no Identity, e não no Customers:** se ficasse no Customers, o Ordering dependeria dele, e o Customers já depende do Ordering (para contar pedidos). Seria um ciclo.
- **A regra "só o admin remove" mora no grupo de rotas, e um teste percorre todas as rotas.** A alternativa, um método `delete` em cada policy, deixaria uma rota nova liberar o suporte em silêncio. É o princípio do *negar por padrão*.
- **Credenciais da outra tabela recebem o mesmo "E-mail ou senha inválidos."** de uma senha errada, sem revelar que a conta existe do outro lado.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Uma tabela com papéis | Simples | Mistura populações com ciclos de vida diferentes; um e-mail por pessoa; risco de uma sessão valer nas duas áreas | Sistemas internos com um único tipo de usuário |
| **Tabelas e guards separados** (escolhida) | Fronteira clara, mesmo e-mail possível, regras de cada lado independentes | Dois logins, dois providers | Loja com equipe e clientes |
| Admin numa origem própria (`admin.dominio`), com cookie e logout independentes | Isolamento total das sessões | Outro deploy e outra configuração de CORS/cookies | Quando o admin for publicado separado da loja |
| Matriz de permissões (`spatie/laravel-permission`) | Permissões finas e configuráveis | Mais tabelas e mais regras a testar | A partir de um terceiro papel com permissões parciais |
| Identidade externa (OIDC: Keycloak, Auth0, Cognito) | SSO, MFA e recuperação de senha prontos | Dependência externa e integração | Vários sistemas compartilhando login, exigência de MFA |

**Sinal para aplicar.** Checagens de papel espalhadas pelo código; duas populações com regras, telas e obrigações legais (LGPD) diferentes; uma sessão de uma área que alcança a outra; um papel novo pedindo exceções em várias policies.

**Para praticar.** Entre como `suporte@example.com` e tente `DELETE /api/admin/products/{id}`: `403`. Crie uma rota `DELETE` nova fora do grupo `admin` e rode o `StaffRoleTest`: ele deve falhar.

---

### 6. Endereços, frete e a cópia no pedido

**Entrega:** [#11](https://github.com/diashelter/laravel-module-ecomerce/pull/11) (07/10), design em [.design/addresses-and-shipping.md](../.design/addresses-and-shipping.md).

**Situação e problema.** O pedido não tinha destino. A entrega era um job que esperava 10 segundos e anunciava "entregue", qualquer que fosse o endereço, e o total cobrado era só a soma dos itens. O Customers e o Fulfillment estavam em 7/10 de coesão pela mesma lacuna: um sem dados próprios, o outro sem regras próprias.

**O que foi feito.**

- O **caderno de endereços** no Customers (`customer_addresses`, até 10 por cliente).
- A **tabela de frete por UF** e o **calendário de dias úteis** no Fulfillment.
- O pedido nasce com uma **cópia** do endereço escolhido, do frete e do prazo prometido, em colunas `NOT NULL` de `orders`, e o total passa a ser itens mais frete, **calculado no servidor**.
- A data prevista é fixada pelo Fulfillment quando o pagamento é aprovado e chega ao pedido pelo evento `DeliveryScheduled` (só o Ordering escreve em `orders`).
- O Ordering pede o endereço e o frete por **contratos que ele mesmo define**, `DeliveryAddressBook` (implementado pelo Customers) e `ShippingQuoter` (implementado pelo Fulfillment).

**Exemplo.** O checkout resolve o endereço e o frete **antes** de abrir a transação, então um endereço inválido nunca bloqueia o estoque ([`PlaceOrderUseCase`](../backend/app-modules/ordering/src/Core/UseCases/PlaceOrderUseCase.php)):

```php
// O endereço vem primeiro: um endereço desconhecido nunca trava nem toca o estoque.
$address = $this->addressBook->find($customerId, $addressId)
    ?? throw ValidationException::withMessages(['address_id' => ['Endereço de entrega não encontrado.']]);

// O servidor cota o frete pela UF do endereço: o cliente nunca o envia.
$shipping = $this->shippingQuoter->quote($address->state);
```

**Por que assim.**

- **A cópia (snapshot), e não uma chave para o endereço.** Editar ou excluir o endereço, ou mudar a tabela de frete, nunca altera um pedido feito. Uma chave estrangeira bloquearia a exclusão do endereço ou perderia a referência. É o mesmo princípio que já valia para `order_items`, que guarda o nome e o preço do produto no momento da compra.
- **O servidor calcula o frete e o total.** O que o cliente manda de preço, frete ou UF é ignorado: a fronteira de confiança é a API.
- **Contratos definidos pelo consumidor**, como o `ProductOrderHistory` do capítulo 1: o Customers e o Fulfillment já dependem do Ordering, então publicar os contratos neles criaria ciclos.
- **O fuso faz parte da regra:** a data prevista conta dias úteis em `America/Sao_Paulo`. Uma compra às 23h30 de quarta, que já é quinta em UTC, conta a partir de quarta. No frontend, o dia `"2026-10-09"` é formatado sem passar por `new Date()`, que o leria como meia-noite UTC e mostraria o dia anterior no Brasil.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Chave estrangeira para o endereço | Sem duplicação | O histórico muda junto com o cadastro, ou a exclusão fica bloqueada | Nunca para documentos com valor histórico (pedido, nota fiscal) |
| Tabela `order_delivery_addresses` separada | Organiza as colunas | Não garante no banco que todo pedido tem destino | Vários endereços por pedido |
| **Cópia em colunas `NOT NULL`** (escolhida) | O banco garante o destino, e o histórico é imutável | Colunas a mais em `orders` | Um destino por pedido |
| Remessas (`shipments`) com ciclo de vida e orçamento com validade | Rastreio, etapa "Enviado", várias remessas | Mais estados e tabelas | Com transportadora real, rastreio ou tarifas que mudam entre o orçamento e a compra |
| API de frete pronta (Correios, Melhor Envio) atrás de uma porta | Preço real por CEP, peso e dimensões | Dependência externa, latência e falhas no checkout | Operação real (o projeto quis regras próprias no módulo) |

**Sinal para aplicar.** Um registro histórico que muda quando o cadastro muda; um valor de negócio calculado no cliente; datas que "pulam" um dia perto da meia-noite; um módulo que só existe como um job sem regras.

**Para praticar.** Faça um pedido, edite o endereço usado e abra o pedido de novo: a cópia não muda. Tente enviar `shipping_cents: 0` no corpo do `POST /api/orders` e confira que o frete gravado é o da tabela.

---

### 7. Fachadas e vocabulário público dos módulos

**Entregas:** [#12](https://github.com/diashelter/laravel-module-ecomerce/pull/12) (HEL-7, 08/10 e 09/10) e [#13](https://github.com/diashelter/laravel-module-ecomerce/pull/13) (HEL-10, 09/10). Designs em [.design/module-facades.md](../.design/module-facades.md) e [.design/module-vocabulary.md](../.design/module-vocabulary.md).

**Situação e problema.** Os módulos existiam como pastas, mas a fronteira era uma **lista de proibições**: cerca de 20 regras pontuais no teste, e tudo o que nenhuma regra nomeava era permitido. Numa contagem das declarações `use`, de **102 referências** de um módulo para uma classe de outro, só **12** passavam por `Contracts` ou `Events`. As outras entravam nas partes internas: o model `Order` usado no Payment e no Fulfillment, o `Product` e o `Stock` no checkout, o `OrderRepository` no Customers. Até os contratos públicos vazavam o model: `StockInitializer::createForProduct(Product): Stock`. Na rodada seguinte, outras **57 referências** iam para `Http`, `DTOs`, `ValueObjects` e `Enums` de outro módulo, e 15 delas não deveriam existir (14 regras de validação e resources do `Http` de outro módulo, e um enum que nenhum contrato expunha).

Isso era construção com prazo: o outbox (capítulo 9) precisava de eventos com um formato que pudesse ser gravado, e o CQRS (capítulo 10) precisava saber quais leituras atravessam módulos. Fazer as fachadas depois significaria escrever a fronteira duas vezes.

**O que foi feito.**

- **A fachada de um módulo é o seu `Contracts` (só interfaces) mais os seus `Events`.** Todo o resto é privado, inclusive uma pasta nova e o `Http`.
- **Contratos e eventos falam em dados** (ids, value objects, enums), nunca em models ou coleções do Eloquent. Cinco contratos novos, um por travessia real e nomeados pelo papel de quem chama: `ProductCatalog`, `StockLevels`, `PayableOrders`, `CustomerOrderHistory` e `CustomerAccounts`.
- **Os eventos passaram a carregar ids e valores**: o `OrderPaid` leva o `orderId` e os dias úteis prometidos, e o Fulfillment agenda a entrega sem ler o pedido.
- **O teste virou uma lista do que é permitido**, gerada das pastas que existem.
- **O vocabulário público é calculado:** de `DTOs`, `ValueObjects` e `Enums`, é público só o que os tipos nativos das assinaturas de `Contracts` e `Events` alcançam, de forma transitiva. O teste lê as assinaturas por reflexão a cada execução, sem lista guardada.
- **O `Http` de cada módulo é privado:** o Customers e o Fulfillment ganharam os próprios adaptadores (regras de validação, normalização da entrada, resources), que só traduzem para o Laravel o que o value object decide.

**Exemplo.** Um contrato que fala em dados ([`StockReservation`](../backend/app-modules/inventory/src/Contracts/StockReservation.php)) e o cálculo do vocabulário ([`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php)):

```php
interface StockReservation
{
    /** SELECT ... FOR UPDATE, sempre na mesma ordem (por product_id). Devolve as quantidades lidas sob o lock. */
    public function lockForProducts(ProductIds $productIds): StockQuantities; // ids entram, valores saem

    public function decrement(string $productId, int $quantity): void;
}
```

```php
/** O vocabulário de um módulo: as classes dele que as assinaturas da própria fachada alcançam. */
function vocabularyOf(string $module): array
{
    return array_values(array_filter(
        reachedFrom(facadeOf($module)), // segue parâmetros, retornos e propriedades públicas, transitivamente
        fn (string $class) => str_starts_with($class, 'Modules\\'.$module.'\\'),
    ));
}
```

Hoje são 30 tipos públicos (como `CatalogProducts`, `StockQuantities` e `OrderToPay`) e 30 privados (como `ValidatedCart`, `OrderLines` e `ProductRecord`). Um tipo novo numa assinatura fica público sozinho; uma classe que nenhuma assinatura alcança continua privada, sem ninguém editar o teste.

**Por que assim.**

- **Um contrato por papel, e não uma interface única por módulo** (`OrderingFacade`): com uma interface só, todo consumidor dependeria de tudo, e qualquer mudança afetaria todos.
- **Só existe contrato para travessia real.** O design cita o retrospecto do Packwerk, a ferramenta de módulos da Shopify: a pasta pública virou um depósito de código "que nunca deveria ser público". Daí as regras: `Contracts` só aceita interfaces, e nenhum método é criado só para tornar um tipo público.
- **Público é o que uma assinatura expõe, não uma pasta.** Uma subpasta de vocabulário moveria cerca de 20 classes, e um atributo `#[Public]` nas classes seria uma segunda fonte para a mesma informação.
- **O vazamento de um model é o pior acoplamento**: quem recebe um model do Eloquent pode carregar relações, gravar e depender do esquema de outro módulo.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Lista de proibições (o estado anterior) | Fácil de começar | Tudo o que ninguém lembrou de proibir é permitido; uma pasta nova nasce pública | Nunca como regra final |
| **Lista do que é permitido, gerada do código** (escolhida) | Uma pasta ou classe nova nasce privada; o teste não precisa de manutenção | Exige um teste de arquitetura bem escrito (e visto falhando) | Monólito modular com vários módulos |
| Uma interface única por módulo (fachada "gorda") | Um só ponto de entrada | Todo consumidor depende de tudo | Módulos muito pequenos |
| Facades estáticas do Laravel por cima dos contratos | Sintaxe curta | Escondem a dependência do construtor e dificultam o teste | Podem vir depois, sem mudar a fronteira |
| Deptrac (camadas e regras em YAML) | Ferramenta dedicada, relatório visual | Mais uma ferramenta e outra linguagem de regras | Times grandes, regras entre muitos componentes |
| Pacotes separados com dependências declaradas (um `composer.json` que lista o que pode usar) | O próprio gerenciador de pacotes impede a dependência | Mais cerimônia por módulo | É o passo do capítulo 15, sem a parte de proibir |

**Sinal para aplicar.** Mudar um model quebra um módulo que nem deveria conhecê-lo; um contrato devolve entidades do ORM; o mesmo DTO é usado por três módulos; uma regra de validação é importada do `Http` de outro módulo; ninguém sabe dizer o que é público.

**Para praticar.** Use, num controller do Customers, uma classe privada do Ordering (por exemplo o value object `ValidatedCart`, que nenhuma assinatura pública alcança) e rode o `ModuleBoundariesTest`: ele deve falhar nessa regra. Depois acrescente um método a um contrato que devolve um value object novo e veja o tipo virar público sem editar o teste.

---

### 8. Ids públicos: UUID v7, o dono na busca e nenhuma rota resolvendo registro

**Entrega:** [#14](https://github.com/diashelter/laravel-module-ecomerce/pull/14) (HEL-11, 15/10), design em [.design/uuid-record-ids.md](../.design/uuid-record-ids.md).

**Situação e problema.** O id inteiro do banco aparecia nas respostas, nas URLs e nas telas (`Pedido #42`):

- o número **revelava quantos pedidos a loja já recebera**, e `/products/7` convidava a tentar o 8 (enumeração);
- nas rotas do cliente, o *route model binding* carregava o registro de **qualquer** cliente, e a policy respondia `403`, o que **confirmava a quem não era dono que o registro existia** (a porta de entrada para um IDOR, *Insecure Direct Object Reference*);
- o binding lia o banco fora dos repositórios, e seis rotas de escrita liam o registro sem precisar (os quatro `DELETE`, o ajuste de estoque, que lia duas vezes, e a edição do cliente no admin).

E havia o relógio: o outbox ia gravar esses ids nos eventos, e o CQRS ia publicá-los em contratos de leitura. Trocar o tipo depois deles significaria refazer o que fosse construído em cima. Esta entrega foi feita **antes** de propósito.

**O que foi feito.**

- **UUID v7** gerado pela aplicação como chave das tabelas de negócio, no tipo nativo `uuid` do PostgreSQL, e o id como `string` em toda assinatura (contratos, eventos, DTOs, value objects).
- **A forma canônica mora numa fonte só** (`RecordId`, minúsculas com hífens). Um id malformado nunca chega ao banco: no caminho é `404` sem query, e no corpo é `422`.
- **Nenhuma rota resolve registro.** O controller recebe `string $order`, e quem resolve é o caso de uso (na escrita) ou o repositório e a consulta (na leitura).
- **O dono entra na busca.** O pedido, o pagamento ou o endereço de outro cliente respondem `404`, com o corpo de um id desconhecido. As três policies que só conferiam o dono deixaram de existir.
- **O código do pedido** (`#K7Q2M9XA`): 8 símbolos sorteados, único e imutável, só para as pessoas lerem. URLs, contratos e eventos continuam com o id.

**Exemplo.** O antes e o depois do detalhe do pedido ([`OrderController`](../backend/app-modules/ordering/src/Http/Controllers/OrderController.php)):

```php
// Antes: o binding carrega o pedido de qualquer cliente, e a policy responde 403 a quem não é dono
public function show(Order $order): OrderResource
{
    Gate::authorize('view', $order); // OrderPolicy: $order->customer_id === $account->getAuthIdentifier()
    return OrderResource::make($order->load('items'));
}
```

```php
// Depois: a rota carrega um id, e o dono faz parte da busca. Outro cliente recebe o mesmo 404 de um id inexistente.
public function show(Request $request, string $order, OrderRepository $orders): OrderResource
{
    $found = $orders->findForCustomer($request->user('customer')->id, $order) ?? abort(404);

    return OrderResource::make($found);
}
```

```php
final class RecordId
{
    public const PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
}
// Route::pattern('order', RecordId::PATTERN) no register() do provider do módulo dono:
// /api/orders/1 ou um UUID em maiúsculas é 404 sem nenhuma query.
```

**Por que assim.**

- **v7, e não v4:** os primeiros 48 bits do UUID v7 são o instante da criação, então as inserções seguem uma ordem e caem perto umas das outras no índice B-tree (a RFC 9562 registra essa localidade). O v4, aleatório, espalha as inserções pelo índice inteiro. O custo aceito: o id revela quando o registro nasceu.
- **Um UUID não é credencial.** Ser difícil de adivinhar não autoriza nada, por isso o dono continua sendo conferido, agora na busca.
- **`404`, e nunca `403`, para o registro de outro cliente:** o `403` confirma que o registro existe.
- **Validar o corpo antes da existência** foi a consequência aceita: corpo inválido é `422` para qualquer id, e corpo válido com id desconhecido é `404`.
- **Por que o código não deriva do UUID:** ele não seria único garantido, e uma sequência revelaria a contagem de pedidos. A colisão é tratada num *savepoint*: no PostgreSQL, uma violação de unicidade aborta a transação inteira, então cada tentativa roda numa transação aninhada, e o lock do estoque não se perde.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| `bigint` sequencial exposto | Simples, compacto, ótimo para o índice | Enumeração, vaza o volume do negócio | APIs internas, sem exposição pública |
| `bigint` interno + id ofuscado (Sqids/Hashids) | Índice compacto, URL curta | O número continua sequencial por baixo; é ofuscação, não segredo | Quando o tamanho da URL importa muito |
| UUID v4 | Padrão universal, sem informação embutida | Inserções aleatórias no índice (mais páginas tocadas e mais fragmentação) | Ids gerados em muitos lugares sem necessidade de ordem |
| **UUID v7** (escolhida) | Gerado na aplicação, ordenado no tempo, sem enumeração | Revela o instante da criação; 16 bytes por chave | Ids públicos em sistemas novos |
| ULID ou Snowflake | Ordenados, compactos (Snowflake cabe em 64 bits) | Formato próprio; o Snowflake precisa de um id de máquina coordenado | Sistemas distribuídos com volume muito alto de ids |
| `bigint` interno e UUID público em outra coluna | O índice das chaves estrangeiras continua compacto | Duas identidades por registro, e é preciso traduzir em toda borda | Bancos muito grandes já existentes |

**Sinal para aplicar.** Ids sequenciais em URLs públicas; respostas `403` que confirmam a existência de um recurso de outra pessoa; o framework lendo o banco antes do seu código decidir; um formato de id que vai virar contrato (eventos, integrações) em breve.

**Para praticar.** Entre como `cliente@example.com`, pegue o id de um pedido de outro cliente no admin e tente `GET /api/orders/{id}`: `404`, com o mesmo corpo de um UUID inventado. Rode `make test-filter FILTER=RouteBindingTest` e depois troque `string $order` por `Order $order` num controller para vê-lo falhar.

---

### 9. Outbox transacional: nenhum evento perdido

**Entrega:** [#15](https://github.com/diashelter/laravel-module-ecomerce/pull/15) (HEL-9, 15/10 e 16/10), design em [.design/transactional-outbox.md](../.design/transactional-outbox.md).

**Situação e problema.** Os 5 eventos do pedido usavam o despacho do Laravel **depois do commit** e chegavam aos listeners pela fila do Redis. É o problema clássico da **escrita dupla** (*dual write*): o dado vai para o banco, e a mensagem vai para a fila, em duas operações que não são atômicas. Quando o envio falhava depois do commit, o evento se perdia e nada o recuperava. Pela leitura do código, isso travava o pedido em três pontos:

1. **`OrderPlaced` perdido:** o pedido ficava em `placed`, com o estoque já baixado, e o pagamento respondia `409`.
2. **`PaymentApproved` perdido:** o evento saía fora de qualquer transação. O **cliente era cobrado**, o pedido continuava aguardando pagamento, e uma nova tentativa também respondia `409`.
3. **`OrderPaid` perdido:** a transição para `payment_approved` já estava gravada, e o retry do listener não conseguia repeti-la (a guarda de idempotência impedia). **O retry tornava a falha permanente.**

Ninguém tinha visto isso acontecer, porque o projeto não tem tráfego: os três casos saíram da leitura dos casos de uso. E havia um segundo motivo: a próxima entrega (capítulo 14) queria trocar o transporte por uma flag, e sem um ponto único de publicação teria de mexer nos 5 lugares de disparo.

**O que foi feito.**

- **O evento é gravado na mesma transação da mudança que ele anuncia**, numa linha da tabela `outbox_messages`. Gravar sem uma transação aberta é erro de programação (`LogicException`).
- **Um relay**, num container próprio (`outbox-relay`), lê as mensagens pendentes, as entrega a uma **porta de transporte** e só então as marca como publicadas.
- **O envelope tem formato próprio:** o nome estável `<módulo>.<evento>` (`ordering.order_paid`), a versão, o payload plano em JSON, o instante e o `request_id`. Nunca a classe PHP.
- **Os listeners viraram consumidores** inscritos pelo módulo que consome, com **uma entrega por consumidor**: se um falha, só a entrega dele é repetida.
- O `request_id` da requisição original volta ao contexto do consumidor, então os logs de toda a cadeia levam o mesmo id.

**Exemplo.** O antes e o depois no pagamento:

```php
// Antes: a tentativa é gravada, e o evento sai depois, fora de qualquer transação.
$payment = DB::transaction(fn (): Payment => $this->payments->record($request, $result));
// ...
PaymentApproved::dispatch($order->id); // se a fila falhar aqui, o cliente foi cobrado e o pedido trava
```

```php
// Depois: a tentativa aprovada e o evento nascem juntos, ou nenhum dos dois.
$payment = DB::transaction(function () use ($request, $result, $order): Payment {
    $payment = $this->payments->record($request, $result);

    if ($result->status === PaymentStatus::Approved) {
        $this->outbox->record(new PaymentApproved($order->id));
    }

    return $payment;
});
```

A guarda que impede gravar fora da transação ([`OutboxMessageRepository`](../backend/app-modules/messaging/src/Persistence/Repositories/OutboxMessageRepository.php)) e o relay que reserva as pendentes sem disputar com outro relay ([`OutboxRelay`](../backend/app-modules/messaging/src/Core/OutboxRelay.php)):

```php
public function record(DomainEvent $event): void
{
    if (app('db.transactions')->callbackApplicableTransactions()->isEmpty()) {
        throw new LogicException('The event '.$event::eventName().' must be recorded inside the transaction of the change it announces.');
    }
    // ...valida o payload (nulo, escalar ou lista plana de escalares) e grava a linha
}

public function claimPending(int $limit): array
{
    return $this->query()
        ->whereNull('published_at')
        ->orderBy('occurred_at')->orderBy('id')
        ->limit($limit)
        ->lock('for update skip locked') // dois relays nunca pegam a mesma mensagem
        ->get()
        // ...vira uma lista de EventEnvelope
}
```

```php
// OutboxRelay::publishPending (trecho): publica em ordem e para na primeira falha
foreach ($envelopes as $envelope) {
    try {
        $this->transport->publish($envelope);
    } catch (Throwable $e) {
        Log::warning('Outbox message could not be published.', [/* message_id, name, error */]);
        break; // esta e as seguintes ficam pendentes e saem no próximo ciclo
    }
    $published[] = $envelope->id;
}
$this->messages->markPublished($published);
```

E um evento declara o próprio formato publicado ([`OrderPaid`](../backend/app-modules/ordering/src/Events/OrderPaid.php)):

```php
class OrderPaid implements DomainEvent
{
    public function __construct(public string $orderId, public int $deliveryBusinessDays) {}

    public static function eventName(): string { return 'ordering.order_paid'; }
    public static function eventVersion(): int { return 1; }

    public function toPayload(): array
    {
        return ['order_id' => $this->orderId, 'delivery_business_days' => $this->deliveryBusinessDays];
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload['order_id'], $payload['delivery_business_days']);
    }
}
```

**Por que assim.**

- **A entrega é ao menos uma vez (*at-least-once*).** Se o relay cair depois de o transporte aceitar e antes de marcar a mensagem, ela sai de novo. Não existe "exatamente uma vez" de graça em sistemas distribuídos: o que existe é "ao menos uma vez" mais consumidores idempotentes (capítulo 16).
- **Relay por polling, num processo próprio**, e não uma tarefa do scheduler por minuto (até 1 minuto de espera), um job despachado depois do commit (a mesma janela de antes) ou o `LISTEN/NOTIFY` do PostgreSQL (mais uma conexão para gerenciar). O polling espera 500 ms sem mensagens e 5 s depois de uma falha.
- **O nome do evento não é a classe PHP**, porque a classe ia mudar de namespace (capítulo 15), e porque um consumidor fora do PHP precisa ler o formato. O payload é escrito à mão em `toPayload()`, e não derivado das propriedades por reflexão, para que renomear uma propriedade não mude o formato publicado sem ninguém perceber. **Mudar um payload é subir a versão.**
- **As mensagens publicadas ficam 7 dias, e as pendentes nunca são apagadas.** A tabela não é histórico nem serve para *replay*.
- **Um teste de arquitetura recusa o despacho direto** (o `Dispatchable` de eventos, a facade `Event`, o helper `event()`), para ninguém voltar ao "atualizar e depois disparar".

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Despachar depois do commit (o estado anterior) | Simples | Escrita dupla: a mensagem se perde quando a fila falha | Efeitos que podem se perder (uma métrica, um cache) |
| Driver de fila `database` (o job gravado na mesma transação) | Já é um outbox, sem código novo | Prende os eventos aos jobs do Laravel; nenhum consumidor fora do PHP | Monólito que nunca vai publicar para fora |
| **Outbox com relay por polling** (escolhida) | Atômico com o dado, transporte trocável, fácil de entender e testar | Latência do polling; o relay é mais um processo | O padrão para a maioria dos sistemas |
| Outbox com CDC (Debezium lendo o log do banco) | Latência baixa, sem consulta à tabela, escala bem | Infraestrutura de CDC (Kafka Connect, Debezium) para operar | Volume alto, em que o polling fica caro |
| *Event sourcing* (os eventos são o próprio estado) | Histórico completo, *replay*, auditoria | Muda o modelo inteiro; projeções para toda leitura | Domínios em que o histórico é o produto (contabilidade, ledger) |
| Transação distribuída (2PC/XA) entre banco e broker | Atomicidade forte | Lenta, frágil, pouco suportada pelos brokers modernos | Quase nunca |

**Sinal para aplicar.** Um "salva no banco e depois publica na fila" em qualquer lugar do código; pedidos ou processos que ficam presos num estado intermediário sem explicação; retries que não conseguem refazer o passo que falhou; um segundo transporte ou um consumidor externo no horizonte.

**Para praticar.** Com o ambiente no ar, pare o Redis (`docker compose stop redis`), faça um pedido e veja-o parado em "Pedido efetuado" enquanto o `make logs-relay` avisa a cada 5 s. Depois `docker compose start redis` e veja o pedido seguir. É um teste de caos feito à mão.

---

### 10. O lado de leitura com consultas

**Entrega:** [#16](https://github.com/diashelter/laravel-module-ecomerce/pull/16) (HEL-6, 16/10), design em [.design/cqrs-read-side.md](../.design/cqrs-read-side.md).

**Situação e problema.** As fachadas (capítulo 7) deixaram três leituras como exceções declaradas: o dashboard do admin, a vitrine com o cadastro de produto e a lista de estoque. Eram **9 exceções** no teste, servindo 10 rotas. O dashboard lia os repositórios de quatro módulos; a vitrine lia o estoque pela relação `Product::stock`; e o Catalog e o Inventory chamavam a regra de disponibilidade que morava no Ordering, embora o Ordering dependa dos dois. Uma leitura nova que juntasse módulos não tinha onde nascer, e copiaria a relação do Eloquent.

**O que foi feito.**

- **Toda leitura que cruzava módulo virou uma consulta** do módulo que serve a rota, numa pasta privada `Queries/`. A consulta devolve value objects (nunca models), lê só as tabelas do próprio módulo e pede ao dono, pelo contrato dele, **por ids e uma vez para a página inteira**, o que não é seu.
- **Um contrato de leitura é implementado por uma consulta do dono**, e um contrato que grava ou trava continua no repositório. Nasceram os contratos de métricas do dashboard (`CatalogMetrics`, `StockMetrics`, `CustomerMetrics` e `OrderMetrics`).
- **Nenhuma relação do Eloquent atravessa módulo** (`Product::stock` e `Stock::product` deixaram de existir; a chave estrangeira continua).
- **O caso de uso de escrita devolve os dados do que gravou** (`ProductRecord`, `StockRecord`), e a consulta completa a resposta sem reler o registro.
- **A regra "pode ser comprado" passou para o Catalog**, no `ProductAvailability`; o Ordering ficou só com as mensagens.
- O `ModuleBoundariesTest` ficou **sem nenhuma exceção**.

**Exemplo.** A regra num lugar só ([`ProductAvailability`](../backend/app-modules/catalog/src/Core/ValueObjects/ProductAvailability.php)) e o antes da vitrine:

```php
// Antes: o resource do Catalog chamava a regra do Ordering e lia o estoque por uma relação do Eloquent
$availability = app(PurchaseAvailabilityService::class); // Ordering
'is_available' => $this->whenLoaded('stock', fn () => $availability->isAvailable($this->isActive(), $this->stock?->quantity)),
```

```php
// Depois: a regra é um value object do Catalog, que recebe dados (o status e as unidades), nunca models
final readonly class ProductAvailability
{
    public function __construct(private ProductStatus $status, private int $unitsInStock) {}

    public function isAvailable(): bool
    {
        return $this->status === ProductStatus::Active && $this->unitsInStock > 0;
    }
}
```

O carrinho, o checkout, a vitrine, o cadastro do admin e a lista de estoque leem a regra dali; cada chamador diz de onde vêm as unidades (o checkout, por exemplo, das linhas travadas com `FOR UPDATE`).

**Por que assim.**

- **CQRS não precisa de dois bancos.** A primeira separação é no código: casos de uso que escrevem e consultas que leem, com regras diferentes (a consulta nunca grava; o caso de uso nunca monta tela).
- **O tamanho da página nunca muda o número de queries** (o problema N+1): o teste mede a vitrine com 1 e com 12 produtos, e a lista de estoque com 1 e com 20 linhas, e exige o mesmo número.
- **Devolver o que gravou, em vez de reler:** a primeira versão do design devolvia só o id e relia o registro depois do commit, o que somava uma query a cada escrita e quebrava as contagens cobradas desde o capítulo 8.
- **A regra mudou de módulo de propósito**, desfazendo o passo 1 do plano original: com ela no Ordering, o Catalog e o Inventory dependiam de um módulo que depende deles.
- **O modelo de leitura projetado foi recusado nesta rodada**: nenhuma leitura lenta tinha sido medida, a projeção pediria eventos que não existiam e a vitrine ficaria alguns segundos atrasada. (Ele veio depois, como estudo, no capítulo 18.)

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Relações do Eloquent entre módulos (o estado anterior) | Eager loading pronto | Acopla ao esquema de outro módulo; qualquer migration dele quebra a leitura | Dentro de um mesmo módulo |
| Um SQL que junta as tabelas de outro módulo | Uma query a menos por página | O Catalog dependeria do esquema de `stocks` sem contrato | Relatórios analíticos num banco à parte |
| **Consultas por módulo + contratos de leitura por ids** (escolhida) | Fronteira limpa, número fixo de queries, leitura sempre exata | Uma leitura por módulo, e o código de composição | Telas que juntam dados de vários módulos |
| Modelo de leitura projetado por eventos (tabelas ou outro banco) | Leitura num formato pronto para a tela, escala separada | Consistência eventual, eventos novos, reconstrução | Leitura lenta medida, escala muito diferente, banco próprio (capítulo 18) |
| Views materializadas no banco | Leitura rápida sem código de projeção | Atualização em lote, ainda acopla esquemas | Dashboards que toleram minutos de atraso |
| GraphQL ou um BFF que compõe chamadas | Composição flexível no cliente | Mais uma camada; N+1 escondido em resolvers | Muitos clientes com necessidades diferentes |

**Sinal para aplicar.** Um painel que quebra quando outra equipe muda uma tabela; relações do ORM atravessando contextos; o número de queries crescendo com o tamanho da página; uma regra de negócio que vive no módulo que só a consome.

**Para praticar.** Abra o `StaffRecordRoutesTest` e veja as contagens de queries cobradas por rota. Acrescente um campo de outro módulo numa tela do admin seguindo a regra: um método novo no contrato do dono, implementado por uma consulta dele, chamado uma vez por página.

---

### 11. A borda: limite de requisições sem brechas

**Entregas:** [#17](https://github.com/diashelter/laravel-module-ecomerce/pull/17), [#18](https://github.com/diashelter/laravel-module-ecomerce/pull/18) e [#20](https://github.com/diashelter/laravel-module-ecomerce/pull/20) (16/10). As três vieram de achados dos testes de carga (capítulo 12).

**Situação e problema.** As rotas sensíveis (login, cadastro, checkout, pagamento) tinham `throttle`, o limite de requisições contra força bruta e abuso. Três defeitos, um atrás do outro:

1. **O IP vinha do cliente.** Com `trustProxies(at: '*')`, o Laravel aceitava o `X-Forwarded-For` de qualquer conexão. Um atacante trocava o próprio IP a cada requisição, e com ele a chave do limite, anulando o `throttle:10,1` do login.
2. **As rotas dividiam um contador.** O `throttle:N,M` do Laravel monta a chave só com o cliente, sem a rota: 10 cotações de frete no mesmo minuto deixavam o login do mesmo IP em `429`.
3. **Uma rajada simultânea passava inteira.** O middleware do framework **lê a contagem, compara com o limite e só depois soma**. Requisições que chegam juntas leem a contagem antes de qualquer uma somar: num teste com 30 requisições simultâneas contra um limite de 10, **passaram as 30**. No perfil `limits` do k6, passaram 11 logins contra o limite de 10. É a condição de corrida clássica **check-then-act**.

**O que foi feito.**

- **Nenhum proxy confiável.** O Nginx fala FastCGI com o PHP-FPM e já entrega o endereço da conexão em `REMOTE_ADDR`; o `trustProxies('*')` saiu.
- **Um contador nomeado por rota**: `throttle:10,1,customer-login`. Um teste percorre a tabela de rotas e falha se uma rota com limite não nomear o contador ou repetir o nome de outra.
- **Contar antes de decidir**: o alias `throttle` virou o `AtomicThrottleRequests`, que soma primeiro e decide pela contagem que recebeu de volta, sobre o `AtomicRateLimiter`, que incrementa e dá a validade da janela **num único script Lua** no Redis.

**Exemplo.** ([`AtomicThrottleRequests`](../backend/app-modules/shared/src/Http/Middleware/AtomicThrottleRequests.php), [`AtomicRateLimiter`](../backend/app-modules/shared/src/Cache/AtomicRateLimiter.php))

```php
// O middleware: conta primeiro, decide depois, pela contagem que esta requisição recebeu
foreach ($limits as $index => $limit) {
    $hits = $this->limiter->hit($limit->key, $limit->decaySeconds);

    if ($hits > $limit->maxAttempts) {
        throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
    }
    // ...
}
```

```php
// O incremento e a validade da janela num comando só do Redis: nenhuma requisição apaga o acerto de outra
private const INCREMENT = <<<'LUA'
    local hits = redis.call('INCRBY', KEYS[1], ARGV[1])
    if redis.call('TTL', KEYS[1]) == -1 then
        redis.call('EXPIRE', KEYS[1], ARGV[2])
    end
    return hits
    LUA;
```

Com só a ordem do middleware trocada, ainda passaram até 19 de 30 requisições, em 12 de 40 rajadas: o incremento do framework cria o contador, incrementa e, numa janela nova, pode gravar `1` por cima dos acertos de outras requisições. Com as duas mudanças, passaram **exatamente 10** em todas as 40 rajadas.

**Por que assim.**

- **A atomicidade precisa estar no armazenamento.** O Redis executa um script Lua de uma vez, sem intercalar outros comandos; é o mesmo princípio do `UPDATE ... WHERE` condicional no banco.
- **O `throttleWithRedis()` do Laravel não resolvia**: confere num script, soma em outro e ignora a resposta do próprio `acquire()` (passaram 21, 13 e 15 de 30 em três rajadas). Uma trava (`Cache::lock`) em volta da conferência faria as requisições da mesma chave esperarem umas pelas outras.
- **Um contador por formulário**, e não um comum: um contador comum não reduziria a força bruta (o atacante continuaria com 10 tentativas por minuto em cada formulário) e faria as senhas erradas de clientes travarem o login da equipe atrás do mesmo IP.
- **O IP real atrás de um balanceador** deve ser restaurado no próprio Nginx (`set_real_ip_from` com o endereço do balanceador e `real_ip_header X-Forwarded-For`), nunca confiando em qualquer origem.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Janela fixa (a atual: N por minuto, contada no Redis) | Simples, um contador por chave | Permite até 2N na virada da janela | A maioria das rotas de formulário |
| Janela deslizante (log ou contador ponderado) | Sem o pico da virada | Mais memória ou mais cálculo | Limites apertados de API pública |
| *Token bucket* / *leaky bucket* | Permite rajadas controladas e uma taxa média | Mais estado por chave | APIs com consumo em rajadas legítimas |
| Limite na borda (Nginx `limit_req`, API gateway, WAF/CDN) | Recusa antes de gastar um processo PHP; protege contra volume | Não conhece o usuário autenticado nem a regra de negócio | Proteção de volume e DDoS, na frente do limite da aplicação |
| Limite por conta além do IP (o e-mail tentado no login) | Pega ataques distribuídos em muitos IPs contra uma conta | Pode ser usado para bloquear a conta de alguém | Login, com cuidado (atraso progressivo, CAPTCHA) |

**Sinal para aplicar.** Código que lê um valor, decide e depois escreve, sob concorrência; um limite que "às vezes" deixa passar um a mais; cabeçalhos `X-Forwarded-*` confiados sem saber quem os escreveu; rotas diferentes que se bloqueiam mutuamente.

**Para praticar.** Rode `make test-filter FILTER=RateLimitBurstTest` e leia como ele dispara 30 processos filhos com `pcntl_fork`. Depois troque, temporariamente, o alias `throttle` pelo `ThrottleRequests` do framework e veja o teste falhar com mais de 10 aprovadas.

---

### 12. Medir antes de otimizar: testes de carga com k6

**Entregas:** [#19](https://github.com/diashelter/laravel-module-ecomerce/pull/19) (HEL-14, 16/10) e [#21](https://github.com/diashelter/laravel-module-ecomerce/pull/21) a [#23](https://github.com/diashelter/laravel-module-ecomerce/pull/23) (17/10). Design em [.design/load-tests-k6.md](../.design/load-tests-k6.md) e a documentação em [load-tests/README.md](../load-tests/README.md).

**Situação e problema.** Ninguém sabia quanto tempo cada rota levava sob concorrência, nem quantas requisições a API atendia antes de degradar. A única medida era um `curl` isolado: `GET /api/products` em 25 a 40 ms. Os testes do Pest provam as regras uma requisição por vez, e três garantias só aparecem com concorrência real: o lock do estoque com dois clientes disputando a última unidade, o limite de requisições sob rajada e o atraso do outbox entre o pedido criado e o pedido pagável. E a próxima grande mudança (os módulos como pacotes, capítulo 15) precisava de uma linha de base medida **antes**.

**O que foi feito.**

- **Uma pilha de carga própria** (o projeto Compose `ecommerce-load`), com banco, Redis e broker próprios e a configuração de produção (sem debug, opcache sem revalidação, caches, PHP-FPM com 16 processos fixos). Ela compartilha com o ambiente de dev só a imagem e o código, e nada que ela grava chega ao banco de dev.
- **Dados determinísticos** (`LoadTestSeeder`): 1.000 clientes, 201 produtos (hoje 202), 10.000 pedidos de histórico, em cerca de 5 segundos.
- **Uma mistura de tráfego realista**: cerca de 75% vitrine, 20% compra e 5% equipe. O k6 fala só HTTP, como o SPA: sessão por cookie, CSRF, nenhum atalho.
- **Oito perfis**: `smoke`, `load` (50 VUs), `stress` (150), `spike` (de 50 para 300 em 10 s), `breakpoint` (taxa de chegada de 5 a 500 iterações/s até quebrar), `soak` (1 hora), `contention` (50 clientes disputando 10 unidades) e `limits`.
- **Cada requisição declara os status que espera**: um `409` para quem perde a disputa de estoque é acerto, não erro.
- **Uma linha de base** só entra na tabela quando duas rodadas seguidas no mesmo commit diferem menos de 20% no p95. Os limites de aprovação saem da linha de base (o p95 vezes 1,2).
- **O smoke roda no CI** em todo PR, e falha quando uma rota, um status ou um payload muda e os scripts não acompanham.
- Os PRs #21 a #23 provaram o próprio harness (o que conta como erro inesperado) e estabilizaram o teste de rajada, que dava `500` no bind mount do Docker Desktop.

**Exemplo.** O cenário de disputa ([`contention.js`](../load-tests/scenarios/contention.js)) e o perfil de ruptura ([`profiles.js`](../load-tests/lib/profiles.js)):

```js
// 50 clientes compram, no mesmo instante, 1 unidade do produto que tem 10.
// Exatamente 10 vendas, nenhum 5xx, nenhum deadlock.
export function contention(data) {
    const addressId = readyAt(data); // todos entram e esperam o mesmo instante
    const body = { items: [{ product_id: data.disputed, quantity: 1 }], address_id: addressId };
    const response = send('POST', '/api/orders', { body, expect: [201, 409] }); // 409 é um resultado esperado
    created.add(response.status === 201 ? 1 : 0);
    refused.add(response.status === 409 && errorCode(response) === 'INSUFFICIENT_STOCK' ? 1 : 0);
}
```

```js
// Modelo aberto: a taxa de chegada sobe mesmo quando a API fica lenta, até quebrar.
export const BREAKPOINT = { startRate: 5, targetRate: 500, duration: '15m', /* ... */ };
```

**O que a medição mostrou.** A primeira linha de base (16/10, 50 VUs): 47,3 req/s, p95 de 30 ms na vitrine e de 55,4 ms no `POST /api/orders`, sem nenhum erro inesperado. O `breakpoint` rompeu em **49,4 iterações/s**, e a causa foi a **fila do PHP-FPM**: com 16 processos e as pausas de cada passo, a taxa de chegada acumula centenas de VUs antes do aborto. O banco não era o gargalo. O `contention` provou que o lock vende exatamente as 10 unidades.

**Por que assim.**

- **Uma linha de base vale para uma máquina, uma configuração e um conjunto de dados.** Mudar qualquer um deles abre uma série nova. Nenhum número vale como capacidade de produção.
- **A regra dos 20%** existe porque, com a máquina ocupada, cinco rodadas do mesmo código variaram até 33% entre si: a diferença entre duas rodadas era maior que a de uma mudança no código.
- **Modelo aberto no `breakpoint`** (`ramping-arrival-rate`): num modelo fechado (VUs fixos), quando a API fica lenta, os VUs também mandam menos requisições, e o teste esconde a lentidão (*coordinated omission*). No modelo aberto, a chegada não espera a resposta, como usuários reais.
- **O limitador inerte nos perfis de volume** (`CACHE_LIMITER=array`): o objetivo é medir a API, e não o `429`. O `smoke` e o `limits` rodam com os limites reais.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| `ab` ou `wrk` | Simples, muito rápidos | Uma URL por vez, sem fluxo, sessão nem checagem de corpo | Medir uma rota isolada |
| JMeter ou Gatling | Maduros, com interface e relatórios | Scripts em XML ou Scala; mais pesados | Times que já os usam |
| Locust | Cenários em Python | Gerador menos eficiente por máquina | Times Python |
| **k6** (escolhida) | Scripts em JavaScript, *thresholds*, cenários abertos e fechados, relatório HTML | O gerador na mesma máquina disputa CPU com a API | Testes versionados com o código e rodados no CI |
| k6 Cloud ou geração distribuída | Mais carga, de várias regiões | Custo | Medir capacidade real antes de um evento (Black Friday) |
| Imagem de produção imutável com limites de CPU e memória por container | Números mais próximos da produção | Mais trabalho de build | Quando os números precisarem valer como capacidade |

**Sinal para aplicar.** Uma reorganização grande no horizonte; ninguém sabe o p95 das rotas principais; uma campanha de marketing ou uma data de pico se aproxima; um bug que "só acontece em produção" sob concorrência.

**Para praticar.** Rode `make load-test TYPE=smoke` e abra o relatório HTML em `load-tests/results/`. Depois rode o `contention` e confira no admin que o `Produto Disputado` terminou com 0 unidades e 10 pedidos.

---

### 13. Um ambiente de testes por checkout

**Entregas:** [#24](https://github.com/diashelter/laravel-module-ecomerce/pull/24) e [#25](https://github.com/diashelter/laravel-module-ecomerce/pull/25) (17/10).

**Situação e problema.** Em 17/10, duas suítes rodaram ao mesmo tempo em `git worktree` diferentes e quebraram uma à outra: `deadlock detected` e `relation "migrations" does not exist`. Todos os checkouts usavam o mesmo projeto Compose e o mesmo banco `ecommerce_testing`, e o `RefreshDatabase` roda `migrate:fresh`: uma suíte apagava as tabelas da outra no meio. Com várias pessoas (ou vários agentes de IA) trabalhando em paralelo, cada um no seu worktree, isso deixava de ser raro.

**O que foi feito.** O `make test` reconhece um worktree pelo git (o `git rev-parse --git-dir` de um worktree termina em `.git/worktrees/<nome>`) e roda a suíte num container avulso que monta o `backend/` daquele worktree, no banco `ecommerce_testing_<nome>`, criado na primeira execução. Depois, a mesma regra passou a valer para o vhost do RabbitMQ (capítulo 14) e para o banco do MongoDB (capítulo 18). O `make pint`, o `make shell`, o `make artisan` e os alvos do frontend também abrem o código do worktree.

**Por que assim.** Um teste que depende de estado compartilhado com outro processo é um teste instável, e um teste instável ensina o time a ignorar falhas. O worktree é reconhecido sem nenhuma variável a definir, então o caminho certo é o caminho padrão.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens |
| --- | --- | --- |
| Um projeto Compose por worktree | Isolamento total | Duplica banco, Redis, Nginx e frontend; as portas publicadas colidem; cada worktree precisaria do próprio `make setup` |
| `--parallel` do Laravel | Divide uma suíte em processos | Os bancos derivam do mesmo nome, então duas suítes ainda disputariam os mesmos |
| SQLite em memória | Rápido e isolado por processo | O projeto usa recursos do PostgreSQL (`lockForUpdate`, `to_char`, índices parciais): o teste não provaria o que roda em produção |
| Testcontainers (um banco descartável por execução) | Isolamento perfeito | Sobe um banco a cada execução; mais lento |
| **Um banco, um vhost e um banco do MongoDB por checkout** (escolhida) | Usa a pilha que já está no ar, sem configuração manual | Os bancos de worktrees removidos ficam até alguém apagá-los (`make test-db-drop`) |

**Sinal para aplicar.** Testes que passam sozinhos e falham juntos; times ou agentes trabalhando em paralelo na mesma máquina ou no mesmo CI; um banco de testes compartilhado.

---

### 14. Mensageria plugável: feature flag, Horizon e RabbitMQ

**Entrega:** [#26](https://github.com/diashelter/laravel-module-ecomerce/pull/26) (HEL-13, 18/10 e 19/10), design em [.design/event-transport-flag.md](../.design/event-transport-flag.md).

**Situação e problema.** O outbox (capítulo 9) deixou a publicação atrás de uma porta, o `EventTransport`, com uma única implementação: a fila do Laravel no Redis. Ele prometeu que um segundo transporte entraria sem mudar casos de uso, eventos nem consumidores, e essa promessa nunca tinha sido testada. Os consumidores só existiam como jobs do Laravel, e não havia como comparar o Redis com o RabbitMQ no mesmo código. A fila **não** era o gargalo: o p95 da espera até o pedido ficar pagável era 2.035 ms, exatamente a resolução da consulta de 2 s. Para comparar os transportes, a medição também precisava ficar mais fina.

**O que foi feito.**

- **Uma feature flag global, `event-transport`** (`redis`, o padrão, ou `rabbitmq`), guardada no banco pelo Laravel Pennant e trocada sem deploy por `make event-transport TO=rabbitmq`.
- **O `EventTransportSelector`** é o que o container entrega à porta: a cada publicação ele lê a flag e passa o envelope ao transporte dela. O relay limpa o cache do Pennant a cada ciclo para obedecer a uma troca sem reiniciar.
- **O `RabbitMqEventTransport`** publica **uma** mensagem por evento numa exchange `topic` (`domain_events`) e só retorna depois da **confirmação do broker** (*publisher confirms*). O broker copia a mensagem para a fila de cada consumidor.
- **Cada consumidor tem um nome estável** (`ordering.mark_order_as_paid`), dado na inscrição, que dá nome à fila dele. A fila é `quorum`, com limite de 2 reentregas e uma **fila de mortas** (DLQ) por consumidor.
- **O `event-consumer`** (`php artisan events:consume`) lê as filas, confirma depois do `handle()` e devolve a entrega com `basic_reject` e requeue quando ele falha. As regras de entrega moram numa classe só, o `EventDelivery`, usada pelos dois transportes.
- **O Horizon** substituiu o `queue:work`, com o painel aberto só ao papel `admin`.
- **O k6 passou a ler o pedido a cada 250 ms** e ganhou o parâmetro `TRANSPORT`.

**Exemplo.** O seletor ([`EventTransportSelector`](../backend/app-modules/messaging/src/Core/EventTransportSelector.php)) e os argumentos de cada fila ([`RabbitMqTopology`](../backend/app-modules/messaging/src/Core/RabbitMq/RabbitMqTopology.php)):

```php
final class EventTransportSelector implements EventTransport
{
    public function publish(EventEnvelope $envelope): void
    {
        $value = $this->flag->current(); // lida a cada publicação: a troca vale no ciclo seguinte

        $this->container->make($value === EventTransportFlag::RABBITMQ ? RabbitMqEventTransport::class : QueueEventTransport::class)
            ->publish($envelope);
    }
}
```

```php
$channel->queue_declare($consumerName, false, true, false, false, false, new AMQPTable([
    'x-queue-type' => 'quorum',                         // replicada, segura contra a queda de um nó
    'x-delivery-limit' => self::DELIVERY_LIMIT,         // 2 reentregas: na terceira falha, vai para a fila de mortas
    'x-dead-letter-exchange' => self::DEAD_EXCHANGE,    // domain_events.dead
    'x-dead-letter-routing-key' => $consumerName,       // a entrega morta volta só para este consumidor
    'x-dead-letter-strategy' => 'at-least-once',
    'x-overflow' => 'reject-publish',
]));
```

**O que a medição mostrou.** Com a vitrine e o `POST /api/orders` iguais dentro do ruído, o p95 da espera até pagar caiu de cerca de **1,38 s no Redis para cerca de 0,56 s no RabbitMQ**. A causa não foi medida; a hipótese é o `sleep` de 1 s do supervisor do Horizon com a fila vazia, contra a entrega por *push* do consumidor do RabbitMQ.

**Por que assim.**

- **A flag fica no PostgreSQL, e não no Redis:** com o Redis fora, ninguém leria nem trocaria uma flag guardada nele. Uma variável de ambiente exigiria reiniciar o relay.
- **O Pennant guarda a flag em memória até o fim da requisição**, e um processo contínuo como o relay nunca termina a "requisição": por isso o relay limpa esse cache a cada ciclo.
- **`basic_reject`, e não `basic_nack`:** desde o RabbitMQ 4.3, o `nack` com requeue não conta para o limite de entregas das filas `quorum`, e uma mensagem envenenada ficaria em laço para sempre.
- **O produtor também declara a topologia antes de publicar:** uma mensagem publicada antes de a fila do assinante existir é descartada sem erro. O padrão dos tutoriais, em que só o consumidor declara a fila, perderia a primeira mensagem depois de um deploy.
- **O nome da fila é o nome estável do consumidor, nunca a classe PHP:** os namespaces iam mudar (capítulo 15), e uma fila nomeada pela classe ficaria órfã, com mensagens dentro.
- **Uma entrega morta volta só ao consumidor dela:** devolvê-la pela exchange a entregaria de novo a todos os consumidores do evento.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Variável de ambiente | Simples | Exige reiniciar os processos | Configuração que muda com deploy |
| Flag no Redis | Leitura rápida | Indisponível justamente quando o Redis cai | Flags que não controlam o Redis |
| **Flag no banco com o Pennant** (escolhida) | Troca sem deploy, auditável, funciona com o Redis fora | Leitura a cada ciclo; o cache em memória precisa ser limpo em processos longos | Uma chave de troca (*kill switch*) de infraestrutura |
| Serviço de flags (LaunchDarkly, Unleash, Flagsmith) | Porcentagem, segmentação, interface, auditoria | Dependência externa e custo | Lançamentos graduais por usuário, experimentos A/B |
| Driver de fila RabbitMQ para o Laravel | Pouca mudança | Os consumidores continuam jobs do Laravel, o contrário do objetivo | Só trocar o broker de jobs |
| Kafka | Log persistente, *replay*, partições com ordem, consumidores em grupo, vazão altíssima | Operação mais pesada; sem fila de mortas nem atraso nativos | Muitos consumidores, reprocessar o histórico, streaming de dados |
| Redis Streams | Já existe no projeto, grupos de consumo | Persistência e replicação mais frágeis que um broker dedicado | Volume moderado com Redis já operado |
| SQS/SNS ou Google Pub/Sub (gerenciados) | Sem servidor para operar, escala automática | Dependência do provedor; latência e semântica próprias | Times pequenos na nuvem |

**Sinal para aplicar.** Você quer trocar uma peça de infraestrutura sem deploy, ou poder voltar atrás em segundos; precisa comparar duas implementações no mesmo código; consumidores em outras linguagens vão assinar os eventos; mensagens envenenadas travam uma fila.

**Para praticar.** Rode `make event-transport TO=rabbitmq`, faça um pedido e acompanhe o `make logs-consumer` e o painel em `http://localhost:15672`. Depois faça um consumidor lançar uma exceção de propósito e veja a entrega chegar à fila `<consumidor>.dead` depois de 3 tentativas; devolva-a com `make events-retry`.

---

### 15. Módulos como pacotes, e a regressão que a carga pegou

**Entregas:** [#27](https://github.com/diashelter/laravel-module-ecomerce/pull/27) e [#28](https://github.com/diashelter/laravel-module-ecomerce/pull/28) (HEL-12, 19/10 e 20/10), design em [.design/modular-layout.md](../.design/modular-layout.md).

**Situação e problema.** As fronteiras estavam verificadas, mas o módulo ainda não era uma **unidade**. Criar um módulo exigia editar à mão pelo menos cinco lugares globais: a lista de providers, o arquivo central de rotas (com controllers de 8 módulos), a pasta de migrations, a de factories e os padrões de rota do `AppServiceProvider`. Dentro de cada módulo, 15 pastas por tipo técnico não diziam o que era regra de negócio, o que era banco e o que era HTTP. E o `Shared` tinha 38 arquivos, 26 deles de mensageria: estava virando um depósito.

**O que foi feito.**

- **Cada módulo virou um pacote Composer** em `backend/app-modules/<módulo>` (namespace `Modules\<Módulo>`), descoberto pelo `internachi/modular`. O provider vem do `composer.json` do pacote, e um módulo novo nasce editando fora dele só o `composer.json` e o `composer.lock` da raiz.
- **Camadas dentro do pacote:** a fachada (`Contracts`, `Events`) ao lado de três camadas privadas, `Core` (casos de uso, regras, consumidores, value objects), `Persistence` (models, repositórios, consultas) e `Http`. A direção é `Http` → `Core` → `Persistence`, cobrada pelo `ModuleLayersTest`.
- **O `Messaging` saiu do `Shared`** e virou um módulo, com o registro de inscrições como interface.
- **As rotas e as migrations foram para o módulo dono**, com os mesmos nomes de arquivo, e o arquivo central de rotas deixou de existir.
- **Um critério para interfaces:** uma interface só existe como fronteira entre módulos ou como porta com uma segunda implementação ou um duplo de teste real. Nenhuma das 19 de então saiu.

**A regressão.** A primeira rodada do `load` depois da mudança reprovou: **p95 da vitrine de 272,8 ms**, contra **24,8 ms** no `main` medido logo antes na mesma máquina. A causa: sem o cache de módulos, a biblioteca procura os pacotes no sistema de arquivos a cada boot da aplicação, e o PHP-FPM faz um boot por requisição. Numa medida isolada, a mediana por requisição era 54,4 ms sem o cache e 16,2 ms com ele. A biblioteca grava o cache num caminho fixo, que a pilha de carga não pode escrever (ele seria lido pela API de dev), então o `AppServiceProvider` passou a religar o cache ao caminho de `APP_MODULES_CACHE`:

```php
// AppServiceProvider::register(): o caminho do cache de módulos segue APP_MODULES_CACHE,
// como o APP_CONFIG_CACHE do framework
$this->app->singleton(Cache::class, fn (Application $app) => new Cache(
    $app->make(Filesystem::class),
    Env::get('APP_MODULES_CACHE') ?: $app->bootstrapPath('cache/app-modules.php'),
));
```

Com o cache, a vitrine voltou a **24,9 e 24,1 ms** de p95 em duas rodadas. Em produção, `php artisan modules:cache` entra no deploy, junto dos outros caches. ([`AppServiceProvider`](../backend/app/Providers/AppServiceProvider.php))

**Por que assim.**

- **O nome do arquivo de migration é uma porta de mão única:** a tabela `migrations` guarda o nome, e renomear reexecutaria o SQL num banco já migrado. Os arquivos foram movidos com `git mv`, sem mudar nome nem conteúdo, e um teste congela os nomes.
- **Os padrões de UUID das rotas ficam no `register()` do provider do módulo dono**, e não no `boot()`: o spike mostrou que uma rota copia os padrões globais quando é criada, e a biblioteca cria as rotas antes do `boot()`.
- **Esvaziar a fila do Redis antes do deploy:** os jobs `DeliverEvent` já enfileirados guardam a classe do consumidor com o namespace antigo. O RabbitMQ não foi afetado, porque as filas usam o nome estável do consumidor (capítulo 14). É por decisões assim que a ordem das entregas importa.
- **A lição de desempenho:** toda mudança estrutural pode ter um custo que nenhum teste funcional pega. Foi a linha de base do capítulo 12 que transformou um "parece mais lento" num número, numa causa e numa correção.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Manter `App\Modules` e cada provider carregando as próprias rotas e migrations | Sem biblioteca, sem `composer install` por checkout, sem cache de módulos | Sem descoberta automática; o registro do provider continua na raiz | Poucos módulos, sem previsão de crescer |
| **Pacotes Composer locais com `internachi/modular`** (escolhida) | Descoberta automática, convenção de pacote, gerador (`make:module`) | Um `composer install` a cada checkout e o cache de módulos em produção | Monólito modular com vários módulos |
| `nwidart/laravel-modules` | Módulos ligados e desligados em tempo de execução | Mais convenções próprias | Plataformas com módulos opcionais por cliente |
| Repositórios separados, um pacote versionado por módulo | Versões e donos independentes | Mudanças entre módulos viram vários PRs e releases | Times diferentes donos de cada módulo |

**Sinal para aplicar.** Toda funcionalidade nova edita arquivos da raiz; a pasta "shared" ou "common" só cresce; dentro de um módulo não dá para distinguir regra, banco e HTTP; criar um módulo é uma receita de vários passos manuais.

**Para praticar.** Rode `make artisan CMD="modules:list"`. Depois meça você mesmo: `make artisan CMD="modules:clear"` e compare o tempo de algumas requisições à vitrine com e sem o cache (no dev, o cache fica desligado por padrão).

---

### 16. Idempotência dos consumidores

**Entrega:** [#29](https://github.com/diashelter/laravel-module-ecomerce/pull/29) (HEL-16, 20/10), design em [.design/event-idempotency.md](../.design/event-idempotency.md).

**Situação e problema.** A entrega "ao menos uma vez" é uma decisão fixa do projeto: o relay pode publicar de novo, o `events:consume` devolve a mensagem com requeue, e um `queue:retry` pode entregar dias depois. Então toda mensagem pode chegar mais de uma vez. A auditoria dos seis pontos assíncronos mostrou que os quatro consumidores do Ordering eram idempotentes, por uma escrita condicional em `orders`, mas o Fulfillment não era: cada reentrega do `OrderPaid` gravava outro `DeliveryScheduled` (com a data recalculada) e enfileirava outro `DeliverOrder`, e cada retry do `DeliverOrder` gravava outro `OrderDelivered`. A tabela `orders` ficava certa, porque o Ordering absorvia as duplicatas, mas as mensagens e os jobs se multiplicavam em cascata. O README prometia "os consumidores são idempotentes", e era falso. Número que decidiu: **2 de 6 pontos sem guarda e nenhum teste que reentregasse a mesma mensagem pelo caminho real**.

**O que foi feito.**

- **O Fulfillment ganhou uma linha por pedido**, na tabela `deliveries` (`order_id` único, a primeira data prevista e o `delivered_at`): a **chave natural** que ele não tinha.
- **Só a escrita que mudou uma linha anuncia o evento, na mesma transação**: o agendamento grava com `INSERT ... ON CONFLICT DO NOTHING`, e a entrega com `UPDATE ... WHERE delivered_at IS NULL`.
- **O `DeliverOrder` é enfileirado depois do commit e pode se repetir**: uma falha ao enfileirar é reparada pela reentrega do `OrderPaid`, e um job duplicado não faz nada.
- **A regra virou escrita e tem prova:** todo consumidor declara a sua chave natural e ganha um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery` (`ConsumerRedeliveryTest`), conferindo que o banco e o outbox não mudam na segunda.

**Exemplo.** As duas formas de chave natural do projeto, a transição condicional ([`OrderRepository`](../backend/app-modules/ordering/src/Persistence/Repositories/OrderRepository.php)) e a linha única ([`ScheduleDeliveryUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/ScheduleDeliveryUseCase.php)):

```php
// Ordering: a transição só acontece a partir do status esperado. Repetida ou fora de ordem, não faz nada.
public function transitionStatus(Order $order, OrderStatus $from, OrderStatus $to): bool
{
    $updated = $this->query()
        ->whereKey($order->getKey())
        ->where('status', $from)
        ->update(['status' => $to, 'updated_at' => now()]);

    return $updated === 1;
}
```

```php
// Fulfillment: só a entrega que criou a linha anuncia a data, na mesma transação.
$scheduled = DB::transaction(function () use ($orderId, $estimatedOn): bool {
    if (! $this->deliveries->schedule($orderId, $estimatedOn)) { // INSERT ... ON CONFLICT DO NOTHING
        return false;
    }

    $this->outbox->record(new DeliveryScheduled($orderId, $estimatedOn));

    return true;
});

// Enfileirado depois do commit: se falhar, a reentrega do OrderPaid repara. Repetido, é um no-op.
DeliverOrder::dispatch($orderId)->delay(now()->addSeconds(config('shop.delivery_delay_seconds')));
```

**Por que assim.**

- **A guarda é a linha do próprio Fulfillment, e não o status do pedido:** perguntar ao Ordering seria uma leitura entre módulos com corrida (a data chega ao pedido de forma assíncrona).
- **Nenhum inbox genérico.** Um inbox (a tabela de mensagens já processadas por consumidor) só se paga com um consumidor que não tem chave natural, como um que envia e-mail ou chama uma API externa. Hoje nenhum tem; se um aparecer, o inbox volta como uma decisão nova, nunca como atalho.
- **A ordem das operações importa:** gravar a mensagem de saída antes do estado que a justifica, ou gravar o estado e perder o disparo do trabalho seguinte, são as duas falhas clássicas. Estado e evento juntos na transação; o trabalho seguinte, repetível, depois do commit.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| **Chave natural** (escrita condicional ou linha única) (escolhida) | Sem tabela extra; o banco garante | Exige pensar a chave de cada consumidor | Sempre que o efeito tem uma chave natural |
| Inbox (`processed_messages(consumer, message_id)` único) | Genérico, serve a qualquer consumidor | Uma escrita e uma tabela a mais, retenção, e não cobre o retry de um job | Efeitos externos sem chave (e-mail, webhook, cobrança) |
| Deduplicação por id de mensagem no Redis, com TTL | Barata | Não é transacional com o efeito; some com o Redis | Efeitos baratos e tolerantes |
| Chave de idempotência na API HTTP (`Idempotency-Key`) | Protege o clique duplo e o retry do cliente | Armazenar e expirar as respostas | `POST` que cria recursos ou cobra (ficou de fora: o clique duplo em `POST /api/orders`) |
| "Exatamente uma vez" do Kafka (transações) | Garantia dentro do Kafka | Não vale para efeitos fora dele (banco, e-mail) | Pipelines de stream Kafka para Kafka |

**Sinal para aplicar.** E-mails, cobranças ou registros duplicados; um consumidor que "normalmente" roda uma vez; retries que repetem efeitos colaterais; uma promessa de idempotência sem um teste que entrega a mesma mensagem duas vezes.

**Para praticar.** Escreva um consumidor que envia um e-mail (pode ser um log) quando o pedido é entregue e responda: qual é a chave natural? Se não houver, desenhe a tabela de inbox e o teste de reentrega.

---

### 17. Módulos isolados: a porta do consumidor e o 503

**Entrega:** [#30](https://github.com/diashelter/laravel-module-ecomerce/pull/30) (20/10 e 21/10), design em [.design/module-isolation.md](../.design/module-isolation.md).

**Situação e problema.** Cada módulo era um pacote, mas não rodava sem os outros. Dos 114 arquivos de teste, 68 usavam de 2 a 8 módulos; 112 linhas `use` ligavam módulos de negócio em 14 pares; 6 chaves estrangeiras apontavam para tabelas de outros módulos; e o model `Customer` do Ordering lia a tabela `customers` do Identity. Removido um módulo, os outros deixavam de carregar ou de migrar. O objetivo desta entrega foi provar, num piloto, que um módulo pode ser uma unidade completa.

**O que foi feito (o piloto Payment ↔ Ordering).**

- **O Payment declara o que precisa numa porta própria**, no próprio vocabulário: `OrdersToPay`, que devolve um `OrderToPay` com o id, o cliente, o total e **se o pedido aguarda pagamento**. O status completo do pedido não atravessa a fronteira.
- **O pacote `Integration`** liga a porta ao contrato `PayableOrders` do Ordering, com um adaptador. É o único pacote que usa mais de um módulo de negócio, e só pelas fachadas deles.
- **Sem o Ordering instalado, a porta responde `503 SERVICE_UNAVAILABLE`** antes de cobrar ou gravar qualquer coisa: nenhuma resposta parcial, nenhum dado inventado.
- **O Ordering lê o `payment.payment_approved` com uma classe própria** (`Core/InboundEvents/PaymentApproved`), com o mesmo nome, versão e chaves do payload. Um teste prova que cada leitora lê o que a produtora grava, no lugar do compilador.
- **`payments.order_id` perdeu a chave estrangeira** (a única exceção à regra de nunca editar uma migration executada, mais uma migration nova que apaga a chave nos bancos já migrados).
- **Os testes de 5 módulos foram para dentro deles**, e uma prova de remoção roda a suíte de cada módulo numa cópia sem os outros módulos de negócio.

**Exemplo.** A porta do consumidor, a composição e a porta indisponível ([`OrdersToPay`](../backend/app-modules/payment/src/Contracts/OrdersToPay.php), [`IntegrationServiceProvider`](../backend/app-modules/integration/src/Providers/IntegrationServiceProvider.php), [`OrdersToPayFromOrdering`](../backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php)):

```php
// Payment: o que ele precisa saber do pedido, na língua dele
interface OrdersToPay
{
    public function findForPayment(string $customerId, string $orderId): ?OrderToPay;
}
```

```php
// Integration: liga a porta ao dono, ou a uma porta que responde 503 quando o dono não está instalado
if (interface_exists(OrdersToPay::class)) {
    $this->app->bind(OrdersToPay::class, interface_exists(PayableOrders::class)
        ? OrdersToPayFromOrdering::class
        : UnavailableOrdersToPay::class);
}
```

```php
// O adaptador traduz o pedido do Ordering no pedido que o Payment pede. Só o Ordering conhece os status dele.
return $order === null ? null : new OrderToPay(
    $order->id,
    $order->customerId,
    $order->totalCents,
    $order->status === OrderStatus::AwaitingPayment,
);
```

**Por que assim.**

- **A porta é do consumidor.** A regra do capítulo 6 ("quando o fornecedor já depende do consumidor, o consumidor define o contrato") virou a regra geral. É o padrão *Anti-Corruption Layer* do DDD: o modelo de um contexto não vaza para o outro, e a tradução mora num lugar só.
- **Falhar fechado, e cedo:** sem o dono, `503` antes de qualquer gravação. A autorização também falha fechada.
- **Sem chave estrangeira entre módulos:** o que a chave fazia vira uma conferência explícita (o pedido é conferido pela porta antes de qualquer linha ser gravada).
- **O formato publicado de um evento é o nome, a versão e as chaves**, não uma classe compartilhada. Sem a classe em comum, o compilador não pega uma chave renomeada, e o `OrderEventPayloadTest` toma o lugar dele.
- **A revisão do PR achou uma brecha:** os helpers de teste são funções globais, e chamar o helper de outro módulo não nomeia nenhuma classe, então a regra não o pegava. Ela passou a acusar as chamadas a helpers, com duas violações plantadas.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Contratos do fornecedor (o estado das fachadas, capítulo 7) | Menos código de tradução | O consumidor compila contra o fornecedor; o módulo não é removível | Monólito sem plano de extrair módulos |
| Um pacote de "eventos de integração" por módulo, compartilhado | O compilador confere o formato | Volta a haver um import entre módulos | Quando a checagem em tempo de compilação vale mais que a independência |
| **Porta do consumidor + pacote de composição** (escolhida) | Módulos removíveis e testáveis sozinhos; tradução explícita | Um adaptador por porta; o formato dos eventos provado por teste | Preparar módulos para virar serviços, ou times donos de módulos |
| Integração só por eventos, com réplicas locais dos dados | Módulos independentes até no banco | Consistência eventual em tudo; quebraria a reserva de estoque na transação do pedido | Serviços com bancos separados |
| Extrair o módulo para um serviço | Deploy e escala independentes | Rede, falhas parciais, sagas | Quando o módulo precisa de escala, time ou ciclo de deploy próprios |

**Sinal para aplicar.** Você pensa em extrair um módulo para um serviço; times diferentes vão cuidar de módulos diferentes; a suíte de um módulo demora porque sobe o sistema inteiro; você quer desligar um módulo sem derrubar os outros. Um módulo sem chave estrangeira para fora, sem imports de outros módulos, com os próprios testes e falando só por portas e eventos já está quase pronto para virar um serviço: o que sobra é infraestrutura (o padrão *Strangler Fig*).

**Para praticar.** Leia a [slice Fulfillment ↔ Ordering](../.design/module-isolation.md#fulfillment--ordering) do design, que ainda não foi feita, e desenhe as portas do Fulfillment no vocabulário dele.

---

### 18. CQRS com banco próprio: a vitrine no MongoDB

**Entrega:** [#32](https://github.com/diashelter/laravel-module-ecomerce/pull/32) (HEL-17, 27/10 e 28/10), design em [.design/storefront-read-model.md](../.design/storefront-read-model.md).

**Situação e problema.** O capítulo 10 separou leitura e escrita no código e deixou o modelo de leitura projetado como alternativa "até uma leitura ficar lenta ou um módulo ganhar banco próprio". Nenhuma das duas coisas tinha acontecido: no `load` de 20/10 a vitrine tinha p95 de 24 ms, e a ruptura vinha da fila do PHP-FPM, não do banco. A HEL-17 construiu o modelo de leitura **pelo que ele ensina**: um banco de leitura de outro tipo, a projeção assíncrona com o estado nos eventos, a consistência eventual, a reconstrução e uma API de leitura num processo próprio, atrás de um gateway. O momento foi escolhido para vir antes da slice Catalog e Inventory da isolação, que teria de reescrever a leitura de estoque da vitrine que esta entrega eliminou.

**O que foi feito.**

- **A versão do registro:** `products`, `categories` e `stocks` ganharam a coluna `version`. Toda escrita que muda a vitrine trava a linha, soma 1 na transação e grava pelo outbox o evento da linha travada.
- **Eventos com o estado inteiro da parte que mudou** (8 eventos: `catalog.product_*`, `catalog.category_*` e `inventory.stock_changed`), nunca uma diferença.
- **O projetor:** 8 consumidores do Catalog, os únicos que gravam nas coleções `catalog_products` e `catalog_categories` do MongoDB, aplicam uma parte só quando a versão dela é maior que a guardada. A exclusão deixa uma **lápide** (`deleted_version`).
- **A vitrine lê só o MongoDB**, num número fixo de leituras por página, e a disponibilidade continua decidida na leitura, pelo `ProductAvailability`.
- **O `api-read`:** o mesmo código e a mesma imagem do `api`, num processo sem credencial do PostgreSQL. O Nginx manda o `GET` e o `HEAD` da vitrine para ele; com o `api-read` ou o MongoDB fora, a resposta é `503`.
- **A reconstrução** (`make storefront-rebuild`) esvazia as coleções e republica pelo outbox o estado atual de cada registro.
- **A métrica `storefront_projection_lag`** no k6: o tempo entre editar um preço e a vitrine mostrá-lo.

**Exemplo.** A escrita que gera o evento com a versão, sem uma leitura extra ([`StockRepository`](../backend/app-modules/inventory/src/Persistence/Repositories/StockRepository.php)):

```php
// UPDATE ... RETURNING: a quantidade que sobrou e a versão nova voltam do mesmo comando
$row = DB::selectOne(
    'update stocks set quantity = quantity - ?, version = version + 1 where product_id = ? returning id, quantity, version',
    [$quantity, $productId],
);

$this->outbox->record(new StockChanged($row->id, $productId, $row->quantity, $row->version));
```

O projetor põe a regra de versão no **filtro** da gravação, e o próprio MongoDB decide ([`ProductDocumentRepository`](../backend/app-modules/catalog/src/Persistence/Repositories/ProductDocumentRepository.php), [`ConditionalUpsert`](../backend/app-modules/catalog/src/Persistence/ReadModel/ConditionalUpsert.php)):

```php
public function replaceStockPart(string $productId, array $stock): bool
{
    return $this->write(
        // só casa se não há parte de estoque ou se a guardada é mais velha
        ['_id' => $productId, '$or' => [['stock' => null], ['stock.version' => ['$lt' => $stock['version']]]]],
        ['$set' => ['stock' => $stock], '$setOnInsert' => ['product' => null, 'deleted_version' => null]],
    );
}
// ConditionalUpsert: updateOne com upsert. Se o filtro não casa, o upsert tenta inserir o _id e bate na chave
// duplicada: ou a versão guardada é mais nova (descarte), ou outra entrega inseriu o documento primeiro. Tenta
// uma vez de novo; a segunda chave duplicada só pode ser um descarte.
```

E o gateway ([`docker/nginx/default.conf`](../docker/nginx/default.conf)):

```nginx
location ~ ^/api/products(/[^/]+)?$ {
    error_page 418 = @laravel;
    if ($request_method !~ ^(GET|HEAD)$) {
        return 418;                      # outros métodos dessas rotas vão para o api
    }
    set $storefront_upstream api-read:9000;
    fastcgi_pass $storefront_upstream;   # resolvido a cada requisição: o Nginx sobe com o api-read parado
    fastcgi_intercept_errors on;
    error_page 502 504 = @storefront_unavailable;   # 503 com o corpo padrão de erro e um X-Request-ID
}
```

**Por que assim.**

- **O estado inteiro, e não a diferença:** um evento de estoque leva a quantidade gravada, nunca as unidades vendidas. Uma entrega repetida de "menos 2" baixaria o estoque duas vezes; uma entrega repetida de "quantidade 8" é inofensiva.
- **A versão vem da escrita, por registro, e só cresce.** A versão pelo relógio (`updated_at`) empataria com duas escritas no mesmo segundo; o id da mensagem não diz a ordem das transações; uma sequência global acoplaria escritas que não disputam a mesma linha.
- **O banco decide a versão,** numa gravação condicional. Duas entregas simultâneas terminam na versão maior sem nenhuma trava na aplicação, e a convergência não depende de existir um consumidor só.
- **A lápide** impede que um evento atrasado ressuscite um produto excluído.
- **Nenhum `is_available` gravado:** com o produto e o estoque chegando por eventos diferentes, o valor gravado ficaria preso à parte que chegou por último, e a regra seria escrita uma segunda vez.
- **Sem leitura de reserva no PostgreSQL:** a vitrine pode ficar alguns segundos atrás da escrita, e isso foi aceito porque quem decide a venda é o checkout, no PostgreSQL e com lock. O produto que acabou de esgotar ainda aparece disponível na vitrine por um instante, e o checkout responde `409`.
- **O Nginx como gateway,** porque ele já era a entrada única: são dois destinos fixos, e o limite de requisições mora no Laravel.

**Alternativas.**

| Alternativa | Vantagens | Desvantagens | Quando escolheria |
| --- | --- | --- | --- |
| Consulta exata no banco de escrita (o capítulo 10) | Sempre consistente, nada para sincronizar | Leitura e escrita disputam o mesmo banco | Enquanto a leitura não for medida como problema |
| Cache *cache-aside* no Redis sobre o PostgreSQL | Simples, muito rápido | Invalidação (o difícil), avalanche quando o cache expira | Leituras quentes e pouco variáveis |
| Réplica de leitura do PostgreSQL | Mesmo esquema e SQL, escala as leituras | Atraso de replicação; o formato da leitura continua o da escrita | Muitas leituras com o mesmo formato da escrita |
| Views materializadas | Leitura pronta sem código de projeção | Atualização em lote e cara | Relatórios que toleram minutos |
| Tabela de leitura com `jsonb` no próprio PostgreSQL | Sem banco novo | O mesmo banco da escrita (o estudo era outro tipo de banco) | Projeções simples sem infraestrutura nova |
| **MongoDB alimentado pelo outbox** (escolhida) | Documento no formato da tela; leitura independente do banco de escrita | Consistência eventual, reconstrução, mais um banco para operar | Leitura com formato e escala diferentes da escrita |
| Elasticsearch ou OpenSearch | Busca textual, filtros facetados, relevância | Operação pesada; também eventual | Busca de produtos de verdade |
| Eventos só com o id, e o projetor lendo a escrita | Eventos pequenos | Uma leitura do PostgreSQL no lado de leitura, e o projetor acoplado ao esquema | Quando o estado é grande demais para o evento |
| CDC (Debezium) das tabelas ou do outbox | Sem relay; latência baixa | Prende a projeção ao esquema, ou troca o relay de todos os eventos | Volume alto e equipe para operar o CDC |
| Uma aplicação de leitura separada atrás de um gateway dedicado | Deploy, versão e escala independentes | Mais um projeto e um gateway para operar | Times e ritmos diferentes para leitura e escrita |

**Sinal para aplicar.** A proporção de leituras para escritas é muito alta (um e-commerce lê dezenas de vezes mais do que escreve); o formato da leitura é muito diferente do da escrita (junta várias tabelas, precisa de busca e filtros); a leitura precisa escalar ou ficar no ar independentemente do banco de escrita; joins lentos medidos.

**Para praticar.** Com o ambiente no ar, pare o PostgreSQL (`docker compose stop db`) e abra a vitrine: ela continua respondendo, servida pelo `api-read`. Depois pare o MongoDB e veja o `503` em até 5 segundos, com o checkout ainda funcionando. Volte os dois e rode `make storefront-rebuild`.

---

### 19. O que já está desenhado e ainda não foi feito

Ler o que ficou de fora é tão útil quanto ler o que foi feito: mostra o próximo passo e o gatilho que o justificaria.

| Item | Onde está | Por que ainda não | O gatilho |
| --- | --- | --- | --- |
| **Observabilidade** com OpenTelemetry e Better Stack (HEL-8): um fluxo como um trace só, do pagamento à entrega, atravessando a fila; logs estruturados; métricas dos containers, do PostgreSQL, do Redis e do Nginx | [.design/observability-better-stack.md](../.design/observability-better-stack.md) (confirmado em 08/10) | Prioridade de estudo; nada trava sem ela | Qualquer tráfego real: sem traces, um fluxo assíncrono é uma caça ao `request_id` em linhas soltas |
| **As slices restantes da isolação**: prova de remoção no CI, Backoffice, Fulfillment ↔ Ordering, Catalog e Inventory, Customers e Identity | [.design/module-isolation.md](../.design/module-isolation.md) | O piloto provou a forma | Extrair um módulo, ou um time por módulo |
| **As 5 chaves estrangeiras entre módulos** e a projeção `Customer` do Ordering sobre a tabela do Identity | [análise de domínio](domain-analysis.md), problema 19 | Saem com a slice de cada travessia | O mesmo |
| **Inbox por consumidor** | capítulo 16 | Todo consumidor tem uma chave natural | O primeiro consumidor com efeito externo (e-mail, webhook) |
| **Espera crescente entre tentativas e um processo por consumidor** | [.design/event-transport-flag.md](../.design/event-transport-flag.md) | 5 consumidores PHP idempotentes, com o mesmo volume | Um consumidor lento que atrasa os outros, ou um efeito externo com limite de taxa |
| **Idempotência do `POST /api/orders`** (o clique duplo) | [.design/event-idempotency.md](../.design/event-idempotency.md) | Não é evento assíncrono | Reclamações de pedido duplicado; retries do cliente |
| **Gateway real com estado pendente e chave de idempotência**; Pix e webhook | [.design/fake-payment-gateway.md](../.design/fake-payment-gateway.md) | Nenhum gateway real planejado | Cobrar dinheiro de verdade |
| **Ler duas versões de um evento ao mesmo tempo** | README, seção de eventos | Só a versão atual de cada evento é aceita | O primeiro payload alterado com mensagens em trânsito |
| **Limite de aprovação do `storefront_projection_lag`** | [load-tests/README.md](../load-tests/README.md) | A primeira série só registra | Duas rodadas aceitas da série nova |

---

## Parte 4. Como perceber que a arquitetura precisa evoluir

Arquitetura não evolui por calendário nem por moda: evolui quando um sinal aparece. O trabalho de quem decide é reconhecer o sinal cedo, medir, e escolher o menor passo que o resolve, sabendo qual é o passo seguinte.

### 4.1 Os sinais, por categoria

| Categoria | Sintoma | Como observar | Resposta típica | Neste projeto |
| --- | --- | --- | --- | --- |
| Acoplamento | Uma mudança pequena edita vários módulos; mudar um model quebra quem nem deveria conhecê-lo | `git log --stat` (arquivos e módulos por PR); contar os `use` entre módulos | Contratos por papel, fachadas, portas do consumidor | 90 de 102 referências entravam nas partes internas (capítulo 7) |
| Coesão | Uma palavra com dois significados; uma regra no módulo que só a consome | Análise de domínio, linguagem ubíqua | Contextos delimitados, mover a regra para o dono | `User` comprador e equipe; disponibilidade no Ordering (capítulos 1 e 10) |
| Confiabilidade | Processos presos num estado intermediário; retry que não resolve | Auditoria dos fluxos assíncronos; teste de caos (parar a fila) | Outbox, idempotência por chave natural | As três janelas de perda (capítulo 9); 2 de 6 consumidores sem guarda (capítulo 16) |
| Consistência | Estoque negativo, pagamento duplicado, total que não bate | Testes de concorrência (k6 `contention`), restrições no banco | Lock, índice único, `UPDATE` condicional, `CHECK` | O checkout e o índice único parcial de `payments` (capítulos 4 e 12) |
| Segurança | Ids enumeráveis, `403` que confirma a existência, cabeçalho forjado, corrida no limite | Revisão de segurança, testes de rajada | UUID, dono na busca, proxies confiáveis, contadores atômicos | Capítulos 8 e 11 |
| Desempenho | p95 subindo, ruptura cedo, fila crescendo | Linha de base de carga, perfis `stress` e `breakpoint` | Cache, modelo de leitura, réplicas, escala horizontal | A regressão de 10 vezes do cache de módulos (capítulo 15) |
| Forma da leitura | A tela junta muitas tabelas, precisa de busca e filtros, lê muito mais do que escreve | Proporção de leituras e escritas; consultas lentas | CQRS: consultas, depois modelo de leitura | A vitrine no MongoDB (capítulo 18) |
| Produtividade | Criar um módulo exige vários passos manuais; testes instáveis em paralelo | Tempo para a primeira entrega de uma pessoa nova; falhas intermitentes no CI | Pacotes por módulo, ambiente de teste isolado | Capítulos 13 e 15 |
| Operação | "Não sei o que aconteceu com o pedido X" | Tempo para diagnosticar um incidente | Observabilidade (traces, logs estruturados, métricas) | A HEL-8, desenhada (capítulo 19) |
| Organização | Times diferentes no mesmo código, com ritmos de deploy diferentes | Conflitos de merge, filas de deploy | Módulos isolados e, quando valer a pena, serviços | O piloto de isolação (capítulo 17) |

### 4.2 Dez heurísticas tiradas deste projeto

1. **Meça antes, e diga se é construção ou dor.** "Está lento" não é um requisito; "o p95 da vitrine passou de 25 para 273 ms" é.
2. **Mude primeiro o que vai virar contrato.** Ids, formatos de evento e superfícies públicas ficam caros depois que algo é construído em cima. Por isso o UUID veio antes do outbox, e as fachadas antes do outbox e do CQRS.
3. **Um passo por vez, com a suíte verde entre os passos.** A migração para módulos foi feita um módulo por vez; o isolamento começou por um piloto.
4. **Toda regra de arquitetura vira um teste, e você o vê falhar.** Uma regra que nunca falhou pode estar verificando nada (o `\{$namespace}` do capítulo 2).
5. **Escreva o gatilho da alternativa mais pesada.** "O inbox volta quando surgir um consumidor sem chave natural" impede tanto construir cedo demais quanto esquecer o próximo passo.
6. **Ponha a invariante no lugar mais baixo possível.** O `CHECK (quantity >= 0)`, o índice único parcial e o `UPDATE ... WHERE status = :from` seguram o que o código esqueceu.
7. **Prefira o mecanismo que torna o caminho errado impossível.** Privado por padrão, lista do que é permitido, `DELETE` restrito por padrão, gravar evento fora de transação lançando exceção.
8. **Registre a decisão, o porquê e o que foi descartado.** Os designs (`.design/`) são, na prática, ADRs: daqui a seis meses, ninguém lembra por que a flag mora no PostgreSQL.
9. **Desconfie de "exatamente uma vez" e de "nunca falha".** Projete para a falha: ao menos uma vez mais idempotência, timeouts curtos, `503` explícito.
10. **Saiba o custo de reverter.** O nome de uma migration, a topologia de um broker e o formato de um evento publicado são portas de mão única: cuidado redobrado nelas, velocidade no resto.

### 4.3 A escada de evolução de um backend

Cada degrau resolve um problema e cria custos novos. Suba quando o sinal aparecer, não antes.

```txt
Degrau 0  Monólito em camadas (Models, Services, Controllers)
            │  sinal: contextos misturados, mudanças que espalham
            ▼
Degrau 1  Monólito modular com fronteiras testadas (contratos, fachadas, testes de arquitetura)
            │  sinal: efeitos entre contextos, integração assíncrona, falhas parciais
            ▼
Degrau 2  Eventos pelo outbox, consumidores idempotentes, filas
            │  sinal: leituras pesadas ou com formato diferente da escrita
            ▼
Degrau 3  CQRS: consultas no código, depois modelos de leitura com banco próprio
            │  sinal: carga crescendo além de uma máquina
            ▼
Degrau 4  Escala horizontal sem estado, cache e CDN, réplicas, pooling, filas com mais workers
            │  sinal: times, ritmos de deploy ou necessidades de escala diferentes por módulo
            ▼
Degrau 5  Módulos isolados (portas, sem chave estrangeira, testes no módulo) e extração de serviços (Strangler Fig)
            │  sinal: volume ou latência global, um banco que não cabe numa máquina
            ▼
Degrau 6  Particionamento de dados (sharding), multi-região, arquitetura em células
```

Este projeto está entre os degraus 3 e 5: tem o outbox, os consumidores idempotentes, as consultas, um modelo de leitura e o primeiro módulo isolado, e ainda roda numa máquina.

### 4.4 Monólito modular ou microsserviços?

Extraia um serviço só quando houver **pelo menos um** destes motivos, medido e não suposto:

- uma parte precisa escalar muito diferente das outras (a vitrine com 100 vezes o tráfego do admin);
- um time precisa de um ritmo de deploy próprio, e a fila de deploy compartilhada atrasa todos;
- uma parte precisa de outra tecnologia (um motor de busca, um modelo de ML);
- uma falha numa parte não pode derrubar as outras, e isolar por processo dentro do monólito não basta;
- uma fronteira de dados exigida por lei ou contrato (dados de pagamento, por exemplo).

E faça antes o dever de casa que este projeto fez, porque é ele que torna a extração barata: o módulo sem chave estrangeira para fora, sem imports de outros módulos, com os próprios testes, falando por portas e eventos de formato estável, com consumidores idempotentes e com observabilidade. Sem isso, a extração gera um **monólito distribuído**: todos os custos de rede de um sistema distribuído e todo o acoplamento de um monólito.

---

## Parte 5. Escalar para milhões de requisições

Escalar é aumentar a capacidade **mantendo** a latência e a correção. Antes das técnicas, o vocabulário e a conta.

### 5.1 Vocabulário mínimo

- **Latência** é o tempo de uma requisição. Use **percentis**, nunca a média: o p95 de 30 ms diz que 95% das requisições levaram até 30 ms; a média esconde a cauda. Em sistemas grandes, a cauda (p99) é o que o usuário sente, porque uma página faz várias chamadas e basta uma lenta.
- **Vazão** (*throughput*) é quantas requisições por segundo o sistema completa.
- **Saturação** é quanto um recurso tem de trabalho esperando (fila do PHP-FPM, conexões do banco, mensagens na fila). Perto de 100% de uso, a espera cresce muito mais rápido que a carga: um recurso a 90% não está "com 10% de folga", está prestes a formar fila.
- **Lei de Little:** `L = λ × W`. O número de requisições em andamento (`L`) é a taxa de chegada (`λ`) vezes o tempo de cada uma (`W`). Ela explica a capacidade de qualquer pool.

**A Lei de Little aplicada a este projeto.** Cada processo do PHP-FPM atende uma requisição por vez. Com 16 processos (a pilha de carga) e um tempo de serviço de 25 ms, o teto teórico é `16 / 0,025 = 640` requisições por segundo, se a CPU acompanhar. Agora imagine que o MongoDB parou de responder:

| Tempo que cada requisição da vitrine espera | Vazão máxima com 16 processos |
| --- | --- |
| 25 ms (normal) | 640 req/s |
| 2 s (o timeout escolhido para o MongoDB) | 8 req/s |
| 30 s (o padrão do driver) | 0,5 req/s |

Com o padrão de 30 s, uma dependência lenta prende todos os processos do pool. Se a vitrine rodasse no mesmo pool do `api`, ela derrubaria também o checkout, que nem usa o MongoDB. Foi por isso que a HEL-17 baixou o timeout do MongoDB para 2 s (a vitrine responde `503` em até 5 s) e pôs a vitrine num processo próprio, o `api-read`, que funciona como um *bulkhead*: **um timeout curto e um pool separado são decisões de capacidade**, não só de experiência do usuário.

### 5.2 A conta de padeiro

"Milhões de requisições" assusta menos depois da conta. Um dia tem 86.400 segundos:

| Volume | Média | Pico (3 a 10 vezes a média num dia comum) |
| --- | --- | --- |
| 1 milhão por dia | 11,6 req/s | 35 a 116 req/s |
| 10 milhões por dia | 116 req/s | 350 a 1.160 req/s |
| 100 milhões por dia | 1.157 req/s | 3.500 a 11.600 req/s |
| 1 milhão por hora | 278 req/s | 830 a 2.780 req/s |
| 1 milhão por minuto | 16.667 req/s | (já é o pico de um evento grande) |

Num e-commerce, a maior parte do tráfego é leitura anônima (a mistura do k6 deste projeto é 75% vitrine, 20% compra e 5% equipe) e o pico é sazonal: uma Black Friday pode multiplicar o tráfego de um dia comum por dezenas. Daí a ordem natural das alavancas:

1. **Leitura** (a maior parte do volume): cache na borda, modelo de leitura, réplicas.
2. **Disputa na escrita** (o estoque de um produto disputado): o ponto em que a correção e a escala brigam.
3. **Vazão assíncrona** (filas e consumidores): para que o pico de pedidos não atrase pagamentos e entregas.

### 5.3 Onde este projeto quebraria primeiro, e o próximo passo para cada gargalo

O `breakpoint` mostrou que a primeira parede é a **fila do PHP-FPM** (16 processos numa máquina de desenvolvimento), não o banco. A tabela segue a ordem em que os gargalos costumam aparecer:

| Gargalo | Sintoma | Primeiro passo | Passo seguinte | Custo a observar |
| --- | --- | --- | --- | --- |
| **PHP-FPM** (um processo por requisição e um boot por requisição) | p95 sobe junto com os usuários simultâneos; fila no FPM | Dimensionar `pm.max_children` pela memória (memória livre dividida pela memória de um processo); opcache; os caches de config, rotas, eventos e **módulos** | Mais réplicas do `api` e do `api-read` atrás de um balanceador (já são sem estado: a sessão está no Redis); depois, o Laravel Octane (Swoole, RoadRunner ou FrankenPHP), que mantém a aplicação carregada entre requisições | No Octane, estado que vaza entre requisições e vazamento de memória |
| **Vitrine** (leitura anônima, a maior parte do tráfego) | Muitas leituras iguais | Cache HTTP na borda: `Cache-Control: public` com `stale-while-revalidate` e um CDN, porque a vitrine é igual para todos | Réplicas do MongoDB (*replica set*) com leitura nos secundários; paginação por cursor no lugar do `skip`; um motor de busca (OpenSearch) para texto e filtros | Conteúdo alguns segundos velho (já aceito pela consistência eventual); invalidação |
| **Conexões do PostgreSQL** | `too many connections`; latência na conexão | PgBouncer em modo *transaction pooling*: cada processo do PHP-FPM abre uma conexão, e 10 réplicas com 16 processos já são 160 conexões | Réplicas de leitura para o admin e o dashboard | No pooling por transação, cuidado com *prepared statements* e variáveis de sessão; na réplica, o atraso de replicação (ler o que acabou de escrever) |
| **Escrita no PostgreSQL** | Consultas lentas, I/O alto, tabelas grandes | `EXPLAIN ANALYZE`, índices, `preventLazyLoading` (já ligado em dev para pegar N+1) | Particionar `orders` e `outbox_messages` por data (apagar uma partição é mais barato que um `DELETE`); escala vertical; por último, particionar por cliente (*sharding*) | O sharding complica consultas entre clientes e transações entre partições |
| **Disputa pelo mesmo estoque** (o produto em promoção relâmpago) | Compras do mesmo produto em fila no lock; timeouts no checkout | `UPDATE` condicional atômico, que segura o lock da linha pelo menor tempo possível (exemplo abaixo) | Reserva no Redis com script Lua e criação do pedido assíncrona; sala de espera virtual; dividir o estoque do produto em várias linhas (*buckets*) | Reconciliar a reserva com o banco; expirar reservas não pagas |
| **Relay do outbox** (um processo, lotes de 100, polling de 500 ms) | Mensagens pendentes crescendo; atraso até o consumidor | Mais relays em paralelo (já é seguro com o `SKIP LOCKED`; a ordem entre mensagens é só a melhor possível, e nenhum consumidor depende dela) | CDC com Debezium, que lê o log do banco em vez de consultar a tabela | Operar a infraestrutura de CDC |
| **Consumidores e filas** | Fila crescendo, `order_payable_wait` subindo | Mais workers no Horizon; filas separadas por prioridade (o pagamento antes de um e-mail) | Um processo por consumidor (previsto no design da HEL-13); Kafka quando precisar de *replay*, ordem por partição e vazão muito alta | Ordem e reprocessamento ficam explícitos |
| **Limite de requisições** | Ataque de volume chegando ao PHP | Limite na borda (Nginx `limit_req`, WAF, CDN) antes do limite da aplicação | *Token bucket* por cliente, limites por conta | Falsos positivos atrás de um NAT corporativo |
| **Redis** (sessão, fila, cache e limitador na mesma instância) | Uma função satura e derruba as outras | Instâncias separadas por função (sessão e cache de um lado, fila de outro) | Redis com Sentinel ou Cluster, ou gerenciado | Mais peças para operar |

**Exemplos dos próximos passos** (nenhum deles está implementado; são exercícios):

Cache HTTP na vitrine, que é igual para todo visitante:

```php
// ProductController::index: o CDN pode servir a página por 30 s, e por mais 60 s enquanto busca a nova
return ProductResource::collection($page->withQueryString())
    ->response()
    ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
// Nunca em respostas que dependem da sessão: essas são "private, no-store", como as de erro já são.
```

Paginação por cursor (*keyset*), porque o `skip` lê e descarta todos os documentos anteriores: com 12 por página, a página 10.000 obriga o banco a percorrer quase 120 mil documentos antes de devolver 12. Com o cursor, toda página custa o mesmo:

```php
// em vez de 'skip' => ($page - 1) * $perPage, continue depois do último item que o cliente viu
$filter['$or'] = [
    ['product.name' => ['$gt' => $lastName]],
    ['product.name' => $lastName, '_id' => ['$gt' => $lastId]], // o _id desempata, como já acontece na ordenação
];
```

Baixa de estoque condicional, sem o `SELECT ... FOR UPDATE` separado:

```sql
-- O UPDATE trava a linha só durante ele mesmo e já confere a quantidade. Nenhuma linha devolvida: estoque insuficiente.
UPDATE stocks SET quantity = quantity - 1, version = version + 1
WHERE product_id = $1 AND quantity >= 1
RETURNING quantity, version;
```

Num pedido com vários itens, isso continua dentro de uma transação, na ordem de `product_id` (para não haver deadlock), e um item sem estoque desfaz a transação. O ganho é segurar cada lock por menos tempo; para um produto realmente disputado, o alívio de verdade é tirar a disputa do banco:

```lua
-- Reserva no Redis (KEYS[1] = estoque disponível do produto, ARGV[1] = unidades pedidas): atômica, sem lock no banco.
local left = tonumber(redis.call('GET', KEYS[1]) or '0')
if left < tonumber(ARGV[1]) then
    return -1 -- esgotado: responde 409 sem tocar no PostgreSQL
end
return redis.call('DECRBY', KEYS[1], ARGV[1])
-- O pedido é criado depois, por um consumidor, e a reserva expira se o pagamento não vier.
```

### 5.4 Uma estratégia de crescimento por estágios

Os números abaixo são ordens de grandeza para orientar a conversa, não promessas: o custo de cada requisição muda tudo, e só a medição diz em que estágio você está.

| Estágio | Ordem de grandeza | O que fazer |
| --- | --- | --- |
| 1 | Até centenas de req/s | Uma máquina bem configurada com esta pilha: opcache e todos os caches do Laravel, PHP-FPM dimensionado, timeouts curtos, backups com restauração testada e observabilidade |
| 2 | Milhares de req/s | Processos sem estado replicados atrás de um balanceador; CDN e cache HTTP para a vitrine; PostgreSQL gerenciado com réplica e PgBouncer; Redis separado por função; RabbitMQ em cluster de 3 nós (as filas `quorum` já são replicadas); workers que escalam pelo tamanho da fila |
| 3 | Dezenas de milhares de req/s | Modelos de leitura para toda leitura quente; motor de busca; particionamento de tabelas; CDC; Kafka para os fluxos de eventos; limite de requisições na borda; várias zonas de disponibilidade |
| 4 | Centenas de milhares de req/s | Serviços extraídos onde os motivos da seção 4.4 aparecerem; sharding; multi-região; arquitetura em células (cópias independentes da pilha, cada uma atendendo uma parte dos clientes, para limitar o raio de uma falha) |

### 5.5 Como medir a capacidade de verdade

- **Use os perfis certos.** `load` para a linha de base, `stress` para ver a degradação, `spike` para ver a reação a um salto (e o tempo de *autoscaling*), `soak` para vazamentos de memória e conexões, `breakpoint` para achar a parede.
- **Modelo aberto para capacidade.** Usuários reais não esperam a sua resposta para chegar; um teste com VUs fixos esconde a lentidão (*coordinated omission*).
- **Meça os recursos e os serviços.** O método USE (*utilization, saturation, errors*) para cada recurso (CPU, memória, conexões, disco) e o método RED (*rate, errors, duration*) para cada serviço.
- **Compare na mesma máquina, com a mesma configuração e os mesmos dados**, e só aceite uma linha quando duas rodadas concordarem (a regra dos 20% do projeto).
- **Teste num ambiente parecido com a produção** antes de prometer um número: o gerador de carga na mesma máquina da API disputa CPU com ela, como o README da carga avisa.

---

## Parte 6. Disponibilidade: continuar no ar quando algo falha

Disponibilidade não é "nunca falhar": é **falhar pouco, falhar pequeno e voltar rápido**. Em escala, alguma peça está sempre falhando; o desenho decide se o usuário percebe.

### 6.1 Medir antes de prometer: SLI, SLO e orçamento de erro

- **SLI** (indicador): o que você mede, do ponto de vista do usuário. Exemplo: a fração de `GET /api/products` que responde `2xx` em menos de 300 ms.
- **SLO** (objetivo): a meta para o SLI, numa janela. Exemplo: 99,9% em 30 dias.
- **SLA** (acordo): a promessa contratual, sempre mais frouxa que o SLO, com uma consequência se for quebrada.
- **Orçamento de erro**: o que sobra do SLO. Com 99,9%, são 43 minutos por mês. Enquanto houver orçamento, o time lança; quando ele acaba, o time prioriza estabilidade.

| Disponibilidade | Indisponível por mês (30 dias) | Por ano |
| --- | --- | --- |
| 99% | 7 h 12 min | 3 dias e 15 h |
| 99,5% | 3 h 36 min | 1 dia e 20 h |
| 99,9% | 43 min | 8 h 46 min |
| 99,95% | 22 min | 4 h 23 min |
| 99,99% | 4 min 19 s | 53 min |
| 99,999% | 26 s | 5 min 15 s |

Cada nove a mais custa caro: a partir de 99,99%, nenhuma intervenção humana cabe no orçamento, e tudo precisa se recuperar sozinho.

### 6.2 Como este projeto já se defende

| Falha | O que acontece | O mecanismo |
| --- | --- | --- |
| Redis fora | Os pedidos continuam sendo criados; os eventos ficam pendentes no outbox, e o relay tenta de novo a cada 5 s | Outbox transacional (capítulo 9); a flag de transporte mora no PostgreSQL e pode trocar para o RabbitMQ |
| RabbitMQ fora (flag em `rabbitmq`) | As mensagens ficam pendentes; o pedido avança quando o broker volta ou quando a flag volta para `redis` | Confirmação do broker antes de marcar a mensagem (capítulo 14) |
| Um consumidor com defeito | 3 tentativas, depois o `failed_jobs` ou a fila de mortas; nada se perde, e o reprocessamento é por comando | `make queue-retry` e `make events-retry` |
| Uma mensagem entregue duas vezes | Nada muda na segunda entrega | Chave natural de cada consumidor (capítulo 16) |
| MongoDB fora | A vitrine responde `503` em até 5 s; o checkout, o pagamento e o admin continuam | Timeout de 2 s; o `api-read` é um processo separado (capítulo 18) |
| `api-read` fora | O Nginx responde `503` com o corpo padrão de erro e um `X-Request-ID` | Rota de erro no gateway |
| PostgreSQL fora | A escrita para; a vitrine continua no ar | O `api-read` não tem credencial do PostgreSQL |
| Módulo de pedidos ausente | O pagamento responde `503` sem cobrar nem gravar | A porta indisponível do `Integration` (capítulo 17) |
| Duas compras pela última unidade | Uma recebe `201`, a outra `409`, e o estoque nunca fica negativo | Lock em ordem de `product_id` e `CHECK (quantity >= 0)` |
| Duas aprovações do mesmo pedido | Uma vence; a outra recebe `409` | Índice único parcial em `payments` |
| Rajada de tentativas de login | Passa exatamente o limite | `throttle` atômico (capítulo 11) |
| Deploy que muda o formato de um evento | Sem perda, se as filas forem esvaziadas antes | Versão no envelope e o procedimento de deploy do README |

Repare que vários desses comportamentos foram **provados com o sistema falhando de propósito**: o Redis parado durante uma compra, o broker fora durante os testes, um consumidor que lança exceção. É a versão caseira do que a Netflix chamou de *chaos engineering*.

### 6.3 O que falta para produção

| Área | Prática | Observação para este projeto |
| --- | --- | --- |
| Redundância | Pelo menos 2 réplicas de cada processo sem estado, em zonas de disponibilidade diferentes, atrás de um balanceador com checagem de saúde | O Laravel já registra `/up`, mas o Nginx só encaminha `/api`, `/sanctum` e `/horizon` ao PHP; um balanceador precisaria de uma rota de saúde exposta. Separe *liveness* (o processo vive) de *readiness* (consegue atender: banco e cache respondem) |
| Dados | PostgreSQL com réplica e failover automático (Patroni ou um serviço gerenciado), backups com restauração no tempo (PITR) e **testes de restauração**; MongoDB e RabbitMQ em clusters de 3 nós; Redis com Sentinel ou gerenciado | Defina o RPO (quanto dado pode ser perdido) e o RTO (em quanto tempo volta) antes de escolher |
| Timeouts | Toda chamada de rede tem um timeout menor que o de quem chama | Hoje: 2 s no MongoDB, 5 s na confirmação do broker, 60 s no FastCGI. Um gateway de pagamento real precisaria do dele |
| Retries | Novas tentativas com espera crescente e aleatoriedade (*exponential backoff with jitter*), e só para operações idempotentes | Hoje as 3 tentativas são imediatas. Na fila do Laravel, um método `backoff()` no job resolve; no RabbitMQ, a espera pede uma fila de atraso (com TTL e *dead-letter* de volta) ou o plugin de mensagens atrasadas |
| Circuit breaker | Depois de N falhas seguidas, parar de chamar a dependência por um tempo e responder rápido | Para o gateway de pagamento real e qualquer API externa |
| Bulkhead | Pools separados para que uma parte saturada não derrube as outras | O `api-read` separado do `api` já é um; filas por prioridade seriam outro |
| Degradação graciosa | Servir um pouco velho em vez de nada; desligar o que não é essencial | `stale-while-revalidate` na vitrine; feature flags como chave de desligamento |
| Descarte de carga | Responder `503` com `Retry-After` quando saturado, em vez de deixar tudo lento; limitar o tamanho das filas | As filas já declaram `x-overflow: reject-publish` (exigido pelo *dead-lettering* at-least-once), mas não têm `x-max-length`. Com um limite, o broker recusaria a publicação e o relay deixaria a mensagem pendente no outbox, sem perder nada |
| Deploy seguro | *Blue/green*, *canary*, feature flags; migrations em duas fases (*expand/contract*: adicionar, migrar os dados, passar a usar e só depois remover) | Mudar um evento pede ler as duas versões durante a transição (ainda não existe; ver o capítulo 19) |
| Observabilidade e alertas | Alertar pela velocidade de consumo do orçamento de erro (*burn rate*), não por cada erro | A HEL-8 está desenhada |
| Procedimentos | *Runbooks* para cada alerta e exercícios de falha (*game days*) | `make events-failed`, `make events-retry` e `make queue-retry` já são passos de um runbook |

Um exemplo de nova tentativa com espera crescente num job do Laravel (exercício, não implementado):

```php
final class DeliverEvent implements ShouldQueue
{
    public int $tries = 3;

    /** Espera 1 s, depois 5 s, depois 30 s entre as tentativas, em vez de tentar de novo na hora. */
    public function backoff(): array
    {
        return [1, 5, 30];
    }
}
```

---

## Parte 7. Roteiro de estudo e de carreira

### 7.1 O que este projeto mostra sobre quem o construiu

O mercado costuma separar os níveis assim, e cada entrega deste projeto exercita um deles:

| Nível | O que se espera | Onde aparece aqui |
| --- | --- | --- |
| Pleno | Entregar funcionalidades com testes; usar transações e filas corretamente | Endereços e frete, pagamento fake, contas separadas |
| Sênior | Desenhar fronteiras; projetar para a falha (outbox, idempotência, timeouts); medir desempenho; escrever designs com alternativas; revisar concorrência | Fachadas, outbox, idempotência, k6, os designs em `.design/` |
| Staff | Decidir a ordem das mudanças pelas portas de mão única; transformar regras em testes de arquitetura; definir SLOs; planejar a evolução de vários sistemas e times | A sequência UUID → outbox → CQRS → pacotes → isolamento; o `ModuleBoundariesTest`; o plano de isolação |

### 7.2 Como contar essas histórias numa entrevista

Use o formato STAR (situação, tarefa, ação, resultado), **sempre com um número**:

- **A regressão de 10 vezes.** "Reorganizamos os módulos em pacotes. A linha de base de carga mostrou o p95 da vitrine indo de 25 para 273 ms. Medi o custo isolado: 54 ms por requisição sem o cache de módulos, 16 ms com ele. A biblioteca não permitia mudar o caminho do cache, então religuei o serviço dela a uma variável de ambiente. O p95 voltou a 24 ms, e o cache entrou no deploy."
- **A corrida no limite de requisições.** "O limite de 10 logins por minuto deixava passar 30 de 30 requisições simultâneas, porque o middleware conferia antes de contar. Mudei para contar antes de decidir, com o incremento e a validade num script Lua. Passaram exatamente 10 em 40 rajadas, e um teste com 30 processos paralelos impede a volta."
- **As três janelas de perda.** "Lendo os casos de uso, achei três pontos em que uma falha da fila travava o pedido para sempre, um deles com o cliente já cobrado. Implementei o outbox transacional: o evento é gravado na transação da mudança e publicado por um relay. Provei derrubando o Redis no meio de uma compra."
- **A idempotência auditada.** "A documentação prometia consumidores idempotentes. Auditei os seis pontos assíncronos: dois não eram, e nenhum teste reentregava uma mensagem. Dei ao módulo de entrega uma chave natural e criei um teste de reentrega por consumidor, que virou regra para os próximos."
- **O 403 que vazava informação.** "As rotas respondiam 403 ao pedido de outro cliente, o que confirmava que ele existia, e os ids eram sequenciais. Troquei por UUID v7, pus o dono na busca e passei a responder 404, igual a um id inexistente."

### 7.3 Perguntas de *system design* que este projeto ajuda a responder

| Pergunta | Onde está a base da resposta |
| --- | --- |
| Como não vender mais do que o estoque, com milhares de compradores? | Capítulos 12 e 18; seção 5.3 (lock, `UPDATE` condicional, reserva no Redis, sala de espera) |
| Como garantir que um evento não se perca? | Capítulo 9 (outbox) e capítulo 16 (idempotência) |
| Como lidar com mensagens duplicadas ou fora de ordem? | Capítulo 16 (chave natural) e capítulo 18 (versão e lápide) |
| Como implementar *rate limiting* distribuído? | Capítulo 11 |
| Como projetar a busca e a listagem de produtos para milhões de acessos? | Capítulo 18 e seção 5.3 (modelo de leitura, CDN, cursor, motor de busca) |
| Quando usar microsserviços? | Capítulo 17 e seção 4.4 |
| Como integrar com um gateway de pagamento? | Capítulo 4 (porta, idempotência, webhook) |
| Como fazer deploy sem quebrar mensagens em trânsito? | Capítulos 9 e 15 (versão do evento, esvaziar a fila, nomes estáveis) |
| Como saber quanto o sistema aguenta? | Capítulo 12 e seção 5.5 |

### 7.4 Um plano de estudo em 8 semanas

| Semana | Tema | Leitura | Prática neste repositório |
| --- | --- | --- | --- |
| 1 | DDD estratégico e monólito modular | *Domain-Driven Design* (Evans), partes de contexto delimitado; o repositório de Kamil Grzybek | Leia a [análise de domínio](domain-analysis.md) e desenhe o mapa de contextos sem olhar |
| 2 | Modelagem tática | *Implementing Domain-Driven Design* (Vernon), value objects e agregados | Capítulo 3; escreva um value object `Money` com rateio do frete entre os itens |
| 3 | Transações, locks e concorrência | *Designing Data-Intensive Applications* (Kleppmann), capítulo 7 | Rode o `contention` do k6; implemente o `UPDATE` condicional e compare |
| 4 | Mensageria confiável | microservices.io: *Transactional Outbox*, *Idempotent Consumer*, *Saga* | Capítulos 9, 14 e 16; derrube o Redis e o RabbitMQ no meio de compras |
| 5 | CQRS e modelos de leitura | Martin Fowler, *CQRS*; Kleppmann, capítulos 11 e 12 | Capítulo 18; acrescente um campo novo ao documento da vitrine, com evento, versão e reconstrução |
| 6 | Desempenho e testes de carga | Documentação do k6; método USE de Brendan Gregg | Rode `load`, `stress` e `breakpoint`; ligue o cache HTTP e meça de novo |
| 7 | Disponibilidade | *Release It!* (Nygard); o livro de SRE do Google, capítulos de SLO | Defina SLOs para a vitrine e o checkout; escreva um runbook para a fila de mortas |
| 8 | Evolução e decisões | *Building Evolutionary Architectures*; *Software Architecture: The Hard Parts* | Escreva uma ADR para a próxima slice da isolação e implemente a Fulfillment ↔ Ordering |

### 7.5 Exercícios práticos, do mais simples ao mais difícil

1. Acrescente o `Cache-Control` público à vitrine e meça o efeito no `load` com um cache na frente do Nginx.
2. Troque o `skip` da `StorefrontQuery` por paginação por cursor, mantendo o mesmo corpo de resposta para a primeira página.
3. Implemente o `backoff()` crescente no `DeliverEvent` e prove com um teste que a segunda tentativa espera.
4. Escreva um consumidor que envia um e-mail quando o pedido é entregue, com um inbox, e o teste de reentrega.
5. Implemente a chave de idempotência no `POST /api/orders` (o cabeçalho `Idempotency-Key`), com a resposta guardada por 24 horas.
6. Acrescente o PgBouncer à pilha de carga e compare o `stress` com e sem ele.
7. Suba duas réplicas do `api` atrás do Nginx na pilha de carga e rode o `breakpoint` de novo. Onde fica a nova parede?
8. Implemente a observabilidade da HEL-8 seguindo o design: um trace do pagamento até a entrega.
9. Faça a slice Fulfillment ↔ Ordering da isolação.
10. Escreva o design (com alternativas e portas de mão única) de um pagamento por Pix com webhook.

### 7.6 O checklist de quem decide arquitetura

Tirado do processo que este repositório segue em cada entrega:

- [ ] O problema está escrito, com o número que o comprova, ou declarado como construção.
- [ ] As portas de mão única estão nomeadas, e recebem o cuidado maior.
- [ ] A alternativa mais pesada está descrita, com o gatilho que a justificaria.
- [ ] Os critérios de aceite são verificáveis, e cada um tem uma prova.
- [ ] Toda regra nova de arquitetura foi vista falhando com uma violação de propósito.
- [ ] Alguém que não construiu verificou, e tentou quebrar as provas.
- [ ] O desempenho foi comparado com a linha de base, quando a mudança toca o caminho de uma requisição.
- [ ] A documentação descreve o código como ele ficou, no mesmo pull request.

---

## Glossário

| Termo | Significado |
| --- | --- |
| *Anti-Corruption Layer* (ACL) | Uma camada de tradução que impede o modelo de um contexto de vazar para outro (o adaptador `OrdersToPayFromOrdering`) |
| Ao menos uma vez (*at-least-once*) | Garantia de entrega em que a mensagem nunca se perde, mas pode chegar repetida; pede consumidores idempotentes |
| *Backpressure* | Mecanismo pelo qual um consumidor sobrecarregado faz o produtor desacelerar ou recusar |
| *Bulkhead* | Isolar recursos (processos, pools, filas) para que a falha de uma parte não afunde as outras |
| CDC (*Change Data Capture*) | Ler as mudanças do banco pelo log de transações, em vez de consultar tabelas |
| *Circuit breaker* | Parar de chamar uma dependência que está falhando, por um tempo, e responder rápido |
| Consistência eventual | A leitura reflete a escrita depois de um intervalo, e não no mesmo instante |
| Contexto delimitado (*bounded context*) | A fronteira dentro da qual um modelo e as suas palavras têm um significado só |
| CQRS | Separar o modelo de escrita (comandos) do modelo de leitura (consultas) |
| DLQ (fila de mortas) | A fila para onde vai a mensagem que falhou o número máximo de vezes |
| Escrita dupla (*dual write*) | Gravar em dois sistemas (banco e fila) sem atomicidade entre eles; o problema que o outbox resolve |
| *Feature flag* | Uma chave que muda o comportamento sem deploy |
| *Fitness function* | Um teste automático de uma característica da arquitetura (os testes de `Unit/Architecture`) |
| Idempotência | Repetir a operação não muda o resultado da primeira vez |
| IDOR | Acesso a um objeto de outra pessoa trocando o id na requisição |
| Inbox | Tabela de mensagens já processadas por consumidor, para deduplicar quem não tem chave natural |
| Inversão de dependência | O módulo de alto nível define a interface, e o de baixo nível a implementa |
| Lápide (*tombstone*) | A marca de que um registro foi excluído, guardada para recusar eventos atrasados |
| Lei de Little | `L = λ × W`: requisições em andamento são a taxa de chegada vezes o tempo de cada uma |
| Lock otimista | Gravar só se a versão não mudou desde a leitura (`WHERE version = ?`) |
| Lock pessimista | Travar a linha ao ler (`SELECT ... FOR UPDATE`), e os outros esperam |
| Modelo de leitura | Dados organizados para uma leitura, alimentados pelas mudanças da escrita |
| Monólito modular | Um deploy só, dividido em módulos com fronteiras explícitas |
| Outbox transacional | Gravar o evento na mesma transação da mudança e publicá-lo depois, por um relay |
| p95 / p99 | O tempo abaixo do qual estão 95% (ou 99%) das requisições |
| Porta de mão única | Uma decisão cara de desfazer |
| Portas e adaptadores (hexagonal) | O domínio fala com o mundo por interfaces (portas), implementadas por adaptadores trocáveis |
| Projetor | O consumidor que mantém um modelo de leitura a partir dos eventos |
| *Publisher confirms* | O broker confirma ao produtor que guardou a mensagem |
| Fila *quorum* | Fila do RabbitMQ replicada por consenso entre os nós, segura contra a queda de um deles |
| Réplica de leitura | Uma cópia do banco que só atende leituras, com algum atraso |
| *Saga* | Uma transação de negócio entre serviços, feita de passos locais com compensações |
| *Savepoint* | Um ponto dentro de uma transação para o qual é possível voltar sem desfazer tudo |
| *Sharding* | Dividir os dados entre vários bancos por uma chave |
| `SKIP LOCKED` | Ler linhas pulando as travadas por outra transação; permite vários relays em paralelo |
| SLI, SLO, SLA | O que se mede, a meta e o contrato de disponibilidade |
| *Snapshot* | A cópia de um dado no momento de um fato (o preço no item do pedido, o endereço no pedido) |
| *Strangler Fig* | Substituir um sistema aos poucos, desviando uma funcionalidade por vez |
| UUID v7 | Identificador de 128 bits cujos primeiros 48 bits são o instante da criação |
| Value object | Um objeto definido pelos valores, imutável e com a própria regra de validade |
| Vocabulário público | Os tipos que a fachada de um módulo expõe; o resto é privado |

---

## Referências

**Livros**

- Eric Evans, *Domain-Driven Design* (2003): contextos delimitados, linguagem ubíqua, *Anti-Corruption Layer*.
- Vaughn Vernon, *Implementing Domain-Driven Design* (2013): agregados, value objects, eventos de domínio.
- Martin Kleppmann, *Designing Data-Intensive Applications* (2017): transações, replicação, particionamento, streams.
- Chris Richardson, *Microservices Patterns* (2018): outbox, sagas, CQRS, consumidor idempotente.
- Neal Ford, Rebecca Parsons e Patrick Kua, *Building Evolutionary Architectures* (2017): *fitness functions*.
- Neal Ford, Mark Richards, Pramod Sadalage e Zhamak Dehghani, *Software Architecture: The Hard Parts* (2021): decompor e integrar, *trade-offs*.
- Michael Nygard, *Release It!* (2.ª edição, 2018): timeouts, *circuit breaker*, *bulkhead*.
- Betsy Beyer e outros, *Site Reliability Engineering* (Google, 2016): SLOs e orçamento de erro.

**Na web**

- [Transactional Outbox](https://microservices.io/patterns/data/transactional-outbox.html), [CQRS](https://microservices.io/patterns/data/cqrs.html) e [Saga](https://microservices.io/patterns/data/saga.html), em microservices.io.
- Martin Fowler, [CQRS](https://martinfowler.com/bliki/CQRS.html) e [Strangler Fig Application](https://martinfowler.com/bliki/StranglerFigApplication.html).
- Alistair Cockburn, [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/).
- Kamil Grzybek, [Modular Monolith with DDD](https://github.com/kgrzybek/modular-monolith-with-ddd).
- [O livro de SRE do Google](https://sre.google/sre-book/table-of-contents/) e o [livro de exercícios de SRE](https://sre.google/workbook/table-of-contents/).
- [RFC 9562](https://www.rfc-editor.org/rfc/rfc9562): os UUIDs, inclusive o v7.
- [Documentação do k6](https://grafana.com/docs/k6/latest/).
- [Quorum queues do RabbitMQ](https://www.rabbitmq.com/docs/quorum-queues).
- [O roteador de outbox do Debezium](https://debezium.io/documentation/reference/stable/transformations/outbox-event-router.html).
- [Requisições idempotentes na API da Stripe](https://docs.stripe.com/api/idempotent_requests).
- [OWASP: prevenção de IDOR](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html).
- Brendan Gregg, [o método USE](https://www.brendangregg.com/usemethod.html).
- [Lei de Little](https://en.wikipedia.org/wiki/Little%27s_law).

**Neste repositório**

- Os designs de cada entrega: [`.design/`](../.design/).
- Os planos, os critérios, as provas e as verificações: [`.specs/features/`](../.specs/features/).
- A [análise de domínio](domain-analysis.md), o [README](../README.md), o [README dos testes de carga](../load-tests/README.md) e o [AGENTS.md](../AGENTS.md).
