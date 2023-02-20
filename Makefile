default:
	docker-compose exec php-min /bin/bash

up:
	docker-compose up -d

start: up

down:
	docker-compose down

stop: down
