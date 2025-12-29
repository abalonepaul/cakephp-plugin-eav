<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

/**
 * EavEntity Entity
 *
 * @property string $id
 * @property string $name
 * @property string|null $model_alias
 * @property string|null $table_name
 * @property string $storage_default
 * @property string|null $json_column
 * @property string $pk_type
 * @property string|null $uuid_subtype
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class EavEntity extends Entity
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
        'model_alias' => true,
        'table_name' => true,
        'storage_default' => true,
        'json_column' => true,
        'pk_type' => true,
        'uuid_subtype' => true,
        'created' => true,
        'modified' => true,
    ];
}
