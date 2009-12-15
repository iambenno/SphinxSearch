<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php printf(Gdn::Translate("Search results for '%s'"), $this->SearchTerm); ?></h1>
<?php echo $this->Pager->ToString('less'); ?>
<ul class="DataList SearchResults">
<?php
if (isset($this->SearchResults) && $this->SearchResults->NumRows() > 0) {
  echo $this->FetchView(PATH_PLUGINS.DS.'SphinxSearch'.DS.'views'.DS.'results.php');
} else {
?>
	<li><?php echo Gdn::Translate("Your search returned no results."); ?></li>
<?php
}
?>
</ul>
<?php
echo $this->Pager->ToString('more');
