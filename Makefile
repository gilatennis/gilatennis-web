REGISTRY ?= docker.io
IMAGE ?= kevinjanada/gilatennis_web
TAG ?= latest

.PHONY: build push pull up down dev migrate seed storage-link logs

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
