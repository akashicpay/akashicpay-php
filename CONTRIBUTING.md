# Contributing

## Get Started

```bash
# install dependencies
composer install

# run test
 ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```

## Lint your code

```bash
# just lint
./vendor/bin/phpcs --standard=PSR2 src/

# lint with auto fix
./vendor/bin/phpcbf --standard=PSR2 src/
```
