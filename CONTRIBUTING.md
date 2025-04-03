# Contributing

## Get Started

```bash
# install dependencies
composer install

# run the tests
 ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```

## PHPStorm

### Formatting on save

Here are [the official, incomplete instructions](https://www.jetbrains.com/help/phpstorm/reformat-and-rearrange-code.html#reformat-on-save).
And here's how to actually do it:

1. Configure the formatter:
   1. Open up settings and go to **PHP | Quality Tools | PHP_CodeSniffer**
   2. Turn the inspections `ON`
   3. Click the `...` to the right of the `Configuration` dropdown, and set the paths:
      1. `Path to phpcs` should be the path to the `vendor/bin/phpcs` file
      2. `Path to phpcbf` should be the path to the `vendor/bin/phpcbf` file
   4. Set the `Coding Standard` to `Custom` and set the path to the `phpcs.xml` file in the root of the SDK
2. Turn on auto-formatting:
   1. Still in settings, go to **Tools | Actions on Save**
   2. Ensure `Reformat code` is checked and turned on for .php files
3. Check everything's working by adding a few arbitrary line-breaks to a file and swapping focus to get your IDE to save it. Hopefully the formatter will remove the extra lines.

## VSCode

The PHP support in JetBrains' PHPStorm is far superior, and most of the community use it. FYI.
But if you insist on using VSCode, you can have a go at configuring it with this section as a starting point.
I'm not sure which extensions are best in each case, being a never-VSCoder myself. So you'll likely need to experiment.

### Formatting on save

But [ObliviousHarmony/vscodephp-codesniffer](https://github.com/ObliviousHarmony/vscode-php-codesniffer) seems to be a well-maintained option for linting with PSR12.
It's possible that the [intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client) extension does everything though. But the documentation is unclear.
