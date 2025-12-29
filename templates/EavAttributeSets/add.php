<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttributeSet
 * @var \Cake\Collection\CollectionInterface|string[] $eavAttributes
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Eav Attribute Sets'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributeSets form content">
            <?= $this->Form->create($eavAttributeSet) ?>
            <fieldset>
                <legend><?= __('Add Eav Attribute Set') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    // Friendlier attribute membership (checkboxes)
                    echo $this->Form->control('eav_attributes._ids', [
                        'type' => 'select',
                        'multiple' => 'checkbox',
                        'options' => $eavAttributes,
                        'label' => __('Attributes'),
                        'help' => __('Select one or more attributes to include in this set.'),
                    ]);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
