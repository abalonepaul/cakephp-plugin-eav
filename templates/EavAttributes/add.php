<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttribute
 * @var \Cake\Collection\CollectionInterface|string[] $eavAttributeSets
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Eav Attributes'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributes form content">
            <?= $this->Form->create($eavAttribute) ?>
            <fieldset>
                <legend><?= __('Add Eav Attribute') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    echo $this->Form->control('data_type');
                    echo $this->Form->control('eav_attribute_sets._ids', ['options' => $eavAttributeSets]);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
