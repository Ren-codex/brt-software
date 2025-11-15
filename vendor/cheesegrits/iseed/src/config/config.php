<?php
/**
 * This file implements the default configuration.
 *
 * @var   array
 * @since 3.1.0
 */
return [
    /**
     * Path where the seeders will be generated.
     */
    'path' => '/database/seeders',

    /**
     * Path where the Seeder file is saved.
     */
    'seeder_path' => '/database/seeders/DatabaseSeeder.php',

    /**
     * Whether the Seeder should be modified after running the iseed command.
     */
    'seeder_modification' => true,

    /**
     * Maximum number of rows per insert statement.
     */
    'chunk_size' => 500,

    /**
     * You may alternatively set a relative path to a custom stub file
     * The default stub file is located in
     * /vendor/cheesegrits/iseed/src/Stubs/seed.stub
     *
     * Make sure to make path relative to your project root:
     * i.e. 'stubs/seeder.stub' not '/stubs/seeder.stub'!
     */
    'stub_path' => false,

    /**
     * You may customize the line that preceeds the inserts inside the seeder.
     * You MUST keep both %s however, the first will be replaced by the table
     * name and the second by the inserts themselves.
     */
    'insert_command' => "\DB::table('%s')->insert(%s);",
];
