<?php
App::uses('EavAppController', 'Eav.Controller');
/**
 * Eav Attributes Controller
 *
 * This file is contains the AttributesController class
 *
 * PHP 5
 *
 * Protelligence (http://cakephp.org)
 * Copyright 2009-2013, Protelligence (http://www.protelligence.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2009-2013, Protelligence (http://www.protelligence.com)
 * @link          http://www.protelligence.com Protelligence
 * @package       plugins.Eav.Controller
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
/**
 * Attributes Controller
 *
 * Methods to manage Attributes. Attributes are the dynamic fields added to an Entity
 *
 * @package       plugins.Eav.Controller
  */
class AttributesController extends EavAppController {


/**
 * List the attributes
 *
 * @return void
 */
    public function admin_index() {
        $this->Attribute->recursive = 0;
        $this->set('attributes', $this->paginate());
    }

/**
 * View an Attribute
 *
 * @param string $id
 * @return void
 */
    public function admin_view($id = null) {
        $this->Attribute->id = $id;
        if (!$this->Attribute->exists()) {
            throw new NotFoundException(__('Invalid attribute'));
        }
        $this->set('attribute', $this->Attribute->read(null, $id));
    }

/**
 * Add a new Attribute
 *
 * @return void
 */
    public function admin_add() {
        if ($this->request->is('post')) {
            $this->Attribute->create();
            if ($this->Attribute->save($this->request->data)) {
                $this->Session->setFlash(__('The attribute has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The attribute could not be saved. Please, try again.'));
            }
        }
        $entityTypes = $this->Attribute->EntityType->find('list');
        $dataTypes = $this->Attribute->DataType->find('list');
        $this->set(compact('entityTypes', 'dataTypes', 'userTypes'));
    }

/**
 * Edit an Attribute
 *
 * @param string $id
 * @return void
 */
    public function admin_edit($id = null) {
        $this->Attribute->id = $id;
        if (!$this->Attribute->exists()) {
            throw new NotFoundException(__('Invalid attribute'));
        }
        if ($this->request->isPost() || $this->request->isPut()) {
            if ($this->Attribute->save($this->request->data)) {
                $this->Session->setFlash(__('The attribute has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The attribute could not be saved. Please, try again.'));
            }
        } else {
            $this->request->data = $this->Attribute->read(null, $id);
        }
        $entityTypes = $this->Attribute->EntityType->find('list');
        $dataTypes = $this->Attribute->DataType->find('list');
        $this->set(compact('entityTypes', 'dataTypes', 'userTypes'));
    }

/**
 * Delete an attribute
 *
 * @param string $id
 * @return void
 */
    public function admin_delete($id = null) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }
        $this->Attribute->id = $id;
        if (!$this->Attribute->exists()) {
            throw new NotFoundException(__('Invalid attribute'));
        }
        if ($this->Attribute->delete()) {
            $this->Session->setFlash(__('Attribute deleted'));
            $this->redirect(array('action'=>'index'));
        }
        $this->Session->setFlash(__('Attribute was not deleted'));
        $this->redirect(array('action' => 'index'));
    }
}
