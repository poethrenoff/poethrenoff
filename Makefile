build:
	@docker compose -f docker-compose.yml build

up:
	@docker compose -f docker-compose.yml up

stop:
	@docker compose -f docker-compose.yml stop

down:
	@docker compose -f docker-compose.yml down

update:
	@docker compose -f docker-compose.yml exec php composer update -W

phpcs:
	@docker compose -f docker-compose.yml exec php bin/phpcs

phpcbf:
	@docker compose -f docker-compose.yml exec php bin/phpcbf

analyze:
	@docker compose -f docker-compose.yml exec php bin/phpstan analyse --no-progress

cache-clear:
	@docker compose -f docker-compose.yml exec php bin/console cache:clear

makemigrations:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:diff --no-interaction --formatted

migrate:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction

loaddata:
	@docker compose -f docker-compose.yml exec php bin/console doctrine:fixtures:load --no-interaction

backup:
	@bin/backup var/backup

restore:
	@bin/restore var/backup

