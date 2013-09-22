<?php
App::uses('EavAppModel', 'Eav.Model');
/**
 * Eav Entity Type Model
 *
 * This file is contains the EntityType class
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
 * @package       plugins.Eav.Model
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
/**
 * Entity Type Model
 *
 * Validations, Associations, and Methods for Entity Types. You need an entity type for each model/object that
 * uses the Eav Behavior
 *
 * @package       plugins.Eav.Model
 *
  */
class EntityType extends EavAppModel {

/**
 * Display field
 *
 * @var string
 */
    public $displayField = 'name';

/**
 * Validation rules
 *
 * @var array
 */
    public $validate = array(
        'id' => array(
            'numeric' => array(
                'rule' => array('numeric'),
                'message' => 'The id should be an integer.',
                'allowEmpty' => false,
                'on' => 'create', // Limit validation to 'create' or 'update' operations
            ),
        ),
        'name' => array(
            'notempty' => array(
                'rule' => array('notempty'),
                'message' => 'Please Enter the name of the Entity Type',
                'allowEmpty' => false,
                'required' => false,
            ),
        ),
    );

    //The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * hasMany associations
 *
 * @var array
 */
    public $hasMany = array(
        'Attribute' => array(
            'className' => 'Attribute',
            'foreignKey' => 'entity_type_id',
            'dependent' => false,
        )
    );

}
