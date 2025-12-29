<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\Cake\Datasource\EntityInterface> $eavAttributeSets
 */
?>
<div class="eavAttributeSets index content">
    <?= $this->Html->link(__('New Eav Attribute Set'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Eav Attribute Sets') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eavAttributeSets as $eavAttributeSet): ?>
                <tr>
                    <td><?= h($eavAttributeSet->id) ?></td>
                    <td><?= h($eavAttributeSet->name) ?></td>
                    <td><?= h($eavAttributeSet->created) ?></td>
                    <td><?= h($eavAttributeSet->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $eavAttributeSet->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $eavAttributeSet->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $eavAttributeSet->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $eavAttributeSet->id),
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</div>