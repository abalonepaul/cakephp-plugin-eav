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
        'plugin.Eav.Attributes',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();
        if (!in_array('attributes', $existing, true)) {
            $schema = new TableSchema('attributes');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('name', ['type' => 'string', 'length' => 191, 'null' => false])
                ->addColumn('label', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('data_type', ['type' => 'string', 'length' => 50, 'null' => false])
                ->addColumn('options', ['type' => 'json', 'null' => false])
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
        $Attributes = TableRegistry::getTableLocator()->get('Eav.Attributes');
        $Attributes->deleteAll([]);
    }

    public function testCreateAttribute(): void
    {
        $this->exec('eav create_attribute color:string -l "Color"');
        $this->assertExitSuccess();
        $this->assertOutputContains('Created attribute color (string)');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->where(['name' => 'color'])->firstOrFail();
        $this->assertSame('string', $attr->data_type);
        $this->assertSame('Color', $attr->label);
        $this->assertSame([], $attr->options);
    }

    public function testDuplicateAttributeNoop(): void
    {
        $this->exec('eav create_attribute color:string -l "Color"');
        $this->assertExitSuccess();

        $this->exec('eav create_attribute color:string -l "Color"');
        $this->assertExitSuccess();
        $this->assertOutputContains('Attribute already exists: color');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.Attributes');
        $count = $Attributes->find()->where(['name' => 'color'])->count();
        $this->assertSame(1, $count);
    }
}
