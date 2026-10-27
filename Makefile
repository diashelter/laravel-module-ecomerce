# Central place for the project's day-to-day commands.
# Every Laravel/npm command runs inside the proper container.

DC        = docker compose
EXEC_API  = $(DC) exec api
ARTISAN   = $(EXEC_API) php artisan
EXEC_DB   = $(DC) exec db

-include .env
APP_PORT    ?= 8080
DB_DATABASE ?= ecommerce
DB_USERNAME ?= ecommerce

# Every checkout of the repository shares the dev containers, whose bind mounts point at the main checkout.
# In a linked git worktree (its git dir is <repository>/.git/worktrees/<name>), the targets that read the
# checkout's code (test, test-filter, pint, shell, artisan, test-frontend, frontend-shell) run in a one-off
# container of the same service, which mounts the worktree's own backend/ or frontend/. The main checkout
# (and CI) execs into the dev containers. The other targets act on the dev environment and stay there.
WORKTREE      := $(shell git rev-parse --git-dir 2>/dev/null | sed -n 's|.*/worktrees/\([^/]*\)$$|\1|p')
MAIN_CHECKOUT := $(if $(WORKTREE),$(patsubst %/.git,%,$(shell git rev-parse --path-format=absolute --git-common-dir)))
CHECKOUT_RUN   = $(DC) $(if $(WORKTREE),run --rm --no-deps,exec)
CHECKOUT_API   = $(CHECKOUT_RUN) api

.DEFAULT_GOAL := help

.PHONY: help up down build rebuild ps logs logs-api logs-worker logs-relay \
	logs-consumer shell artisan worktree-env migrate migrate-fresh seed fresh optimize clear \
	db-shell db-reset db-dump db-restore storefront-rebuild \
	queue queue-restart queue-failed queue-retry event-transport events-failed events-retry \
	test test-filter test-prepare test-db-drop pint test-frontend npm-install frontend-shell setup env-files wait-api \
	load-test load-down

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ---------- Ambiente ----------

up: ## Inicia todos os containers
	$(DC) up -d

down: ## Para os containers
	$(DC) down

build: ## Reconstrói as imagens
	$(DC) build

rebuild: ## Reconstrói as imagens e inicia os containers
	$(DC) up -d --build

ps: ## Mostra o status dos containers
	$(DC) ps

logs: ## Mostra os logs principais (api, worker, relay, consumidor, nginx, frontend)
	$(DC) logs -f --tail=100 api queue-worker outbox-relay event-consumer nginx frontend

logs-api: ## Mostra os logs da API
	$(DC) logs -f --tail=100 api

logs-worker: ## Mostra os logs do worker de filas
	$(DC) logs -f --tail=100 queue-worker

logs-relay: ## Mostra os logs do relay do outbox
	$(DC) logs -f --tail=100 outbox-relay

logs-consumer: ## Mostra os logs do consumidor do RabbitMQ
	$(DC) logs -f --tail=100 event-consumer

## ---------- Laravel ----------

shell: worktree-env ## Abre um shell no container da API, com o backend/ deste checkout
	$(CHECKOUT_API) sh

artisan: worktree-env ## Executa um comando Artisan no backend/ deste checkout: make artisan CMD="route:list"
	$(CHECKOUT_API) php artisan $(CMD)

# A worktree has no backend/.env (it is not in git): it gets the main checkout's one, so it talks to the same
# db and redis. vendor/ is installed by the api container's entrypoint.
worktree-env:
	@test -z "$(WORKTREE)" || test -f backend/.env \
		|| { cp "$(MAIN_CHECKOUT)/backend/.env" backend/.env && echo "Copiado backend/.env de $(MAIN_CHECKOUT)"; }

migrate: ## Executa as migrations
	$(ARTISAN) migrate --force

migrate-fresh: ## Apaga todas as tabelas e executa as migrations
	$(ARTISAN) migrate:fresh --force

seed: ## Executa os seeders (e reconstrói a vitrine)
	$(ARTISAN) db:seed --force
	$(MAKE) storefront-rebuild

fresh: ## Recria o banco de desenvolvimento (migrate:fresh --seed) e reconstrói a vitrine
	$(ARTISAN) migrate:fresh --seed --force
	$(MAKE) storefront-rebuild

