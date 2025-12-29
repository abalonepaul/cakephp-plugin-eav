<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\Cake\Datasource\EntityInterface> $eavAttributes
 */
use Cake\ORM\TableRegistry;
?>
<div class="eavAttributes index content">
    <?= $this->Html->link(__('New Eav Attribute'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Eav Attributes') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th><?= $this->Paginator->sort('id') ?></th>
                <th><?= $this->Paginator->sort('name') ?></th>
                <th><?= $this->Paginator->sort('label') ?></th>
                <th><?= $this->Paginator->sort('data_type') ?></th>
                <th><?= $this->Paginator->sort('options') ?></th>
                <th><?= $this->Paginator->sort('created') ?></th>
                <th><?= $this->Paginator->sort('modified') ?></th>
                <th><?= $this->Paginator->sort('actions') ?></th>
            </tr>
            </thead>
            <tbody>
                <?php foreach ($eavAttributes as $eavAttribute): ?>
                <tr>
                    <td><?= h($eavAttribute->id) ?></td>
                    <td><?= h($eavAttribute->name) ?></td>
                    <td><?= h($eavAttribute->label) ?></td>
                    <td><?= h($eavAttribute->data_type) ?></td>
                    <td><?= h($eavAttribute->options) ?></td>
                    <td><?= h($eavAttribute->created) ?></td>
                    <td><?= h($eavAttribute->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $eavAttribute->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $eavAttribute->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $eavAttribute->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $eavAttribute->id),
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
