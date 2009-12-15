<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo Gdn::Translate('Sphinx Search Settings'); ?></h1>
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
   <li id="ServerSettings">
      <div class="Info"><?php echo Gdn::Translate('Sphinx Server Settings'); ?></div>
      <table class="Label AltColumns">
         <thead>
            <tr>
               <th><?php echo Gdn::Translate('Setting'); ?></th>
               <th class="Alt"><?php echo Gdn::Translate('Value'); ?></th>
            </tr>
         </thead>
         <tbody>
            <tr class="Alt">
               <th><?php echo Gdn::Translate('Host'); ?></th>
               <td class="Alt"><?php echo $this->Form->TextBox('SphinxSearch.Host'); ?></td>
            </tr>
            <tr class="Alt">
               <th><?php echo Gdn::Translate('Port'); ?></th>
               <td class="Alt"><?php echo $this->Form->TextBox('SphinxSearch.Port'); ?></td>
            </tr>
            <tr class="Alt">
               <th><?php echo Gdn::Translate('Maximum Results'); ?></th>
               <td class="Alt"><?php echo $this->Form->TextBox('SphinxSearch.MaxResults'); ?></td>
            </tr>
         </tbody>
       </table>
   </li>
</ul>
<?php
echo $this->Form->Close('Save');
