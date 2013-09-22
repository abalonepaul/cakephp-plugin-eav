<?php
App::uses('EavAppModel', 'Eav.Model');
/**
 * Eav Data Type Model
 *
 * This file is contains the Attribute Model class
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
 * Attribute Model
 *
 * Validations, Associations, and Methods for Attributes. Attributes are the dynamc fields added to a model.
 *
 * @package       plugins.Eav.Model
 *
 */
class Attribute extends EavAppModel {
    public $uses = 'Eav.Attribute';
/**
 * Display field
 *
 * @var string
 */
    public $displayField = 'name';

    //public $actsAs = array('Containable');
/**
 * Validation rules
 *
 * @var array
 */
    public $validate = array(
        'id' => array(
            'notempty' => array(
                'rule' => array('notempty'),
                'message' => 'The Id is missing',
                'allowEmpty' => false,
            ),
        ),
        'name' => array(
            'notempty' => array(
                'rule' => array('notempty'),
                'message' => 'Attribute Name can not be empty',
                'allowEmpty' => false,
            ),
        ),
        'entity_type_id' => array(
            'numeric' => array(
                'rule' => array('numeric'),
                'message' => 'The Entity Type is incorrect',
                'allowEmpty' => false,
            ),
        ),
        'data_type_id' => array(
            'numeric' => array(
                'rule' => array('numeric'),
                'message' => 'The Data Type is incorrect',
                'allowEmpty' => false,
            ),
        ),
    );

    //The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
    public $belongsTo = array(
        'EntityType' => array(
            'className' => 'EntityType',
            'foreignKey' => 'entity_type_id',
            'conditions' => '',
            'fields' => '',
            'order' => ''
        ),
        'DataType' => array(
            'className' => 'DataType',
            'foreignKey' => 'data_type_id',
            'conditions' => '',
            'fields' => '',
            'order' => ''
        )
    );


}
