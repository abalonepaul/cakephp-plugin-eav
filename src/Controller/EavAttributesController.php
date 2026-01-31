<?php
declare(strict_types=1);

namespace Eav\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Response;

/**
 * EavAttributes Controller
 *
 * @property \Eav\Model\Table\EavAttributesTable $EavAttributes
 */
class EavAttributesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        // Load EAV UI/component to supply data types and optional admin prefix.
        $this->loadComponent('Eav.Eav');
    }

    /**
     * Index method
     *
     * Eager-load EavAttributeSets so the index view can display “Used in X sets” badges
     * without issuing per-row queries.
     *
     * @return void Renders view
     */
    public function index()
    {
        $query = $this->EavAttributes
            ->find()
            ->contain(['EavAttributeSets']); // for badge counts

        $eavAttributes = $this->paginate($query);

        $this->set(compact('eavAttributes'));
    }

    /**
     * View method
     *
     * @param string|null $id Eav Attribute id.
     * @return void|null Renders view
     * @throws RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $eavAttribute = $this->EavAttributes->get($id, contain: ['EavAttributeSets']);
        $this->set(compact('eavAttribute'));
    }

    /**
     * Add method
     *
     * @return Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $eavAttribute = $this->EavAttributes->newEmptyEntity();
        if ($this->request->is('post')) {
            $eavAttribute = $this->EavAttributes->patchEntity($eavAttribute, $this->request->getData());
            if ($this->EavAttributes->save($eavAttribute)) {
                $this->Flash->success(__('The attribute has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The attribute could not be saved. Please, try again.'));
        }
        $eavAttributeSets = $this->EavAttributes->EavAttributeSets->find('list', limit: 500)->all();
        $dataTypeOptions = $this->Eav->getDataTypeOptions();
        $this->set(compact('eavAttribute', 'eavAttributeSets', 'dataTypeOptions'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Eav Attribute id.
     * @return Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws RecordNotFoundException When record not found.
     */
    public function edit(string $id = null)
    {
        $eavAttribute = $this->EavAttributes->get($id, contain: ['EavAttributeSets']);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $eavAttribute = $this->EavAttributes->patchEntity($eavAttribute, $this->request->getData());
            if ($this->EavAttributes->save($eavAttribute)) {
                $this->Flash->success(__('The attribute has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The attribute could not be saved. Please, try again.'));
        }
        $eavAttributeSets = $this->EavAttributes->EavAttributeSets->find('list', limit: 500)->all();
        $dataTypeOptions = $this->Eav->getDataTypeOptions();
        $this->set(compact('eavAttribute', 'eavAttributeSets', 'dataTypeOptions'));
    }

    /**
     * Delete method
     *
     * Guardrail: EavAttributesTable::beforeDelete prevents deletion if attribute is used by any set.
     *
     * @param string|null $id Eav Attribute id.
     * @return Response|null Redirects to index.
     * @throws RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $eavAttribute = $this->EavAttributes->get($id);
        if ($this->EavAttributes->delete($eavAttribute)) {
            $this->Flash->success(__('The attribute has been deleted.'));
        } else {
            // If blocked by guardrail, surface the entity error message if present
            $err = $eavAttribute->getError('id') ? ' ' . implode('; ', (array)$eavAttribute->getError('id')) : '';
            $this->Flash->error(__('The attribute could not be deleted.') . $err);
        }

        return $this->redirect(['action' => 'index']);
    }
}
