<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttributeSet
 * @var string[]|\Cake\Collection\CollectionInterface $eavAttributes
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $eavAttributeSet->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $eavAttributeSet->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Eav Attribute Sets'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributeSets form content">
            <?= $this->Form->create($eavAttributeSet) ?>
            <fieldset>
                <legend><?= __('Edit Eav Attribute Set') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    echo $this->Form->control('eav_attributes._ids', [
                        'type' => 'select',
                        'multiple' => 'checkbox',
                        'options' => $eavAttributes,
                        'label' => __('Attributes'),
                        'help' => __('Check attributes to include in this set.'),
                    ]);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
