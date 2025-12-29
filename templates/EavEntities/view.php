<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavEntity
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Eav Entity'), ['action' => 'edit', $eavEntity->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Eav Entity'), ['action' => 'delete', $eavEntity->id], ['confirm' => __('Are you sure you want to delete # {0}?', $eavEntity->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Eav Entities'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Eav Entity'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavEntities view content">
            <h3><?= h($eavEntity->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($eavEntity->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($eavEntity->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Model Alias') ?></th>
                    <td><?= h($eavEntity->model_alias) ?></td>
                </tr>
                <tr>
                    <th><?= __('Table Name') ?></th>
                    <td><?= h($eavEntity->table_name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Storage Default') ?></th>
                    <td><?= h($eavEntity->storage_default) ?></td>
                </tr>
                <tr>
                    <th><?= __('Json Column') ?></th>
                    <td><?= h($eavEntity->json_column) ?></td>
                </tr>
                <tr>
                    <th><?= __('Pk Type') ?></th>
                    <td><?= h($eavEntity->pk_type) ?></td>
                </tr>
                <tr>
                    <th><?= __('Uuid Subtype') ?></th>
                    <td><?= h($eavEntity->uuid_subtype) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($eavEntity->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($eavEntity->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>