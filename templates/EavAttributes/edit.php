<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttribute
 * @var string[]|\Cake\Collection\CollectionInterface $eavAttributeSets
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $eavAttribute->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $eavAttribute->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Eav Attributes'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributes form content">
            <?= $this->Form->create($eavAttribute) ?>
            <fieldset>
                <legend><?= __('Edit Eav Attribute') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    echo $this->Form->control('label');
                    echo $this->Form->control('data_type');
                    echo $this->Form->control('options', [
                        'type' => 'textarea',
                        'rows' => 3,
                        'help' => __('JSON metadata for this attribute (e.g., UI hints or validation rules). Leave "{}" if not used.'),
                    ]);
                    echo $this->Form->control('eav_attribute_sets._ids', ['options' => $eavAttributeSets]);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
