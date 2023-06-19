up:
	@docker-compose -f docker-compose.dev.yml up -d --build
	@chmod -R 0755 plugin || :
	@chmod -R 0755 resource || :
	@chmod -R 0777 theme || :
	@chmod -R 0777 var || :

down:
	@docker-compose -f docker-compose.dev.yml down

run-test:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/phpunit --color=always --configuration phpunit.xml

lint:
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/phpstan analyze src --memory-limit 256M
	@docker-compose -f docker-compose.dev.yml exec platform ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff

clean:
    docker rm platform
    docker rmi ghcr.io/getwebspace/platform:test
