.PHONY: help up down build shell artisan composer npm migrate fresh seed test logs ps

# ── Geral ─────────────────────────────────────────────────────────────────────

help: ## Exibe este help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Sobe todos os containers em background
	docker compose up -d

down: ## Para todos os containers
	docker compose down

build: ## Reconstrói as imagens
	docker compose build --no-cache

ps: ## Lista containers em execução
	docker compose ps

logs: ## Exibe logs de todos os containers
	docker compose logs -f

logs-app: ## Exibe logs do container app
	docker compose logs -f app

logs-queue: ## Exibe logs do worker de fila
	docker compose logs -f queue

# ── Acesso ao container ───────────────────────────────────────────────────────

shell: ## Abre shell no container app
	docker compose exec app bash

shell-root: ## Abre shell como root no container app
	docker compose exec -u root app bash

# ── Laravel ───────────────────────────────────────────────────────────────────

artisan: ## Executa comando artisan. Uso: make artisan CMD="route:list"
	docker compose exec app php artisan $(CMD)

composer: ## Executa comando composer. Uso: make composer CMD="require pacote/nome"
	docker compose exec app composer $(CMD)

npm: ## Executa comando npm. Uso: make npm CMD="run dev"
	docker compose exec app npm $(CMD)

# ── Banco de dados ────────────────────────────────────────────────────────────

migrate: ## Roda as migrations
	docker compose exec app php artisan migrate

fresh: ## Recria o banco (migrate:fresh) com seeds
	docker compose exec app php artisan migrate:fresh --seed

seed: ## Roda os seeders
	docker compose exec app php artisan db:seed

# ── Qualidade de código ───────────────────────────────────────────────────────

test: ## Roda os testes
	docker compose exec app php artisan test

test-coverage: ## Roda testes com cobertura
	docker compose exec app php artisan test --coverage

pint: ## Roda o Laravel Pint (code style)
	docker compose exec app ./vendor/bin/pint

# ── Setup inicial ─────────────────────────────────────────────────────────────

setup: ## Configuração inicial completa do projeto
	cp -n src/.env.example src/.env 2>/dev/null || true
	docker compose build
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	docker compose exec app npm install
	@echo "\n✅ HMPay pronto em http://localhost:8000"

key: ## Gera APP_KEY
	docker compose exec app php artisan key:generate

storage-link: ## Cria link simbólico storage
	docker compose exec app php artisan storage:link

cache-clear: ## Limpa todos os caches
	docker compose exec app php artisan optimize:clear

queue-restart: ## Reinicia os workers de fila
	docker compose exec app php artisan queue:restart
