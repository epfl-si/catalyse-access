.PHONY: help
## Print this help (see <https://gist.github.com/klmr/575726c7e05d8780505a> for explanation)
help:
	@echo "$$(tput bold)Available rules (alphabetical order):$$(tput sgr0)";sed -ne"/^## /{h;s/.*//;:d" -e"H;n;s/^## //;td" -e"s/:.*//;G;s/\\n## /---/;s/\\n/ /g;p;}" ${MAKEFILE_LIST}|LC_ALL='C' sort -f |awk -F --- -v n=$$(tput cols) -v i=20 -v a="$$(tput setaf 6)" -v z="$$(tput sgr0)" '{printf"%s%*s%s ",a,-i,$$1,z;m=split($$2,w," ");l=n-i;for(j=1;j<=m;j++){l-=length(w[j])+1;if(l<= 0){l=n-i-length(w[j])-1;printf"\n%*s ",-i," ";}printf"%s ",w[j];}printf"\n";}'

.PHONY: ps
## docker ps -a
ps:
	docker ps -a --filter "label=com.docker.compose.project.working_dir=${PWD}"

.PHONY: up
## docker-compose up
up: applog
	docker-compose up
	$(MAKE) app/vendor

app/vendor:
	 docker run --rm --volume $$PWD/app:/app composer install

.PHONY: upd
## docker-compose up -d
upd: applog
	docker-compose up -d

.PHONY: logs
## docker-compose logs -f -t --tail=150
logs:
	docker-compose logs -f -t --tail=150

.PHONY: applog
## create the app/app.log file
applog:
	touch app/app.log && chmod 777 app/app.log
