<div class="attributes view">
<h2><?php  echo __('Attribute');?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($attribute['Attribute']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($attribute['Attribute']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Description'); ?></dt>
		<dd>
			<?php echo h($attribute['Attribute']['description']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Entity Type'); ?></dt>
		<dd>
			<?php echo $this->Html->link($attribute['EntityType']['name'], array('controller' => 'entity_types', 'action' => 'view', $attribute['EntityType']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Data Type'); ?></dt>
		<dd>
			<?php echo $this->Html->link($attribute['DataType']['name'], array('controller' => 'data_types', 'action' => 'view', $attribute['DataType']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Created'); ?></dt>
		<dd>
			<?php echo h($attribute['Attribute']['created']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Modified'); ?></dt>
		<dd>
			<?php echo h($attribute['Attribute']['modified']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Attribute'), array('action' => 'edit', $attribute['Attribute']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Attribute'), array('action' => 'delete', $attribute['Attribute']['id']), null, __('Are you sure you want to delete # %s?', $attribute['Attribute']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Attributes'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Attribute'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Entity Types'), array('controller' => 'entity_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Entity Type'), array('controller' => 'entity_types', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Data Types'), array('controller' => 'data_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Data Type'), array('controller' => 'data_types', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List User Types'), array('controller' => 'user_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New User Type'), array('controller' => 'user_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
<div class="related">
	<h3><?php echo __('Related User Types');?></h3>
	<?php if (!empty($attribute['UserType'])):?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Name'); ?></th>
		<th><?php echo __('Created'); ?></th>
		<th><?php echo __('Modified'); ?></th>
		<th class="actions"><?php echo __('Actions');?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($attribute['UserType'] as $userType): ?>
		<tr>
			<td><?php echo $userType['id'];?></td>
			<td><?php echo $userType['name'];?></td>
			<td><?php echo $userType['created'];?></td>
			<td><?php echo $userType['modified'];?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'user_types', 'action' => 'view', $userType['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'user_types', 'action' => 'edit', $userType['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'user_types', 'action' => 'delete', $userType['id']), null, __('Are you sure you want to delete # %s?', $userType['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New User Type'), array('controller' => 'user_types', 'action' => 'add'));?> </li>
		</ul>
	</div>
</div>
