# Laravel Migration Resource Checker

A Laravel package that compares Filament resources and models against migrations using AST parsing to ensure consistency.

## Installation

Install the package via Composer:

```bash
composer require michael4d45/laravel-resource-checker
```

The package will automatically register its service provider and the command will be available.

## Usage

Run the command to check your migrations, resources, and models:

```bash
php artisan check:migrations-resources
```

### Options

- `--output=`: Specify an output path for the JSON report (default: as configured in `config/migration-resource-checker.php`, or `reports/migration_resource_report.json`)
- `--fix-missing-properties`: Automatically add missing @property annotations to model PHPDoc
- `--fix-missing-property-read`: Automatically add missing @property-read annotations for relationships to model PHPDoc
- `--fix-wrong-property-read`: Automatically fix wrong @property-read property names to snake_case
- `--fix-wrong-model-doc-types`: Automatically fix wrong PHPDoc property types to match migrations
- `--fix-add-fields-to-resources`: Automatically add missing fields to resource form schemas
- `--fix-add-fields-to-model-docs`: Automatically add missing @property annotations for fields to model PHPDoc

## Features

- Parses migrations to extract table schemas
- Analyzes Filament resource forms
- Checks model properties and relationships
- Generates reports on inconsistencies
- Provides automatic fixing options for common issues

## Configuration

You can publish the config file to customize the default output path:

```bash
php artisan vendor:publish --provider="Michael4d45\LaravelResourceChecker\Providers\LaravelResourceCheckerServiceProvider" --tag=config
```

This will copy the config file to `config/migration-resource-checker.php` where you can modify settings.

## License

This package is open-sourced software licensed under the MIT license.
