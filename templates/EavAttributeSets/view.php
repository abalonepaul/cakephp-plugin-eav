<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttributeSet
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Eav Attribute Set'), ['action' => 'edit', $eavAttributeSet->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Eav Attribute Set'), ['action' => 'delete', $eavAttributeSet->id], ['confirm' => __('Are you sure you want to delete # {0}?', $eavAttributeSet->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Eav Attribute Sets'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Eav Attribute Set'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributeSets view content">
            <h3><?= h($eavAttributeSet->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($eavAttributeSet->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($eavAttributeSet->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($eavAttributeSet->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($eavAttributeSet->modified) ?></td>
                </tr>
            </table>
            <div class="related">
                <h4><?= __('Related Eav Attributes') ?></h4>
                <?php if (!empty($eavAttributeSet->eav_attributes)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Label') ?></th>
                            <th><?= __('Data Type') ?></th>
                            <th><?= __('Options') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($eavAttributeSet->eav_attributes as $eavAttribute) : ?>
                        <tr>
                            <td><?= h($eavAttribute->id) ?></td>
                            <td><?= h($eavAttribute->name) ?></td>
                            <td><?= h($eavAttribute->label) ?></td>
                            <td><?= h($eavAttribute->data_type) ?></td>
                            <td><?= h($eavAttribute->options) ?></td>
                            <td><?= h($eavAttribute->created) ?></td>
                            <td><?= h($eavAttribute->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'EavAttributes', 'action' => 'view', $eavAttribute->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'EavAttributes', 'action' => 'edit', $eavAttribute->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'EavAttributes', 'action' => 'delete', $eavAttribute->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $eavAttribute->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>