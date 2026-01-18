<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveFieldsFromEavAttributes extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        if ($this->hasTable('eav_attributes')) {

            $table = $this->table('eav_attributes');
            if ($table->hasColumn('label')) {
                $table->removeColumn('label');
            }
            if ($table->hasColumn('options')) {
                $table->removeColumn('options');
            }
            $table->update();
        }
    }
}
