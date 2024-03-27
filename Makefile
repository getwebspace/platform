up:
	@docker-compose -f docker-compose.dev.yml up -d || :
	@docker-compose -f docker-compose.dev.yml exec platform composer install || :
	@chmod -R 0777 plugin || :
	@chmod -R 0777 public/resource || :
	@chmod -R 0777 theme || :
	@chmod -R 0777 var || :

down:
	@docker-compose -f docker-compose.dev.yml down

run-test:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/phpunit --color=always --configuration phpunit.xml --filter UserServiceTest

run-lint:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

migrate-up:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite migrate --no-interaction

migrate-down:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite migrate prev

migrate-status:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite status

migrate-diff:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite diff

migrate-test:
	@docker-compose -f // php artisan make:migration create_users_table --create=users
