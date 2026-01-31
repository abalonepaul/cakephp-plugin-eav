<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Command;

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \Eav\Command\EavCreateAttributeCommand
 */
class EavCreateAttributeCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();
        if (!in_array('eav_attributes', $existing, true)) {
            $schema = new TableSchema('eav_attributes');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('name', ['type' => 'string', 'length' => 255, 'null' => false])
                ->addColumn('data_type', ['type' => 'string', 'length' => 50, 'null' => false])
                ->addColumn('placeholder', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('help_text', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

            $connection->disableConstraints(function ($connection) use ($schema): void {
                foreach ($schema->createSql($connection) as $sql) {
                    $connection->execute($sql);
                }
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $Attributes->deleteAll([]);
    }

    public function testCreateAttribute(): void
    {
        // Pass explicit test connection to ensure writes land on the test datasource
        $this->exec('eav create_attribute --name color --type string --connection test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Created attribute color (string)');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $attr = $Attributes->find()->where(['name' => 'color'])->firstOrFail();
        $this->assertSame('string', $attr->data_type);
    }

    public function testDuplicateAttributeNoop(): void
    {
        $this->exec('eav create_attribute --name color --type string --connection test');
        $this->assertExitSuccess();

        $this->exec('eav create_attribute --name color --type string --connection test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Attribute already exists: color');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $count = $Attributes->find()->where(['name' => 'color'])->count();
        $this->assertSame(1, $count);
    }

    public function testMissingNameShowsError(): void
    {
        $this->exec('eav create_attribute --type string --connection test');
        $this->assertExitError();
        $this->assertErrorContains('Missing required option: --name');
    }

    public function testMissingTypeShowsError(): void
    {
        $this->exec('eav create_attribute --name color --connection test');
        $this->assertExitError();
        $this->assertErrorContains('Missing required option: --type');
    }

    public function testUnsupportedTypeShowsError(): void
    {
        $this->exec('eav create_attribute --name bad --type bogus --connection test');
        $this->assertExitError();
        $this->assertErrorContains('Unsupported EAV type');
    }
}
