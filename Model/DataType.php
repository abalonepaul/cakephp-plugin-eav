<?php
App::uses('EavAppModel', 'Eav.Model');
/**
 * Eav Data Type Model
 *
 * This file is contains the DataType class
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
 * Data Type Model
 *
 * Validations, Associations, and Methods for Data Types. Data Types map to the CakePHP data types.
 *
 * @package       plugins.Eav.Model
 *
 */
class DataType extends EavAppModel {
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
                'message' => 'Please enter the name of the Data Type',
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
        'Eav.Attribute' => array(
            'className' => 'Attribute',
            'foreignKey' => 'data_type_id',
            'dependent' => false,
        )
    );

}
