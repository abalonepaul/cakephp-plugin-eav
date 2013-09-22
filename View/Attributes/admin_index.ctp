<div class="attributes index">
	<h2><?php echo __('Attributes');?></h2>
	<table class="table table-striped">
	<tr>
			<th><?php echo $this->Paginator->sort('id');?></th>
			<th><?php echo $this->Paginator->sort('name');?></th>
			<th><?php echo $this->Paginator->sort('description');?></th>
			<th><?php echo $this->Paginator->sort('entity_type_id');?></th>
			<th><?php echo $this->Paginator->sort('data_type_id');?></th>
			<th class="actions"><?php echo __('Actions');?></th>
	</tr>
	<?php
	$i = 0;
	foreach ($attributes as $attribute): ?>
	<tr>
		<td><?php echo h($attribute['Attribute']['id']); ?>&nbsp;</td>
		<td><?php echo h($attribute['Attribute']['name']); ?>&nbsp;</td>
		<td><?php echo h($attribute['Attribute']['description']); ?>&nbsp;</td>
		<td>
			<?php echo $this->Html->link($attribute['EntityType']['name'], array('controller' => 'entity_types', 'action' => 'view', $attribute['EntityType']['id'])); ?>
		</td>
		<td>
			<?php echo $this->Html->link($attribute['DataType']['name'], array('controller' => 'data_types', 'action' => 'view', $attribute['DataType']['id'])); ?>
		</td>
		<td class="actions">
		  <div class="btn-group">
		  <button class="btn dropdown-toggle" data-toggle="dropdown"><?php echo __('Actions'); ?><span class="caret"></span></button>
		  <ul class="dropdown-menu">
			<li><?php echo $this->Html->link(__('View'), array('action' => 'view', $attribute['Attribute']['id'])); ?></li>
			<li><?php echo $this->Html->link(__('Edit'), array('action' => 'edit', $attribute['Attribute']['id'])); ?></li>
			<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $attribute['Attribute']['id']), null, __('Are you sure you want to delete # %s?', $attribute['Attribute']['id'])); ?></li>
		  </ul>
		  </div>
		</td>
	</tr>
<?php endforeach; ?>
	</table>
	<ul>
	<?php
	echo $this->Paginator->counter(array(
	'format' => __('Page {:page} of {:pages}, showing {:current} records out of {:count} total, starting on record {:start}, ending on {:end}')
	));
	?>	</ul>

	<ul class="pager">
	<?php
		echo $this->Paginator->prev('< ' . __('previous'), array(), null, array('class' => 'previous', 'tag'=>'li'));
		echo $this->Paginator->numbers(array('separator' => '', 'tag'=>'li'));
		echo $this->Paginator->next(__('next') . ' >', array(), null, array('class' => 'next', 'tag'=>'li'));
	?>
	</ul>
</div>
<div class="btn-group">
	<button class="btn dropdown-toggle" data-toggle="dropdown"><?php echo __('Actions'); ?><span class="caret"></span></button>
	<ul class="dropdown-menu">
		<li><?php echo $this->Html->link(__('New Attribute'), array('action' => 'add')); ?></li>
		<li><?php echo $this->Html->link(__('List Entity Types'), array('controller' => 'entity_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Entity Type'), array('controller' => 'entity_types', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Data Types'), array('controller' => 'data_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Data Type'), array('controller' => 'data_types', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List User Types'), array('controller' => 'user_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New User Type'), array('controller' => 'user_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
