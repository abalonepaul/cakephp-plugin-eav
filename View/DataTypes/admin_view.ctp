<div class="dataTypes view">
<h2><?php  echo __('Data Type');?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($dataType['DataType']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($dataType['DataType']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Created'); ?></dt>
		<dd>
			<?php echo h($dataType['DataType']['created']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Modified'); ?></dt>
		<dd>
			<?php echo h($dataType['DataType']['modified']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Data Type'), array('action' => 'edit', $dataType['DataType']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Data Type'), array('action' => 'delete', $dataType['DataType']['id']), null, __('Are you sure you want to delete # %s?', $dataType['DataType']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Data Types'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Data Type'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Attributes'), array('controller' => 'attributes', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Attribute'), array('controller' => 'attributes', 'action' => 'add')); ?> </li>
	</ul>
</div>
<div class="related">
	<h3><?php echo __('Related Attributes');?></h3>
	<?php if (!empty($dataType['Attribute'])):?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Name'); ?></th>
		<th><?php echo __('Description'); ?></th>
		<th><?php echo __('Entity Type Id'); ?></th>
		<th><?php echo __('Data Type Id'); ?></th>
		<th><?php echo __('Created'); ?></th>
		<th><?php echo __('Modified'); ?></th>
		<th class="actions"><?php echo __('Actions');?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($dataType['Attribute'] as $attribute): ?>
		<tr>
			<td><?php echo $attribute['id'];?></td>
			<td><?php echo $attribute['name'];?></td>
			<td><?php echo $attribute['description'];?></td>
			<td><?php echo $attribute['entity_type_id'];?></td>
			<td><?php echo $attribute['data_type_id'];?></td>
			<td><?php echo $attribute['created'];?></td>
			<td><?php echo $attribute['modified'];?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'attributes', 'action' => 'view', $attribute['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'attributes', 'action' => 'edit', $attribute['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'attributes', 'action' => 'delete', $attribute['id']), null, __('Are you sure you want to delete # %s?', $attribute['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New Attribute'), array('controller' => 'attributes', 'action' => 'add'));?> </li>
		</ul>
	</div>
</div>
