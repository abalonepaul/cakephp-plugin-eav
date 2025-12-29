<?php
// in tests/bootstrap.php
use Migrations\TestSuite\Migrator;

$migrator = new Migrator();

// Run the Eav migrations on the test connection.
$migrator->run(['plugin' => 'Eav', 'connection' => 'test']);
