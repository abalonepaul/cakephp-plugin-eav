<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $eavEntity
 * @var array $entityNameChoices
 * @var array $storageDefaults
 * @var array $pkTypes
 * @var array $uuidSubtypes
 * @var array $suggestions
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $eavEntity->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $eavEntity->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Eav Entities'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="eavEntities form content">
            <?= $this->Form->create($eavEntity) ?>
            <fieldset>
                <legend><?= __('Edit Eav Entity') ?></legend>
                <?php
                    echo $this->Form->control('name', [
                        'type' => 'select',
                        'options' => $entityNameChoices ?? [],
                        'empty' => __('Select an entity/table'),
                        'id' => 'entity-name',
                    ]);
                    echo $this->Form->control('model_alias', ['id' => 'model-alias']);
                    echo $this->Form->control('table_name', ['id' => 'table-name']);
                    echo $this->Form->control('storage_default', [
                        'type' => 'select',
                        'options' => $storageDefaults ?? [],
                        'id' => 'storage-default',
                    ]);
                    echo $this->Form->control('json_column', ['id' => 'json-column']);
                    echo $this->Form->control('pk_type', [
                        'type' => 'select',
                        'options' => $pkTypes ?? [],
                        'id' => 'pk-type',
                    ]);
                    echo $this->Form->control('uuid_subtype', [
                        'type' => 'select',
                        'options' => $uuidSubtypes ?? [],
                        'id' => 'uuid-subtype',
                    ]);
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
(function() {
    const suggestions = <?= json_encode($suggestions ?? [], JSON_UNESCAPED_SLASHES) ?>;
    const nameSel = document.getElementById('entity-name');
    const aliasInput = document.getElementById('model-alias');
    const tableInput = document.getElementById('table-name');
    const storageSel = document.getElementById('storage-default');
    const jsonColWrap = document.getElementById('json-column')?.closest('.input');
    const pkSel = document.getElementById('pk-type');
    const uuidWrap = document.getElementById('uuid-subtype')?.closest('.input');

    function updateFromName() {
        const val = nameSel ? nameSel.value : '';
        if (val && suggestions[val]) {
            // Only fill if empty to avoid overwriting edits
            if (aliasInput && !aliasInput.value) aliasInput.value = suggestions[val].alias || '';
            if (tableInput && !tableInput.value) tableInput.value = suggestions[val].table || '';
        }
    }
    function updateStorageVisibility() {
        if (!storageSel || !jsonColWrap) return;
        jsonColWrap.style.display = (storageSel.value === 'json_column') ? '' : 'none';
    }
    function updateUuidVisibility() {
        if (!pkSel || !uuidWrap) return;
        uuidWrap.style.display = (pkSel.value === 'uuid') ? '' : 'none';
    }

    nameSel && nameSel.addEventListener('change', updateFromName);
    storageSel && storageSel.addEventListener('change', updateStorageVisibility);
    pkSel && pkSel.addEventListener('change', updateUuidVisibility);

    // Initial state
    updateFromName();
    updateStorageVisibility();
    updateUuidVisibility();
})();
</script>
