<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Output Path
    |--------------------------------------------------------------------------
    |
    | The default path where the JSON report will be saved if no --output
    | option is provided to the check:migrations-resources command.
    |
    */
    'output_path' => 'reports/migration_resource_report.json',

    /*
    |--------------------------------------------------------------------------
    | Ignored Tables
    |--------------------------------------------------------------------------
    |
    | Tables that should be ignored when comparing migrations, resources, and models.
    | You can specify which components to ignore for each table.
    | Available components: 'resources', 'models', 'phpdoc'
    |
    | Examples:
    | 'users' => ['resources'] - ignore users table only for resources
    | 'migrations' => ['resources', 'models', 'phpdoc'] - ignore for all components
    |
    */
    'ignored_tables' => [
        'failed_jobs' => ['resources', 'models', 'phpdoc'],
        'jobs' => ['resources', 'models', 'phpdoc'],
        'migrations' => ['resources', 'models', 'phpdoc'],
        'personal_access_tokens' => ['resources', 'models', 'phpdoc'],
        'password_resets' => ['resources', 'models', 'phpdoc'],
        'password_reset_tokens' => ['resources', 'models', 'phpdoc'],
        'sessions' => ['resources', 'models', 'phpdoc'],
        'cache' => ['resources', 'models', 'phpdoc'],
        'cache_locks' => ['resources', 'models', 'phpdoc'],
        'job_batches' => ['resources', 'models', 'phpdoc'],
        'activity_log' => ['resources', 'models', 'phpdoc'],
        'webhooks' => ['resources', 'models', 'phpdoc'],
        // Add any additional tables to ignore here with their components
        // Examples:
        // 'pivot_table' => ['resources'], // Ignore only for resources
        // 'system_logs' => ['models', 'phpdoc'], // Ignore for models and PHPDoc but not resources
        // 'temp_data' => ['resources', 'models', 'phpdoc'], // Ignore for all components
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be ignored when comparing migrations, resources, and models.
    | You can specify which components to ignore for each field.
    | Available components: 'resources', 'models', 'phpdoc'
    |
    | Examples:
    | 'created_at' => ['models'] - ignore created_at field only for models
    | 'password' => ['resources', 'phpdoc'] - ignore for resources and PHPDoc
    |
    */
    'ignored_fields' => [
        'id' => ['models'],
        'created_at' => ['models'],
        'updated_at' => ['models'],
        'deleted_at' => ['models'],
        // Add any additional fields to ignore here with their components
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Type Mappings
    |--------------------------------------------------------------------------
    |
    | Maps Laravel migration column methods to PHP types for PHPDoc generation.
    | Add custom mappings for packages like Magellan (PostGIS) here.
    |
    */
    'column_type_mappings' => [
        'id' => 'int',
        'bigIncrements' => 'int',
        'increments' => 'int',
        'smallIncrements' => 'int',
        'mediumIncrements' => 'int',
        'tinyIncrements' => 'int',
        'integer' => 'int',
        'bigInteger' => 'int',
        'smallInteger' => 'int',
        'mediumInteger' => 'int',
        'tinyInteger' => 'int',
        'unsignedInteger' => 'int',
        'unsignedBigInteger' => 'int',
        'unsignedSmallInteger' => 'int',
        'unsignedMediumInteger' => 'int',
        'unsignedTinyInteger' => 'int',
        'foreignId' => 'int',
        'foreignIdFor' => 'int',

        'string' => 'string',
        'char' => 'string',
        'text' => 'string',
        'mediumText' => 'string',
        'longText' => 'string',
        'enum' => 'string',
        'uuid' => 'string',
        'foreignUuid' => 'string',
        'rememberToken' => 'string',

        'binary' => 'resource',

        'boolean' => 'bool',

        'decimal' => 'float',
        'float' => 'float',
        'double' => 'float',

        'timestamp' => 'Carbon',
        'timestampTz' => 'Carbon',
        'date' => 'Carbon',
        'dateTime' => 'Carbon',
        'dateTimeTz' => 'Carbon',

        'json' => 'array',
        'jsonb' => 'array',

        // Custom types for packages
        // 'magellanPoint' => 'Point',
        // 'magellanBox2D' => 'Box2D',
        // 'vector' => 'Vector',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Normalizations
    |--------------------------------------------------------------------------
    |
    | Maps short type names to their full qualified class names for type compatibility checks.
    |
    */
    'type_normalizations' => [
        'Carbon' => '\\Illuminate\\Support\\Carbon',
        'Point' => '\\Clickbar\\Magellan\\Data\\Geometries\\Point',
        'Box2D' => '\\Clickbar\\Magellan\\Data\\Boxes\\Box2D',
        // 'Vector' => '\\Pgvector\\Laravel\\Vector',
        // Add more normalizations as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Cast Type Mappings
    |--------------------------------------------------------------------------
    |
    | Maps Eloquent cast names to their expected PHPDoc types.
    |
    */
    'cast_type_mappings' => [
        'datetime' => '\\Illuminate\\Support\\Carbon',
        'timestamp' => '\\Illuminate\\Support\\Carbon',
        'json' => 'array',
        'boolean' => 'bool',
        'integer' => 'int',
        'hashed' => 'string',
        // Add more cast mappings as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Component Classes
    |--------------------------------------------------------------------------
    |
    | Filament form component classes to look for when parsing resources.
    |
    */
    'form_component_classes' => [
        '\\Filament\\Forms\\Components',
        '\\Filament\\Forms\\Components\\Field',
        '\\Filament\\Forms\\Components\\TextInput',
        '\\Filament\\Forms\\Components\\Select',
        '\\Filament\\Forms\\Components\\Textarea',
        '\\Filament\\Forms\\Components\\Checkbox',
        '\\Filament\\Forms\\Components\\DateTimePicker',
        '\\Filament\\Forms\\Components\\FileUpload',
        '\\Filament\\Forms\\Components\\KeyValue',
        '\\Filament\\Forms\\Components\\Repeater',
        '\\Filament\\Forms\\Components\\Builder',
        '\\Filament\\Forms\\Components\\Toggle',
        '\\Filament\\Forms\\Components\\Radio',
        '\\Filament\\Forms\\Components\\TagsInput',
        '\\Filament\\Forms\\Components\\ColorPicker',
        // '\\App\\Filament\\Fields\\MapField',
        // '\\App\\Filament\\Fields\\MapBboxField',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Component Type Mapping
    |--------------------------------------------------------------------------
    |
    | Maps detected Filament form components to the expected migration type.
    | Keys should be the fully-qualified component class names (or short names)
    | resolved during parsing, and values are normalized field types.
    |
    */
    'resource_component_type_map' => [
        '\\Filament\\Forms\\Components\\TextInput' => 'string',
        '\\Filament\\Forms\\Components\\Textarea' => 'string',
        '\\Filament\\Forms\\Components\\Select' => 'string',
        '\\Filament\\Forms\\Components\\Toggle' => 'bool',
        '\\Filament\\Forms\\Components\\Checkbox' => 'bool',
        '\\Filament\\Forms\\Components\\Radio' => 'string',
        '\\Filament\\Forms\\Components\\DatePicker' => 'Carbon',
        '\\Filament\\Forms\\Components\\DateTimePicker' => 'Carbon',
        '\\Filament\\Forms\\Components\\TimePicker' => 'Carbon',
        '\\Filament\\Forms\\Components\\TagsInput' => 'array',
        '\\Filament\\Forms\\Components\\KeyValue' => 'array',
        '\\Filament\\Forms\\Components\\FileUpload' => 'string',
        '\\Filament\\Forms\\Components\\ColorPicker' => 'string',
        // '\\App\\Filament\\Fields\\MapField' => 'Point',
        // '\\App\\Filament\\Fields\\MapBboxField' => 'Box2D',
        // 'MapField' => 'Point',
        // 'MapBboxField' => 'Box2D',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Component Required Methods
    |--------------------------------------------------------------------------
    |
    | Method names that, when called on a component, imply the field should be
    | treated as non-nullable. Example: ->required() or ->rules(['required']).
    |
    */
    'resource_component_required_methods' => [
        'required',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Component Mappings
    |--------------------------------------------------------------------------
    |
    | Determines which Filament component should be generated when the
    | --fix-add-fields-to-resources flag adds missing form fields. Keys are the
    | normalized migration column types (lowercase) and values are component
    | class names. The "resource_component_default" value is used when a type
    | does not have an explicit mapping.
    |
    */
    'resource_component_default' => '\\Filament\\Forms\\Components\\TextInput',
    'resource_component_mappings' => [
        'text' => '\\Filament\\Forms\\Components\\Textarea',
        'mediumtext' => '\\Filament\\Forms\\Components\\Textarea',
        'longtext' => '\\Filament\\Forms\\Components\\Textarea',
        'bool' => '\\Filament\\Forms\\Components\\Toggle',
        'boolean' => '\\Filament\\Forms\\Components\\Toggle',
        'date' => '\\Filament\\Forms\\Components\\DateTimePicker',
        'datetime' => '\\Filament\\Forms\\Components\\DateTimePicker',
        'timestamp' => '\\Filament\\Forms\\Components\\DateTimePicker',
        'carbon' => '\\Filament\\Forms\\Components\\DateTimePicker',
        'carbontimestamp' => '\\Filament\\Forms\\Components\\DateTimePicker',
        // 'point' => '\\App\\Filament\\Fields\\MapField',
        // 'box2d' => '\\App\\Filament\\Fields\\MapBboxField',
    ],

    /*
    |--------------------------------------------------------------------------
    | Numeric Field Types
    |--------------------------------------------------------------------------
    |
    | Column types that should call ->numeric() when generating Filament
    | components.
    |
    */
    'resource_numeric_types' => [
        'int',
        'integer',
        'bigint',
        'smallint',
        'tinyint',
        'mediumint',
        'float',
        'double',
        'decimal',
    ],
];
