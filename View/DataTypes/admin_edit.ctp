<div class="dataTypes form">
<?php echo $this->Form->create('DataType');?>
	<fieldset>
		<legend><?php echo __('Admin Edit Data Type'); ?></legend>
	<?php
		echo $this->Form->input('id');
		echo $this->Form->input('name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit'));?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $this->Form->value('DataType.id')), null, __('Are you sure you want to delete # %s?', $this->Form->value('DataType.id'))); ?></li>
		<li><?php echo $this->Html->link(__('List Data Types'), array('action' => 'index'));?></li>
		<li><?php echo $this->Html->link(__('List Attributes'), array('controller' => 'attributes', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Attribute'), array('controller' => 'attributes', 'action' => 'add')); ?> </li>
	</ul>
</div>
