<?php
App::uses('EavAppController', 'Eav.Controller');
/**
 * Eav Attributes Controller
 *
 * This file is contains the DataTypesController class
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
 * Data Types Controller
 *
 * Methods to manage Data Types. Data Types map to the CakePHP data types.
 *
 * @package       plugins.Eav.Controller
 *
  */
class DataTypesController extends EavAppController {


/**
 * View a list of Data Types
 *
 * @return void
 */
    public function admin_index() {
        $this->DataType->recursive = 0;
        $this->set('dataTypes', $this->paginate());
    }

/**
 * View a single Data Type
 *
 * @param string $id
 * @return void
 */
    public function admin_view($id = null) {
        $this->DataType->id = $id;
        if (!$this->DataType->exists()) {
            throw new NotFoundException(__('Invalid data type'));
        }
        $this->set('dataType', $this->DataType->read(null, $id));
    }

/**
 * Add a New Data Type
 *
 * @return void
 */
    public function admin_add() {
        if ($this->request->is('post')) {
            $this->DataType->create();
            if ($this->DataType->save($this->request->data)) {
                $this->Session->setFlash(__('The data type has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The data type could not be saved. Please, try again.'));
            }
        }
    }

/**
 * Edit a Data Type
 *
 * @param string $id
 * @return void
 */
    public function admin_edit($id = null) {
        $this->DataType->id = $id;
        if (!$this->DataType->exists()) {
            throw new NotFoundException(__('Invalid data type'));
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            if ($this->DataType->save($this->request->data)) {
                $this->Session->setFlash(__('The data type has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The data type could not be saved. Please, try again.'));
            }
        } else {
            $this->request->data = $this->DataType->read(null, $id);
        }
    }

/**
 * Delete a Data Type
 *
 * @param string $id
 * @return void
 */
    public function admin_delete($id = null) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }
        $this->DataType->id = $id;
        if (!$this->DataType->exists()) {
            throw new NotFoundException(__('Invalid data type'));
        }
        if ($this->DataType->delete()) {
            $this->Session->setFlash(__('Data type deleted'));
            $this->redirect(array('action'=>'index'));
        }
        $this->Session->setFlash(__('Data type was not deleted'));
        $this->redirect(array('action' => 'index'));
    }
}
