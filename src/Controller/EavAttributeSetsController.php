<?php
declare(strict_types=1);

namespace Eav\Controller;

use App\Controller\AppController;

/**
 * EavAttributeSets Controller
 *
 * @property \Eav\Model\Table\EavAttributeSetsTable $EavAttributeSets
 */
class EavAttributeSetsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->EavAttributeSets->find();
        $eavAttributeSets = $this->paginate($query);

        $this->set(compact('eavAttributeSets'));
    }

    /**
     * View method
     *
     * @param string|null $id Eav Attribute Set id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $eavAttributeSet = $this->EavAttributeSets->get($id, contain: ['EavAttributes']);
        $this->set(compact('eavAttributeSet'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $eavAttributeSet = $this->EavAttributeSets->newEmptyEntity();
        if ($this->request->is('post')) {
            $eavAttributeSet = $this->EavAttributeSets->patchEntity($eavAttributeSet, $this->request->getData());
            if ($this->EavAttributeSets->save($eavAttributeSet)) {
                $this->Flash->success(__('The eav attribute set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The eav attribute set could not be saved. Please, try again.'));
        }
        $eavAttributes = $this->EavAttributeSets->EavAttributes->find('list', limit: 200)->all();
        $this->set(compact('eavAttributeSet', 'eavAttributes'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Eav Attribute Set id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $eavAttributeSet = $this->EavAttributeSets->get($id, contain: ['EavAttributes']);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $eavAttributeSet = $this->EavAttributeSets->patchEntity($eavAttributeSet, $this->request->getData());
            if ($this->EavAttributeSets->save($eavAttributeSet)) {
                $this->Flash->success(__('The eav attribute set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The eav attribute set could not be saved. Please, try again.'));
        }
        $eavAttributes = $this->EavAttributeSets->EavAttributes->find('list', limit: 200)->all();
        $this->set(compact('eavAttributeSet', 'eavAttributes'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Eav Attribute Set id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $eavAttributeSet = $this->EavAttributeSets->get($id);
        if ($this->EavAttributeSets->delete($eavAttributeSet)) {
            $this->Flash->success(__('The eav attribute set has been deleted.'));
        } else {
            $this->Flash->error(__('The eav attribute set could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
