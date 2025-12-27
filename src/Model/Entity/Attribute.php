<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property array $options
 * @property string $id
 * @property string $name
 * @property string|null $label
 * @property string $data_type
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Attribute extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
