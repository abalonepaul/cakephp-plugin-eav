<div class="attributes form">
<?php echo $this->Form->create('Attribute');?>
	<fieldset>
		<legend><?php echo __('Admin Add Attribute'); ?></legend>
	<?php
		echo $this->Form->input('name');
		echo $this->Form->input('description');
		echo $this->Form->input('entity_type_id');
		echo $this->Form->input('data_type_id');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit'));?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Html->link(__('List Attributes'), array('action' => 'index'));?></li>
		<li><?php echo $this->Html->link(__('List Entity Types'), array('controller' => 'entity_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Entity Type'), array('controller' => 'entity_types', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Data Types'), array('controller' => 'data_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Data Type'), array('controller' => 'data_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
