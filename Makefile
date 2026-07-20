build:
	@docker compose -f docker-compose.yml build

up:
	@docker compose -f docker-compose.yml up

stop:
	@docker compose -f docker-compose.yml stop

down:
	@docker compose -f docker-compose.yml down

cache-clear:
	@docker compose -f docker-compose.yml exec php bin/console cache:clear

makemigrations:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:diff --no-interaction --formatted

migrate:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction

loaddata:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:fixtures:load --no-interaction

backup:
	@bin/backup .data

restore:
	@bin/restore .data
