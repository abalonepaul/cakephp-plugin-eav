<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

/**
 * EavAttribute Entity
 *
 * @property string $id
 * @property string $name
 * @property string|null $label
 * @property string $data_type
 * @property array $options
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class EavAttribute extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'label' => true,
        'data_type' => true,
        'options' => true,
        'created' => true,
        'modified' => true,
    ];
}