# The storefront is served from MongoDB, which the seeders and the factories do not write: this empties the read model
# and publishes the state of every category, product and stock through the outbox. The relay and the consumers
# (make up) project the messages, so the storefront fills in a few seconds.
storefront-rebuild: ## Reconstrói o modelo de leitura da vitrine (MongoDB) a partir do PostgreSQL
	$(ARTISAN) catalog:read-model-clear
	$(ARTISAN) catalog:republish
	$(ARTISAN) inventory:republish

optimize: ## Gera caches de configuração, rotas e eventos
	$(ARTISAN) optimize

clear: ## Limpa os caches da aplicação
	$(ARTISAN) optimize:clear

## ---------- Banco de dados ----------

db-shell: ## Abre o psql no container db
	$(EXEC_DB) psql -U $(DB_USERNAME) -d $(DB_DATABASE)

db-reset: fresh ## Recria completamente o banco (migrations + seeders)

db-dump: ## Cria um dump em storage-dumps/
	@mkdir -p storage-dumps
	$(EXEC_DB) pg_dump -U $(DB_USERNAME) -d $(DB_DATABASE) --clean --if-exists > storage-dumps/dump-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Dump salvo em storage-dumps/"

db-restore: ## Restaura um dump: make db-restore FILE=storage-dumps/dump.sql
	@test -n "$(FILE)" || (echo "Informe o arquivo: make db-restore FILE=storage-dumps/arquivo.sql" && exit 1)
	@test -f "$(FILE)" || (echo "Arquivo $(FILE) não encontrado" && exit 1)
	$(DC) exec -T db psql -U $(DB_USERNAME) -d $(DB_DATABASE) -v ON_ERROR_STOP=1 < $(FILE)

## ---------- Filas ----------

queue: ## Executa um worker em primeiro plano (além do container queue-worker)
	$(ARTISAN) queue:work redis --sleep=1 --tries=3 --verbose

queue-restart: ## Reinicia o Horizon, o relay e o consumidor do RabbitMQ (necessário após alterar jobs, consumidores ou o relay)
	$(ARTISAN) queue:restart
	$(ARTISAN) horizon:terminate

queue-failed: ## Lista os jobs que falharam
	$(ARTISAN) queue:failed

queue-retry: ## Reprocessa jobs falhos: make queue-retry (todos) ou make queue-retry ID=<uuid>
	$(ARTISAN) queue:retry $(if $(ID),$(ID),all)

# Which transport the outbox relay publishes through (the event-transport feature flag), without a deploy.
# The user is recorded in the log of the change.
event-transport: ## Mostra ou troca o transporte dos eventos: make event-transport [TO=redis|rabbitmq]
	$(ARTISAN) events:transport $(if $(TO),$(TO) )--by=$(USER)

events-failed: ## Lista as entregas mortas das filas do RabbitMQ
	$(ARTISAN) events:failed

events-retry: ## Devolve as entregas mortas aos consumidores: make events-retry (todas) ou make events-retry ID=<uuid>
	$(ARTISAN) events:retry $(ID)

## ---------- Testes ----------

# Each checkout tests on a database of its own, created on the first run: RefreshDatabase runs migrate:fresh,
# so two suites on one database break each other. The main checkout keeps ecommerce_testing; a worktree uses
# ecommerce_testing_<name>. Any checkout can pick another database: make test TEST_DB_DATABASE=<name>.
TEST_DB_DATABASE := $(or $(TEST_DB_DATABASE),ecommerce_testing$(if $(WORKTREE),_$(shell printf '%s' '$(WORKTREE)' | tr 'A-Z' 'a-z' | tr -c 'a-z0-9' '_' | cut -c1-45)))
# The RabbitMQ vhost and the MongoDB database of the checkout have the name of its test database, so two suites never
# see each other's queues or read model.
TEST_API          = $(CHECKOUT_RUN) -e DB_DATABASE=$(TEST_DB_DATABASE) -e RABBITMQ_VHOST=$(TEST_DB_DATABASE) -e MONGODB_DATABASE=$(TEST_DB_DATABASE) api
PEST              = sh -c 'php artisan config:clear --quiet && exec ./vendor/bin/pest "$$@"' pest
# The name goes unquoted into SQL, and the suite would wipe the development database. The check reads it
# from the environment, so no value can break its own quoting.
export TEST_DB_DATABASE
CHECK_TEST_DB     = printf '%s' "$$TEST_DB_DATABASE" | grep -Eqx '[a-z_][a-z0-9_]{0,62}' && [ "$$TEST_DB_DATABASE" != '$(DB_DATABASE)' ] \
	|| { echo "TEST_DB_DATABASE inválido: '$$TEST_DB_DATABASE'. Use minúsculas, dígitos e _ (até 63), e nunca o banco de desenvolvimento ($(DB_DATABASE))."; exit 1; }

