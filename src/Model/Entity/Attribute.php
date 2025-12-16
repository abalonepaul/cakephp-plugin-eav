<?php
declare(strict_types=1);

namespace Eav\Model\Entity;

use Cake\ORM\Entity;

class Attribute extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
