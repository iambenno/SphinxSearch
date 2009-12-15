<?php if (!defined('APPLICATION')) exit();
if ($this->SearchResults->NumRows() > 0) {
  $resultNo = $this->SearchOffset + 1;
	foreach ($this->SearchResults->ResultObject() as $Row) {
?>
	<li class="Row">
		<ul>
			<li class="Title">
				<strong><?php echo Anchor(Format::Text($Row->Title), $Row->Url); ?></strong>
				<?php echo Anchor(Format::Text($Row->Summary), $Row->Url); ?>
			</li>
			<li class="Meta">
				<span><?php printf(Gdn::Translate('Comment by %s'), UserAnchor($Row)); ?></span>
				<span><?php echo Format::Date($Row->DateInserted); ?></span>
				<span><?php echo Anchor(Gdn::Translate('permalink'), $Row->Url); ?></span>
			</li>
		</ul>
	</li>
<?php
    $resultNo++;
	}
}
