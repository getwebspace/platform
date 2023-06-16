up:
	@docker-compose -f ./docker/dev/docker-compose.yml up --build -d
	@chmod -R 0755 plugin || :
	@chmod -R 0755 resource || :
	@chmod -R 0777 theme || :
	@chmod -R 0777 var || :

down:
	@docker-compose -f ./docker/dev/docker-compose.yml down

run-test:
	@docker-compose -f ./docker/dev/docker-compose.yml exec fpm ./vendor/bin/phpunit tests

lint:
	@docker-compose -f ./docker/dev/docker-compose.yml exec fpm ./vendor/bin/phpstan analyze src --memory-limit 256M
	@docker-compose -f ./docker/dev/docker-compose.yml exec fpm ./vendor/bin/php-cs-fixer fix --diff
	@docker-compose -f ./docker/dev/docker-compose.yml exec fpm ./vendor/bin/rector -n
