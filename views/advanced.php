<?php if (!defined('APPLICATION')) exit(); ?>
<div id="DiscussionForm">
  <h1><?php echo Gdn::Translate("Advanced Search"); ?></h1>
  <?php
    echo $this->Form->Open(array('action' => '/search'));
    echo $this->Form->Errors();
    echo $this->Form->Label('Keywords', 'Search');
    echo $this->Form->TextBox('Search', array('maxlength' => 30));
    
    echo '<div class="SearchMode">';
    echo $this->Form->Label('Search Mode', 'Mode');
    echo $this->Form->DropDown('Mode', $this->SearchMode);
    echo '</div>';

    if (Gdn::Config('Vanilla.Categories.Use') === TRUE) {
      echo '<div class="Category">';
      echo $this->Form->Label('Category', 'Category');
      echo $this->Form->DropDown('Category[]', $this->Categories, array('value' => '-1', 'TextField' => 'Name', 'ValueField' => 'CategoryID', 'multiple' => 'multiple', 'size' => 3));
      echo '</div>';
    }

    echo $this->Form->Button('Advanced Search', array('Name' => ''));
    echo $this->Form->Close();
  ?>
</div>
<?php
