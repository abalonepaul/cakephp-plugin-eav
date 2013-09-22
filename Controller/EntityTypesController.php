<?php
App::uses('EavAppController', 'Eav.Controller');
/**
 * Eav Entity Types Controller
 *
 * This file is contains the EntityTypesController class
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
 * Entity Types Controller
 *
 * Methods to manage Entity Types. You need an entity type for each model/object.
 *
 * @package       plugins.Eav.Controller
  */
class EntityTypesController extends EavAppController {


/**
 * List the Entity Types
 *
 * @return void
 */
    public function admin_index() {
        $this->EntityType->recursive = 0;
        $this->set('entityTypes', $this->paginate());
    }

/**
 * View a single Entity Type
 *
 * @param string $id
 * @return void
 */
    public function admin_view($id = null) {
        $this->EntityType->id = $id;
        if (!$this->EntityType->exists()) {
            throw new NotFoundException(__('Invalid entity type'));
        }
        $this->set('entityType', $this->EntityType->read(null, $id));
    }

/**
 * Add a new Entity Type
 *
 * @return void
 */
    public function admin_add() {
        if ($this->request->is('post')) {
            $this->EntityType->create();
            if ($this->EntityType->save($this->request->data)) {
                $this->Session->setFlash(__('The entity type has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The entity type could not be saved. Please, try again.'));
            }
        }
    }

/**
 * Edit an Entity Type
 *
 * @param string $id
 * @return void
 */
    public function admin_edit($id = null) {
        $this->EntityType->id = $id;
        if (!$this->EntityType->exists()) {
            throw new NotFoundException(__('Invalid entity type'));
        }
        if ($this->request->is('post') || $this->request->is('put')) {
            if ($this->EntityType->save($this->request->data)) {
                $this->Session->setFlash(__('The entity type has been saved'));
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The entity type could not be saved. Please, try again.'));
            }
        } else {
            $this->request->data = $this->EntityType->read(null, $id);
        }
    }

/**
 * admin_delete method
 *
 * @param string $id
 * @return void
 */
    public function admin_delete($id = null) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }
        $this->EntityType->id = $id;
        if (!$this->EntityType->exists()) {
            throw new NotFoundException(__('Invalid entity type'));
        }
        if ($this->EntityType->delete()) {
            $this->Session->setFlash(__('Entity type deleted'));
            $this->redirect(array('action'=>'index'));
        }
        $this->Session->setFlash(__('Entity type was not deleted'));
        $this->redirect(array('action' => 'index'));
    }
}
