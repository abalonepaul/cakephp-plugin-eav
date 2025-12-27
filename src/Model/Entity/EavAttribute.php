<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

/**
 * EAV attribute registry entity (eav_attributes).
 *
 * @property string $id
 * @property string $name
 * @property string|null $label
 * @property string $data_type
 * @property array $options
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class EavAttribute extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
