<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;

/**
 * EavAttribute Entity
 *
 * @property string $id
 * @property string $name
 * @property string $data_type
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property DateTime $created
 * @property DateTime|null $modified
 */
class EavAttribute extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'data_type' => true,
        'placeholder' => true,
        'help_text' => true,
        'created' => true,
        'modified' => true,
    ];
}