test: test-prepare ## Executa todos os testes (Pest) no banco de testes deste checkout
	$(TEST_API) $(PEST)

test-filter: test-prepare ## Executa testes filtrados: make test-filter FILTER=CheckoutTest
	$(TEST_API) $(PEST) --filter="$(FILTER)"

# Creates the database and the RabbitMQ vhost when they are missing.
test-prepare: worktree-env
	@$(CHECK_TEST_DB)
	@printf '%s\n' "SELECT 'CREATE DATABASE $(TEST_DB_DATABASE) OWNER $(DB_USERNAME)' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$(TEST_DB_DATABASE)')\gexec" \
		| $(DC) exec -T db psql -U $(DB_USERNAME) -d $(DB_DATABASE) -q -v ON_ERROR_STOP=1
	@$(DC) exec -T -e VHOST=$(TEST_DB_DATABASE) rabbitmq sh -c 'rabbitmqctl -q list_vhosts --no-table-headers | grep -qx "$$VHOST" \
		|| { rabbitmqctl -q add_vhost "$$VHOST" && rabbitmqctl -q set_permissions -p "$$VHOST" "$$RABBITMQ_DEFAULT_USER" ".*" ".*" ".*"; }'
	@echo "Banco de testes: $(TEST_DB_DATABASE) (vhost do RabbitMQ: $(TEST_DB_DATABASE))"

test-db-drop: ## Apaga o banco, o banco do MongoDB e o vhost do RabbitMQ de testes deste checkout (o próximo make test os recria)
	@$(CHECK_TEST_DB)
	$(DC) exec -T db psql -U $(DB_USERNAME) -d $(DB_DATABASE) -c 'DROP DATABASE IF EXISTS $(TEST_DB_DATABASE) WITH (FORCE)'
	$(DC) exec -T -e TEST_DB=$(TEST_DB_DATABASE) mongodb sh -c 'mongosh --quiet -u "$$MONGO_INITDB_ROOT_USERNAME" -p "$$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "db.getSiblingDB(process.env.TEST_DB).dropDatabase()"'
	$(DC) exec -T -e VHOST=$(TEST_DB_DATABASE) rabbitmq sh -c 'if rabbitmqctl -q list_vhosts --no-table-headers | grep -qx "$$VHOST"; then rabbitmqctl -q delete_vhost "$$VHOST"; fi'

pint: worktree-env ## Verifica o estilo do backend/ deste checkout (Pint, sem corrigir)
	$(CHECKOUT_API) ./vendor/bin/pint --test

## ---------- Frontend ----------

# In a worktree, the one-off container mounts the worktree's frontend/ with the dev node_modules volume, which
# holds the dependencies of the last npm install (usually the main checkout's).
test-frontend: ## Executa os testes do frontend/ deste checkout (Vitest)
	$(CHECKOUT_RUN) frontend npm test

npm-install: ## Instala as dependências do frontend
	$(DC) run --rm --no-deps frontend npm install

frontend-shell: ## Abre um shell no container do frontend, com o frontend/ deste checkout
	$(CHECKOUT_RUN) frontend sh

## ---------- Testes de carga (k6) ----------

LOAD_PROFILES  = smoke load stress spike breakpoint soak contention limits
# The event-transport flag of the load stack for the run (HEL-13): the transport the relay publishes through.
LOAD_TRANSPORTS = redis rabbitmq
TRANSPORT      ?= redis
# Profiles whose volume would trip the rate limits: their limiter keeps the counts in memory, per request.
LOAD_UNLIMITED = load stress spike breakpoint soak contention
LOAD_LIMITER   = $(if $(filter $(TYPE),$(LOAD_UNLIMITED)),array,)
LOAD           = CACHE_LIMITER=$(LOAD_LIMITER) $(DC) -f load-tests/docker-compose.yml
LOAD_STAMP    := $(shell date +%Y%m%d-%H%M%S)
# The reports of a rabbitmq run say so: <profile>-rabbitmq-<stamp>.html and .json. The redis ones keep <profile>-<stamp>.
LOAD_RUN       = $(if $(filter rabbitmq,$(TRANSPORT)),rabbitmq-)$(LOAD_STAMP)

