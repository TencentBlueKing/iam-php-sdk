.PHONY: test check

check:
	./vendor/bin/psalm --show-info=true

test:
	./vendor/bin/phpunit tests
