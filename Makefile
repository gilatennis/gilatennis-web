REGISTRY ?= docker.io
IMAGE ?= kevinjanada/gilatennis_web
TAG ?= latest

SSH ?= hetzner-root
PROD_DB_IMAGE ?= mysql:8
PROD_APP_IMAGE ?= kevinjanada/gilatennis_web:latest
BACKUP_DIR ?= storage/app/db-backups

.PHONY: build push pull up down dev migrate seed storage-link logs db-pull storage-pull mirror migrate-prod prod-status prod-artisan

# Build for linux (run on Mac)
build:
	docker buildx build --platform linux/amd64,linux/arm64 -t $(REGISTRY)/$(IMAGE):$(TAG) .

# Build + push in one shot
push:
	docker buildx build --platform linux/amd64,linux/arm64 -t $(REGISTRY)/$(IMAGE):$(TAG) --push .

# Pull on production server
pull:
	docker compose pull

# Production
up:
	docker compose --env-file .env.production up -d

down:
	docker compose down

# Local dev (build from source)
dev:
	docker compose -f docker-compose.dev.yml up --build

# Mirror production DB + uploads into local dev
db-pull:
	@SSH=$(SSH) PROD_DB_IMAGE=$(PROD_DB_IMAGE) BACKUP_DIR=$(BACKUP_DIR) scripts/db-pull.sh

storage-pull:
	@SSH=$(SSH) PROD_APP_IMAGE=$(PROD_APP_IMAGE) BACKUP_DIR=$(BACKUP_DIR) scripts/storage-pull.sh

mirror: db-pull storage-pull

# Artisan helpers
migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

storage-link:
	docker compose exec app php artisan storage:link

logs:
	docker compose logs -f app

queue-logs:
	docker compose exec app php artisan queue:listen --tries=1

# Production (over SSH; migrations are not automated on deploy)
migrate-prod:
	@SSH=$(SSH) PROD_APP_IMAGE=$(PROD_APP_IMAGE) PROD_DB_IMAGE=$(PROD_DB_IMAGE) BACKUP_DIR=$(BACKUP_DIR) scripts/migrate-prod.sh $(ARGS)

prod-status:
	@SSH=$(SSH) PROD_APP_IMAGE=$(PROD_APP_IMAGE) scripts/prod-artisan.sh migrate:status --pending

prod-artisan:
	@SSH=$(SSH) PROD_APP_IMAGE=$(PROD_APP_IMAGE) scripts/prod-artisan.sh $(CMD)
