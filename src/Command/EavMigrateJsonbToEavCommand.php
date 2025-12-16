<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;

class EavMigrateJsonbToEavCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $table = (string)$args->getArgument('table');
        $jsonbField = (string)$args->getArgument('jsonbField');
        $attribute = (string)$args->getOption('attribute');
        $type = (string)$args->getOption('type') ?: 'string';
        $entityTable = (string)$args->getOption('entity-table') ?: $table;
        $pkType = (string)$args->getOption('pk') ?: 'uuid';

        if (!$table || !$jsonbField || !$attribute) {
            $io->err('Usage: bin/cake eav migrate_jsonb_to_eav table jsonbField --attribute key --type decimal --entity-table parts --pk uuid');
            return Command::CODE_ERROR;
        }

        $conn = ConnectionManager::get('default');
        $Attributes = $this->getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->where(['name'=>$attribute])->first();
        if (!$attr) {
            $attr = $Attributes->newEntity(['name'=>$attribute,'data_type'=>$type]);
            $Attributes->saveOrFail($attr);
        }

        $pkCol = $pkType === 'int' ? 'id' : 'id';
        $rows = $conn->execute("SELECT id, {$jsonbField} ->> :key AS val FROM {$table} WHERE {$jsonbField} ? :key", ['key'=>$attribute])->fetchAll('assoc');

        $Eav = $this->getTableLocator()->get('Eav.Model/Behavior/EavBehavior'); // not used directly; calls below mimic persistence
        $Behavior = new \Eav\Model\Behavior\EavBehavior($this->getTableLocator()->get($table), [
            'entityTable' => $entityTable, 'pkType' => $pkType,
        ]);

        $count = 0;
        foreach ($rows as $r) {
            if ($r['val'] === null || $r['val'] === '') { continue; }
            $Behavior->saveEavValue($r['id'], $attribute, $type, $r['val']);
            $count++;
        }
        $io->out("Migrated {$count} values from {$table}.{$jsonbField} -> {$attribute} ({$type}).");
        return Command::CODE_SUCCESS;
    }

    public static function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser->addArgument('table');
        $parser->addArgument('jsonbField');
        $parser->addOption('attribute', ['short'=>'a', 'help'=>'JSON key to migrate', 'required'=>true]);
        $parser->addOption('type', ['short'=>'t', 'help'=>'EAV type (string,int,decimal,bool,date,datetime,json,uuid,fk_uuid)']);
        $parser->addOption('entity-table', ['help'=>'Entity table name in AV rows']);
        $parser->addOption('pk', ['help'=>'Primary key type: uuid|int', 'default'=>'uuid']);
        return $parser;
    }
}