load-test: ## Recria a pilha de carga e roda um perfil do k6: make load-test TYPE=smoke [TRANSPORT=redis|rabbitmq]
	@if [ "$(words $(TYPE))" != "1" ] || [ -z "$(filter $(TYPE),$(LOAD_PROFILES))" ]; then \
		echo "Informe um perfil: make load-test TYPE=<perfil>"; \
		echo "Perfis: $(LOAD_PROFILES)"; \
		exit 1; \
	fi
	@if [ "$(words $(TRANSPORT))" != "1" ] || [ -z "$(filter $(TRANSPORT),$(LOAD_TRANSPORTS))" ]; then \
		echo "Transporte inválido: make load-test TYPE=$(TYPE) TRANSPORT=<transporte>"; \
		echo "Transportes: $(LOAD_TRANSPORTS)"; \
		exit 1; \
	fi
	$(LOAD) down --volumes --remove-orphans
	@# The rabbitmq container sometimes exits at boot with "Error when reading /var/lib/rabbitmq/.erlang.cookie: eacces"
	@# (seen on a busy host, never twice in a row): one more attempt starts it.
	$(LOAD) up -d --wait db redis rabbitmq || { echo "Subindo o rabbitmq de novo."; $(LOAD) up -d --wait db redis rabbitmq; }
	$(LOAD) up -d api
	@until $(LOAD) exec -T api test -f vendor/autoload.php 2>/dev/null; do sleep 2; done
	@$(LOAD) exec -T api sh -c 'php artisan migrate:fresh --force && php artisan db:seed --class=LoadTestSeeder --force' \
		|| { echo "A pilha de carga não subiu. Logs da api de carga:"; $(LOAD) logs --no-color api; exit 1; }
	$(LOAD) exec -T api sh -c 'mkdir -p /tmp/laravel && php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan modules:cache'
	$(LOAD) exec -T api php artisan events:transport $(TRANSPORT) --by=$(USER)
	$(LOAD) up -d queue-worker outbox-relay event-consumer nginx
	@mkdir -p load-tests/results && chmod a+rwx load-tests/results
	$(LOAD) run --rm \
		-e PROFILE=$(TYPE) -e RUN_STAMP=$(LOAD_RUN) \
		-e K6_WEB_DASHBOARD=true -e K6_WEB_DASHBOARD_PERIOD=2s \
		-e K6_WEB_DASHBOARD_EXPORT=results/$(TYPE)-$(LOAD_RUN).html \
		k6 run main.js

load-down: ## Remove a pilha de carga e os volumes dela (a de dev não muda)
	$(LOAD) down --volumes --remove-orphans

## ---------- Setup ----------

env-files:
	@test -f .env || (cp .env.example .env && echo "Criado .env")
	@test -f backend/.env || (cp backend/.env.example backend/.env && echo "Criado backend/.env")

wait-api:
	@echo "Aguardando a API ficar pronta..."
	@until $(EXEC_API) test -f vendor/autoload.php 2>/dev/null; do sleep 2; done

setup: env-files ## Sobe e prepara todo o projeto (ATENÇÃO: recria o banco de desenvolvimento)
	$(DC) build
	$(MAKE) npm-install
	$(DC) up -d --wait db redis rabbitmq
	$(DC) up -d
	@$(MAKE) --no-print-directory wait-api
	$(EXEC_API) composer install --no-interaction --prefer-dist
	@grep -q '^APP_KEY=base64' backend/.env || $(ARTISAN) key:generate --force
	$(ARTISAN) config:clear --quiet
	$(MAKE) fresh
	$(MAKE) queue-restart
	@echo ""
	@echo "Pronto! Acesse http://localhost:$(APP_PORT)"
	@echo "  Admin:   admin@example.com / password"
	@echo "  Cliente: cliente@example.com / password"
