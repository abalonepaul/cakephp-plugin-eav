<?php
declare(strict_types=1);

namespace Eav\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

/**
 * EavEntities Controller
 *
 * @property \Eav\Model\Table\EavEntitiesTable $EavEntities
 */
class EavEntitiesController extends AppController
{
    /**
     * Build UI choice lists for add/edit forms.
     *
     * @return array{entityNameChoices: array<string,string>, storageDefaults: array<string,string>, pkTypes: array<string,string>, uuidSubtypes: array<string,string>, suggestions: array<string,array{alias:string,table:string}>}
     */
    protected function formChoices(): array
    {
        $conn = ConnectionManager::get('default');
        $tables = $conn->getSchemaCollection()->listTables();
        sort($tables);

        $entityNameChoices = [];
        $suggestions = [];
        foreach ($tables as $t) {
            // Exclude plugin EAV tables and common migration tables from selection
            if (preg_match('/^eav_/', $t) === 1 || in_array($t, ['phinxlog', 'phinxlog_eav'], true)) {
                continue;
            }
            // Human label for the select, plus alias/table suggestions
            $entityNameChoices[$t] = Inflector::humanize($t);
            $alias = Inflector::camelize(Inflector::singularize($t));
            $suggestions[$t] = [
                'alias' => $alias,
                'table' => $t,
            ];
        }

        $storageDefaults = [
            'tables' => __('Tables (Typed EAV)'),
            'json_column' => __('JSON Column (on Entity Table)'),
        ];
        $pkTypes = [
            'uuid' => __('UUID'),
            'int' => __('Integer'),
        ];
        $uuidSubtypes = [
            'nativeuuid' => __('Native UUID'),
            'binaryuuid' => __('Binary UUID'),
            'uuid' => __('UUID (string)'),
        ];

        return compact('entityNameChoices', 'storageDefaults', 'pkTypes', 'uuidSubtypes', 'suggestions');
    }

    /**
     * Apply smart defaults and enforce schema-derived values prior to saving.
     *
     * - model_alias/table_name: default derived from selected "name" when empty.
     * - pk_type/uuid_subtype: detected from the selected table's primary key column type.
     * - json_column: cleared unless storage_default is json_column.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @return void
     */
    protected function applySmartDefaults(\Cake\Datasource\EntityInterface $entity): void
    {
        $name = (string)($entity->get('name') ?? '');
        $modelAlias = (string)($entity->get('model_alias') ?? '');
        $tableName = (string)($entity->get('table_name') ?? '');
        $storageDefault = (string)($entity->get('storage_default') ?? 'tables');

        // Derive alias/table from "name" if empty
        if ($name !== '') {
            if ($modelAlias === '') {
                $entity->set('model_alias', Inflector::camelize(Inflector::singularize($name)));
            }
            if ($tableName === '') {
                $entity->set('table_name', $name);
                $tableName = $name;
            }
        }

        // Detect PK family and UUID subtype from schema (best-effort)
        if ($tableName !== '') {
            try {
                $conn = ConnectionManager::get('default');
                $schema = $conn->getSchemaCollection();
                $listed = array_map('strtolower', $schema->listTables());
                if (in_array(strtolower($tableName), $listed, true)) {
                    $desc = $schema->describe($tableName);
                    // Try to read primary key column from constraint; default to 'id' if unknown
                    $pkColumn = 'id';
                    $primary = $desc->getConstraint('primary');
                    if (is_array($primary) && !empty($primary['columns'][0])) {
                        $pkColumn = (string)$primary['columns'][0];
                    }
                    $colType = (string)$desc->getColumnType($pkColumn);

                    // Map Cake column types to pk_type and uuid_subtype
                    $intTypes = ['integer', 'smallinteger', 'tinyinteger', 'biginteger'];
                    if (in_array($colType, $intTypes, true)) {
                        $entity->set('pk_type', 'int');
                        // Clear subtype for integer PKs
                        $entity->set('uuid_subtype', null);
                    } else {
                        $entity->set('pk_type', 'uuid');
                        // Choose subtype based on column type or driver recommendation
                        $subtype = null;
                        if ($colType === 'binaryuuid') {
                            $subtype = 'binaryuuid';
                        } elseif ($colType === 'nativeuuid') {
                            $subtype = 'nativeuuid';
                        } elseif ($colType === 'uuid') {
                            // Prefer nativeuuid on Postgres; binaryuuid on MySQL; fallback 'uuid'
                            $driver = $conn->getDriver();
                            $driverClass = get_class($driver);
                            if (stripos($driverClass, 'Postgres') !== false) {
                                $subtype = 'nativeuuid';
                            } elseif (stripos($driverClass, 'Mysql') !== false) {
                                $subtype = 'binaryuuid';
                            } else {
                                $subtype = 'uuid';
                            }
                        } else {
                            // Unknown non-integer type: assume UUID family with generic subtype
                            $subtype = 'uuid';
                        }
                        $entity->set('uuid_subtype', $subtype);
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal; leave any user-provided values as-is
            }
        }

        // Clear json_column unless JSON Storage is selected
        if ($storageDefault !== 'json_column') {
            $entity->set('json_column', null);
        }
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->EavEntities->find();
        $eavEntities = $this->paginate($query);

        $this->set(compact('eavEntities'));
    }

    /**
     * View method
     *
     * @param string|null $id Eav Entity id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $eavEntity = $this->EavEntities->get($id, contain: []);
        $this->set(compact('eavEntity'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $eavEntity = $this->EavEntities->newEmptyEntity();
        if ($this->request->is('post')) {
            $eavEntity = $this->EavEntities->patchEntity($eavEntity, $this->request->getData());
            // Enforce schema-driven defaults
            $this->applySmartDefaults($eavEntity);

            if ($this->EavEntities->save($eavEntity)) {
                $this->Flash->success(__('The EAV Entity has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The EAV Entity could not be saved. Please, try again.'));
        }

        [
            'entityNameChoices' => $entityNameChoices,
            'storageDefaults' => $storageDefaults,
            'pkTypes' => $pkTypes,
            'uuidSubtypes' => $uuidSubtypes,
            'suggestions' => $suggestions,
        ] = $this->formChoices();

        $this->set(compact(
            'eavEntity',
            'entityNameChoices',
            'storageDefaults',
            'pkTypes',
            'uuidSubtypes',
            'suggestions'
        ));
    }

    /**
     * Edit method
     *
     * @param string|null $id Eav Entity id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $eavEntity = $this->EavEntities->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $eavEntity = $this->EavEntities->patchEntity($eavEntity, $this->request->getData());
            // Enforce schema-driven defaults
            $this->applySmartDefaults($eavEntity);

            if ($this->EavEntities->save($eavEntity)) {
                $this->Flash->success(__('The EAV Entity has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The EAV Entity could not be saved. Please, try again.'));
        }

        [
            'entityNameChoices' => $entityNameChoices,
            'storageDefaults' => $storageDefaults,
            'pkTypes' => $pkTypes,
            'uuidSubtypes' => $uuidSubtypes,
            'suggestions' => $suggestions,
        ] = $this->formChoices();

        $this->set(compact(
            'eavEntity',
            'entityNameChoices',
            'storageDefaults',
            'pkTypes',
            'uuidSubtypes',
            'suggestions'
        ));
    }

    /**
     * Delete method
     *
     * @param string|null $id Eav Entity id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $eavEntity = $this->EavEntities->get($id);
        if ($this->EavEntities->delete($eavEntity)) {
            $this->Flash->success(__('The EAV Entity has been deleted.'));
        } else {
            $this->Flash->error(__('The EAV Entity could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
