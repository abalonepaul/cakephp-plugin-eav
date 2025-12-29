<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

/**
 * EavAttributeSetsEavAttribute Entity
 *
 * @property string $attribute_set_id
 * @property string $attribute_id
 * @property int|null $position
 *
 * @property \Eav\Model\Entity\EavAttributeSet $attribute_set
 * @property \Eav\Model\Entity\EavAttribute $attribute
 */
class EavAttributeSetsEavAttribute extends Entity
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
        'position' => true,
        'attribute_set' => true,
        'attribute' => true,
    ];
}
