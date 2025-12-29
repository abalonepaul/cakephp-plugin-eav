<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavAttribute
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Eav Attribute'), ['action' => 'edit', $eavAttribute->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Eav Attribute'), ['action' => 'delete', $eavAttribute->id], ['confirm' => __('Are you sure you want to delete # {0}?', $eavAttribute->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Eav Attributes'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Eav Attribute'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavAttributes view content">
            <h3><?= h($eavAttribute->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($eavAttribute->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($eavAttribute->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Label') ?></th>
                    <td><?= h($eavAttribute->label) ?></td>
                </tr>
                <tr>
                    <th><?= __('Data Type') ?></th>
                    <td><?= h($eavAttribute->data_type) ?></td>
                </tr>
                <tr>
                    <th><?= __('Options') ?></th>
                    <td><?= h($eavAttribute->options) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($eavAttribute->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($eavAttribute->modified) ?></td>
                </tr>
            </table>
            <div class="related">
                <h4><?= __('Related Eav Attribute Sets') ?></h4>
                <?php if (!empty($eavAttribute->eav_attribute_sets)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($eavAttribute->eav_attribute_sets as $eavAttributeSet) : ?>
                        <tr>
                            <td><?= h($eavAttributeSet->id) ?></td>
                            <td><?= h($eavAttributeSet->name) ?></td>
                            <td><?= h($eavAttributeSet->created) ?></td>
                            <td><?= h($eavAttributeSet->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'EavAttributeSets', 'action' => 'view', $eavAttributeSet->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'EavAttributeSets', 'action' => 'edit', $eavAttributeSet->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'EavAttributeSets', 'action' => 'delete', $eavAttributeSet->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $eavAttributeSet->id),
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