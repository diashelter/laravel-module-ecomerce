# Central place for the project's day-to-day commands.
# Every Laravel/npm command runs inside the proper container.

DC        = docker compose
EXEC_API  = $(DC) exec api
ARTISAN   = $(EXEC_API) php artisan
EXEC_DB   = $(DC) exec db
EXEC_NODE = $(DC) exec frontend

-include .env
APP_PORT    ?= 8080
DB_DATABASE ?= ecommerce
DB_USERNAME ?= ecommerce

.DEFAULT_GOAL := help

.PHONY: help up down build rebuild ps logs logs-api logs-worker logs-relay \
	shell artisan migrate migrate-fresh seed fresh optimize clear \
	db-shell db-reset db-dump db-restore \
	queue queue-restart queue-failed queue-retry \
	test test-filter test-frontend npm-install frontend-shell setup env-files wait-api \
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

logs: ## Mostra os logs principais (api, worker, relay, nginx, frontend)
	$(DC) logs -f --tail=100 api queue-worker outbox-relay nginx frontend

logs-api: ## Mostra os logs da API
	$(DC) logs -f --tail=100 api

logs-worker: ## Mostra os logs do worker de filas
	$(DC) logs -f --tail=100 queue-worker

logs-relay: ## Mostra os logs do relay do outbox
	$(DC) logs -f --tail=100 outbox-relay

## ---------- Laravel ----------

shell: ## Abre um shell no container da API
	$(EXEC_API) sh

artisan: ## Executa um comando Artisan: make artisan CMD="route:list"
	$(ARTISAN) $(CMD)

migrate: ## Executa as migrations
	$(ARTISAN) migrate --force

migrate-fresh: ## Apaga todas as tabelas e executa as migrations
	$(ARTISAN) migrate:fresh --force

seed: ## Executa os seeders
	$(ARTISAN) db:seed --force

fresh: ## Recria o banco de desenvolvimento (migrate:fresh --seed)
	$(ARTISAN) migrate:fresh --seed --force

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

queue-restart: ## Reinicia os workers e o relay (necessário após alterar jobs, consumidores ou o relay)
	$(ARTISAN) queue:restart

queue-failed: ## Lista os jobs que falharam
	$(ARTISAN) queue:failed

queue-retry: ## Reprocessa jobs falhos: make queue-retry (todos) ou make queue-retry ID=<uuid>
	$(ARTISAN) queue:retry $(if $(ID),$(ID),all)

## ---------- Testes ----------

test: ## Executa todos os testes (Pest)
	$(ARTISAN) config:clear --quiet
	$(EXEC_API) ./vendor/bin/pest

test-filter: ## Executa testes filtrados: make test-filter FILTER=CheckoutTest
	$(ARTISAN) config:clear --quiet
	$(EXEC_API) ./vendor/bin/pest --filter="$(FILTER)"

## ---------- Frontend ----------

test-frontend: ## Executa os testes do frontend (Vitest)
	$(EXEC_NODE) npm test

npm-install: ## Instala as dependências do frontend
	$(DC) run --rm --no-deps frontend npm install

frontend-shell: ## Abre um shell no container do frontend
	$(EXEC_NODE) sh

## ---------- Testes de carga (k6) ----------

LOAD_PROFILES  = smoke load stress spike breakpoint soak contention limits
# Profiles whose volume would trip the rate limits: their limiter keeps the counts in memory, per request.
LOAD_UNLIMITED = load stress spike breakpoint soak contention
LOAD_LIMITER   = $(if $(filter $(TYPE),$(LOAD_UNLIMITED)),array,)
LOAD           = CACHE_LIMITER=$(LOAD_LIMITER) $(DC) -f load-tests/docker-compose.yml
LOAD_STAMP    := $(shell date +%Y%m%d-%H%M%S)

load-test: ## Recria a pilha de carga e roda um perfil do k6: make load-test TYPE=smoke
	@if [ "$(words $(TYPE))" != "1" ] || [ -z "$(filter $(TYPE),$(LOAD_PROFILES))" ]; then \
		echo "Informe um perfil: make load-test TYPE=<perfil>"; \
		echo "Perfis: $(LOAD_PROFILES)"; \
		exit 1; \
	fi
	$(LOAD) down --volumes --remove-orphans
	$(LOAD) up -d --wait db redis
	$(LOAD) up -d api
	@until $(LOAD) exec -T api test -f vendor/autoload.php 2>/dev/null; do sleep 2; done
	@$(LOAD) exec -T api sh -c 'php artisan migrate:fresh --force && php artisan db:seed --class=LoadTestSeeder --force' \
		|| { echo "A pilha de carga não subiu. Logs da api de carga:"; $(LOAD) logs --no-color api; exit 1; }
	$(LOAD) exec -T api sh -c 'mkdir -p /tmp/laravel && php artisan config:cache && php artisan route:cache && php artisan event:cache'
	$(LOAD) up -d queue-worker outbox-relay nginx
	@mkdir -p load-tests/results && chmod a+rwx load-tests/results
	$(LOAD) run --rm \
		-e PROFILE=$(TYPE) -e RUN_STAMP=$(LOAD_STAMP) \
		-e K6_WEB_DASHBOARD=true -e K6_WEB_DASHBOARD_PERIOD=2s \
		-e K6_WEB_DASHBOARD_EXPORT=results/$(TYPE)-$(LOAD_STAMP).html \
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
	$(DC) up -d --wait db redis
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
