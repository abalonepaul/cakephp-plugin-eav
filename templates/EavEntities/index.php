<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\Cake\Datasource\EntityInterface> $eavEntities
 */
?>
<div class="eavEntities index content">
    <?= $this->Html->link(__('New Eav Entity'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Eav Entities') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('model_alias') ?></th>
                    <th><?= $this->Paginator->sort('table_name') ?></th>
                    <th><?= $this->Paginator->sort('storage_default') ?></th>
                    <th><?= $this->Paginator->sort('json_column') ?></th>
                    <th><?= $this->Paginator->sort('pk_type') ?></th>
                    <th><?= $this->Paginator->sort('uuid_subtype') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eavEntities as $eavEntity): ?>
                <tr>
                    <td><?= h($eavEntity->id) ?></td>
                    <td><?= h($eavEntity->name) ?></td>
                    <td><?= h($eavEntity->model_alias) ?></td>
                    <td><?= h($eavEntity->table_name) ?></td>
                    <td><?= h($eavEntity->storage_default) ?></td>
                    <td><?= h($eavEntity->json_column) ?></td>
                    <td><?= h($eavEntity->pk_type) ?></td>
                    <td><?= h($eavEntity->uuid_subtype) ?></td>
                    <td><?= h($eavEntity->created) ?></td>
                    <td><?= h($eavEntity->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $eavEntity->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $eavEntity->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $eavEntity->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $eavEntity->id),
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