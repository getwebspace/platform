up:
	@docker-compose -f docker-compose.dev.yml up -d --build
	@chmod -R 0777 plugin || :
	@chmod -R 0777 public/resource || :
	@chmod -R 0777 theme || :
	@chmod -R 0777 var || :

down:
	@docker-compose -f docker-compose.dev.yml down

run-test:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/phpunit --color=always --configuration phpunit.xml

lint:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff

migrate:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite migrate --no-interaction

rollback:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite migrate prev

status:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite status

diff:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/doctrine-migrations --em=sqlite diff
