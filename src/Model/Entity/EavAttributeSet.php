<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;

/**
 * EavAttributeSet Entity
 *
 * @property string $id
 * @property string $name
 * @property DateTime $created
 * @property DateTime|null $modified
 *
 * @property EavAttribute[] $eav_attributes
 */
class EavAttributeSet extends Entity
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
        'created' => true,
        'modified' => true,
        'eav_attributes' => true,
    ];
}
